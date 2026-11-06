// A fake hosting server. It speaks the same protocol HttpHost speaks, verifies
// every signature with the shared secret and writes files under a root. The
// test suite starts it, publishes to it over real HTTP and reads the files back.
//
//   node src/hosting/fake-server.js --root .raips/fake-host --key demo --secret demo-secret

import { createServer } from 'node:http';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { verifyRequest, SIGNATURE_HEADER, KEY_HEADER, TIMESTAMP_HEADER } from '../crypto.js';

export function createFakeServer({ root, keyId, secret, window = 300 }) {
  const base = path.resolve(root);
  const log = [];

  const server = createServer(async (req, res) => {
    const chunks = [];
    for await (const chunk of req) chunks.push(chunk);
    const body = Buffer.concat(chunks).toString('utf8');
    const urlPath = req.url.split('?')[0];
    const signature = req.headers[SIGNATURE_HEADER];
    const key = req.headers[KEY_HEADER];
    const stamp = Number(req.headers[TIMESTAMP_HEADER]);

    if (key !== keyId) {
      res.writeHead(401, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ error: 'unknown key' }));
      return;
    }
    const valid = verifyRequest({
      secret,
      method: req.method,
      path: urlPath,
      body,
      timestamp: stamp,
      signature,
      window,
    });
    if (!valid) {
      res.writeHead(401, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ error: 'bad signature' }));
      return;
    }

    if (req.method === 'POST' && urlPath === '/__raips/manifest') {
      let manifest = null;
      try {
        manifest = JSON.parse(body);
      } catch {
        res.writeHead(400, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ error: 'manifest is not json' }));
        return;
      }
      log.push({ at: Date.now(), path: urlPath, files: manifest.files?.length ?? 0 });
      res.writeHead(200, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ accepted: manifest.files?.length ?? 0 }));
      return;
    }

    if (req.method === 'PUT') {
      const rel = urlPath.replace(/^\/+/, '');
      const target = path.resolve(base, rel);
      if (target !== base && !target.startsWith(base + path.sep)) {
        res.writeHead(403, { 'content-type': 'application/json' });
        res.end(JSON.stringify({ error: 'path escapes root' }));
        return;
      }
      mkdirSync(path.dirname(target), { recursive: true });
      writeFileSync(target, body, 'utf8');
      log.push({ at: Date.now(), path: urlPath, bytes: Buffer.byteLength(body) });
      res.writeHead(201, { 'content-type': 'application/json' });
      res.end(JSON.stringify({ written: rel, bytes: Buffer.byteLength(body) }));
      return;
    }

    res.writeHead(405, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ error: 'method not allowed' }));
  });

  return {
    server,
    log,
    listen(port = 0, host = '127.0.0.1') {
      return new Promise((resolve) => {
        server.listen(port, host, () => {
          const address = server.address();
          resolve(`http://${host}:${address.port}`);
        });
      });
    },
    close() {
      return new Promise((resolve) => server.close(resolve));
    },
  };
}

const isMain = process.argv[1] && process.argv[1].endsWith('fake-server.js');
if (isMain) {
  const args = process.argv.slice(2);
  const arg = (name, fallback) => {
    const i = args.indexOf(`--${name}`);
    return i >= 0 && args[i + 1] ? args[i + 1] : fallback;
  };
  const root = arg('root', '.raips/fake-host');
  const keyId = arg('key', 'demo-key');
  const secret = arg('secret', process.env.RAIPS_FAKE_SECRET ?? '');
  if (!secret) {
    console.error('fake-server: pass --secret or set RAIPS_FAKE_SECRET');
    process.exit(2);
  }
  const port = Number(arg('port', '8788'));
  const fake = createFakeServer({ root, keyId, secret });
  fake.listen(port).then((url) => {
    console.log(`fake host listening on ${url}, root ${path.resolve(root)}`);
  });
}
