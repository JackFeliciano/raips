// The static server and the demo share resolveFile. A path that would leave the
// root resolves to null, so neither of them can serve a file from outside it.

import test from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { resolveFile, createStaticServer } from '../src/serve.js';

const root = path.resolve('examples/site');

test('serve: a plain route maps into the root', () => {
  assert.equal(resolveFile(root, '/'), path.join(root, 'index.html'));
  assert.equal(resolveFile(root, '/about/'), path.join(root, 'about', 'index.html'));
  assert.equal(resolveFile(root, '/assets/site.css'), path.join(root, 'assets', 'site.css'));
});

test('serve: a path that leaves the root resolves to null', () => {
  assert.equal(resolveFile(root, '/../secret.txt'), null);
  assert.equal(resolveFile(root, '/../../etc/passwd'), null);
  assert.equal(resolveFile(root, '/%2e%2e%2fsecret.txt'), null);
});

test('serve: a real request gets the file it names', async () => {
  const host = createStaticServer({ root });
  const url = await host.listen(0);
  const res = await fetch(`${url}/site.json`);
  assert.equal(res.status, 200);
  assert.match(res.headers.get('content-type'), /application\/json/);
  const body = await res.json();
  assert.equal(body.name, 'example-site');
  await host.close();
});

test('serve: a missing file is a 404 and a write is a 405', async () => {
  const host = createStaticServer({ root });
  const url = await host.listen(0);
  const missing = await fetch(`${url}/nothing-here`);
  assert.equal(missing.status, 404);
  const write = await fetch(`${url}/site.json`, { method: 'POST', body: '{}' });
  assert.equal(write.status, 405);
  await host.close();
});
