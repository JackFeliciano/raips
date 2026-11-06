// The publish path over real HTTP. The test starts the fake hosting server,
// publishes to it with HttpHost, and reads the files the server wrote to disk.
// A tampered signature, a wrong key and a stale clock each get their own case.

import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createFakeServer } from '../src/hosting/fake-server.js';
import { HttpHost, PublishError } from '../src/hosting/index.js';
import { signRequest } from '../src/crypto.js';
import { publish } from '../src/publish.js';

const keyId = 'client-01';
const secret = 'a-test-secret';

const site = {
  name: 'http-site',
  pages: [
    { route: '/', title: 'Home over HTTP', sections: [{ type: 'hero', props: { heading: 'Live' } }] },
    { route: '/about', title: 'About', sections: [{ type: 'about', props: { heading: 'About' } }] },
  ],
};

async function start() {
  const root = mkdtempSync(path.join(tmpdir(), 'raips-host-'));
  const fake = createFakeServer({ root, keyId, secret });
  const url = await fake.listen(0);
  return { root, fake, url };
}

test('http: a signed publish writes every file and the server logs each call', async () => {
  const { root, fake, url } = await start();
  try {
    const config = {
      site: { name: 'http-site', outputDir: 'out' },
      hosting: { driver: 'http', endpoint: url, keyId, secret, root: '/' },
    };
    const receipt = await publish(site, config);
    assert.equal(receipt.driver, 'http');
    assert.equal(receipt.pages, 2);
    assert.equal(receipt.files, 3);

    assert.match(readFileSync(path.join(root, 'index.html'), 'utf8'), /<h1>Live<\/h1>/);
    assert.match(readFileSync(path.join(root, 'about', 'index.html'), 'utf8'), /<h2>About<\/h2>/);
    assert.ok(readFileSync(path.join(root, 'assets', 'site.css'), 'utf8').includes('.r-hero'));

    const manifest = fake.log.filter((entry) => entry.path === '/__raips/manifest');
    assert.equal(manifest.length, 1);
    assert.equal(manifest[0].files, 3);
    assert.equal(fake.log.length, 4);
  } finally {
    await fake.close();
    rmSync(root, { recursive: true, force: true });
  }
});

test('http: a wrong key is refused and stops the publish at the manifest', async () => {
  const { root, fake, url } = await start();
  try {
    const host = new HttpHost({ endpoint: url, keyId: 'other-client', secret });
    await assert.rejects(() => host.publish([{ path: 'index.html', body: 'x' }]), (err) => {
      assert.ok(err instanceof PublishError);
      assert.equal(err.detail.status, 401);
      return true;
    });
    assert.equal(fake.log.length, 0);
  } finally {
    await fake.close();
    rmSync(root, { recursive: true, force: true });
  }
});

test('http: a tampered signature is refused', async () => {
  const { root, fake, url } = await start();
  try {
    const res = await fetch(`${url}/__raips/manifest`, {
      method: 'POST',
      headers: {
        'x-raips-key': keyId,
        'x-raips-timestamp': String(Math.floor(Date.now() / 1000)),
        'x-raips-signature': 'f'.repeat(64),
      },
      body: '{}',
    });
    assert.equal(res.status, 401);
    assert.equal((await res.json()).error, 'bad signature');
  } finally {
    await fake.close();
    rmSync(root, { recursive: true, force: true });
  }
});

test('http: a stale timestamp is refused', async () => {
  const { root, fake, url } = await start();
  try {
    const stamp = Math.floor(Date.now() / 1000) - 3600;
    const headers = signRequest({ secret, method: 'POST', path: '/__raips/manifest', body: '{}', timestamp: stamp, keyId });
    headers['content-type'] = 'application/json';
    const res = await fetch(`${url}/__raips/manifest`, { method: 'POST', headers, body: '{}' });
    assert.equal(res.status, 401);
  } finally {
    await fake.close();
    rmSync(root, { recursive: true, force: true });
  }
});

test('http: a path outside the hosting root never lands on the server', async () => {
  const { root, fake, url } = await start();
  try {
    const host = new HttpHost({ endpoint: url, keyId, secret });
    await assert.rejects(
      () => host.publish([{ path: '../escape.html', body: 'x' }]),
      (err) => {
        assert.ok(err instanceof PublishError);
        assert.equal(err.detail.path, '/../escape.html');
        return true;
      },
    );
    assert.equal(fake.log.filter((e) => e.path === '/__raips/manifest').length, 1);
  } finally {
    await fake.close();
    rmSync(root, { recursive: true, force: true });
  }
});
