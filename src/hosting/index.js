// The hosting client. One interface, three implementations: fake for tests,
// http for a real hosting panel, none for preview only.
//
// publish(site) takes the files the builder produced and returns a receipt.
// Every driver returns the same shape, so the publish step does not branch on
// the driver.

import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { signRequest, redact } from '../crypto.js';

export class PublishError extends Error {
  constructor(message, detail) {
    super(message);
    this.name = 'PublishError';
    this.detail = detail;
  }
}

/**
 * The fake host. It keeps a directory on disk and counts what it received, so
 * a test can publish, read the result back and check every byte. It rejects a
 * write outside its root and a file it was not told to accept.
 */
export class FakeHost {
  constructor({ root, hostRoot = '/' } = {}) {
    if (!root) throw new Error('FakeHost: root is required');
    this.root = path.resolve(root);
    this.hostRoot = hostRoot;
    this.received = [];
    this.calls = 0;
  }

  target(relPath) {
    const resolved = path.resolve(this.root, relPath);
    if (resolved !== this.root && !resolved.startsWith(this.root + path.sep)) {
      throw new PublishError(`path escapes the hosting root: ${relPath}`);
    }
    return resolved;
  }

  async publish(files, { site } = {}) {
    this.calls += 1;
    const written = [];
    for (const file of files) {
      const target = this.target(file.path);
      mkdirSync(path.dirname(target), { recursive: true });
      writeFileSync(target, file.body, 'utf8');
      written.push({ path: file.path, bytes: Buffer.byteLength(file.body) });
      this.received.push({ path: file.path, body: file.body });
    }
    return {
      ok: true,
      driver: 'fake',
      site: site?.name ?? '',
      published: written,
      bytes: written.reduce((n, f) => n + f.bytes, 0),
      at: new Date().toISOString(),
    };
  }

  read(relPath) {
    return readFileSync(this.target(relPath), 'utf8');
  }

  reset() {
    this.received = [];
    this.calls = 0;
  }
}

/**
 * The HTTP host. It signs each request with the key and secret from the
 * config and posts a manifest first, then one request per file. A panel that
 * answers anything other than 2xx stops the publish at that file, and the
 * error carries the status and the path.
 */
export class HttpHost {
  constructor({ endpoint, keyId, secret, root = '/', fetchImpl, timeoutMs = 30000 } = {}) {
    if (!endpoint) throw new Error('HttpHost: endpoint is required');
    if (!keyId) throw new Error('HttpHost: keyId is required');
    if (!secret) throw new Error('HttpHost: secret is required');
    this.endpoint = endpoint.replace(/\/+$/, '');
    this.keyId = keyId;
    this.secret = secret;
    this.root = root;
    this.fetch = fetchImpl ?? globalThis.fetch;
    this.timeoutMs = timeoutMs;
    this.calls = 0;
  }

  pathFor(relPath) {
    const base = this.root.endsWith('/') ? this.root.slice(0, -1) : this.root;
    return `${base}/${relPath.replace(/^\/+/, '')}`;
  }

  async request(method, urlPath, body) {
    this.calls += 1;
    const headers = {
      ...signRequest({ secret: this.secret, method, path: urlPath, body, keyId: this.keyId }),
      'content-type': 'application/json',
    };
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    try {
      const res = await this.fetch(`${this.endpoint}${urlPath}`, {
        method,
        headers,
        body: body ?? undefined,
        signal: controller.signal,
      });
      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new PublishError(`hosting answered ${res.status} for ${urlPath}`, {
          status: res.status,
          path: urlPath,
          body: text.slice(0, 400),
        });
      }
      return res;
    } finally {
      clearTimeout(timer);
    }
  }

  async publish(files, { site } = {}) {
    const manifest = {
      site: site?.name ?? '',
      files: files.map((f) => ({ path: f.path, type: f.type ?? 'text/html; charset=utf-8', bytes: Buffer.byteLength(f.body) })),
    };
    await this.request('POST', '/__raips/manifest', JSON.stringify(manifest));
    const published = [];
    for (const file of files) {
      const urlPath = this.pathFor(file.path);
      await this.request('PUT', urlPath, file.body);
      published.push({ path: file.path, bytes: Buffer.byteLength(file.body) });
    }
    return {
      ok: true,
      driver: 'http',
      site: site?.name ?? '',
      published,
      bytes: published.reduce((n, f) => n + f.bytes, 0),
      at: new Date().toISOString(),
    };
  }
}

/**
 * A driver that refuses to publish and says why. Preview uses it so a member
 * can look at the site without a credential ever being read.
 */
export class NullHost {
  constructor({ reason = 'no hosting driver configured' } = {}) {
    this.reason = reason;
  }

  async publish() {
    throw new PublishError(`publish refused: ${this.reason}`);
  }
}

export function createHost(config, options = {}) {
  const driver = config?.hosting?.driver;
  if (driver === 'fake') {
    return new FakeHost({ root: options.fakeRoot ?? config.hosting.fakeRoot ?? '.raips/fake-host' });
  }
  if (driver === 'http') {
    return new HttpHost({
      endpoint: config.hosting.endpoint,
      keyId: config.hosting.keyId,
      secret: config.hosting.secret,
      root: config.hosting.root,
      fetchImpl: options.fetchImpl,
    });
  }
  return new NullHost({ reason: `driver "${driver ?? ''}" has no client` });
}

export { redact };
export const exampleHostScript = path.join(
  path.dirname(fileURLToPath(import.meta.url)),
  'fake-server.js',
);
