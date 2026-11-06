// Publish tests. The fake host writes to a temporary directory, so a publish
// leaves real files a test reads back byte for byte.

import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { publish, collectFiles, PublishRefused } from '../src/publish.js';
import { FakeHost, NullHost, createHost, PublishError } from '../src/hosting/index.js';
import { publishReadiness, validateConfig } from '../src/config.js';

const scratch = () => mkdtempSync(path.join(tmpdir(), 'raips-test-'));

const site = {
  name: 'example-site',
  pages: [
    {
      route: '/',
      title: 'Home',
      sections: [
        { type: 'header', props: { brand: 'Example' } },
        { type: 'hero', props: { heading: 'Hello' } },
      ],
    },
    { route: '/about', title: 'About', sections: [{ type: 'about', props: { heading: 'About' } }] },
  ],
};

const fakeConfig = (fakeRoot) => ({
  site: { name: 'example-site', outputDir: 'out' },
  hosting: { driver: 'fake', endpoint: '', root: '/', keyId: '', secret: '', fakeRoot },
});

test('config: the sample file validates and reports what is missing', () => {
  const sample = JSON.parse(
    readFileSync(new URL('../raips.config.sample.json', import.meta.url), 'utf8'),
  );
  const config = validateConfig(sample);
  assert.equal(config.hosting.driver, 'fake');
  assert.deepEqual(publishReadiness(config), { ready: true, problems: [] });
});

test('config: the http driver refuses an empty credential and names both fields', () => {
  const config = {
    site: { name: 's', outputDir: 'out' },
    hosting: { driver: 'http', endpoint: 'https://host.example', credential: { keyId: '', secret: '' } },
  };
  assert.throws(() => validateConfig(config), /credential\.keyId is empty/);
  config.hosting.credential.keyId = 'k';
  assert.throws(() => validateConfig(config), /credential\.secret is empty/);
});

test('config: publishReadiness lists every empty field for an http driver', () => {
  const config = validateConfig({
    site: { name: 's', outputDir: 'out' },
    hosting: { driver: 'fake', credential: { keyId: '', secret: '' } },
  });
  assert.equal(publishReadiness(config).ready, true);

  const httpConfig = {
    site: { name: 's', outputDir: 'out' },
    hosting: { driver: 'http', endpoint: '', keyId: '', secret: '', root: '/' },
  };
  assert.deepEqual(publishReadiness(httpConfig).problems, [
    'hosting.endpoint',
    'hosting.credential.keyId',
    'hosting.credential.secret',
  ]);
});

test('publish: collectFiles returns the stylesheet plus one file per page', () => {
  const { files, pages } = collectFiles(site);
  assert.equal(pages, 2);
  assert.deepEqual(files.map((f) => f.path), ['assets/site.css', 'index.html', 'about/index.html']);
});

test('publish: the fake host writes the files and reports every byte', async () => {
  const root = scratch();
  try {
    const receipt = await publish(site, fakeConfig(root));
    assert.equal(receipt.ok, true);
    assert.equal(receipt.driver, 'fake');
    assert.equal(receipt.pages, 2);
    assert.equal(receipt.files, 3);

    const index = readFileSync(path.join(root, 'index.html'), 'utf8');
    assert.match(index, /<h1>Hello<\/h1>/);
    assert.ok(existsSync(path.join(root, 'about', 'index.html')));
    assert.ok(existsSync(path.join(root, 'assets', 'site.css')));

    const css = readFileSync(path.join(root, 'assets', 'site.css'), 'utf8');
    assert.equal(receipt.bytes, Buffer.byteLength(index) + Buffer.byteLength(css) +
      Buffer.byteLength(readFileSync(path.join(root, 'about', 'index.html'), 'utf8')));
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('publish: a second publish overwrites the first and the fake host counts both calls', async () => {
  const root = scratch();
  try {
    const host = new FakeHost({ root });
    const config = fakeConfig(root);
    await publish(site, config, { host });
    site.pages[0].title = 'Home two';
    const receipt = await publish(site, config, { host });
    assert.equal(host.calls, 2);
    assert.equal(receipt.ok, true);
    assert.match(readFileSync(path.join(root, 'index.html'), 'utf8'), /<title>Home two<\/title>/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('publish: the fake host refuses a path that leaves its root', async () => {
  const root = scratch();
  try {
    const host = new FakeHost({ root });
    await assert.rejects(
      () => host.publish([{ path: '../escape.html', body: 'x' }]),
      (err) => {
        assert.ok(err instanceof PublishError);
        assert.match(err.message, /escapes the hosting root/);
        return true;
      },
    );
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('publish: the null driver refuses with a reason', async () => {
  await assert.rejects(() => new NullHost({ reason: 'nothing configured' }).publish([]), /nothing configured/);
});

test('publish: createHost picks the driver the config names', () => {
  const root = scratch();
  try {
    assert.ok(createHost(fakeConfig(root)) instanceof FakeHost);
    const httpDriver = createHost({
      hosting: { driver: 'http', endpoint: 'https://h.example', keyId: 'k', secret: 's', root: '/' },
    });
    assert.equal(httpDriver.constructor.name, 'HttpHost');
    assert.ok(createHost({ hosting: { driver: 'unset' } }) instanceof NullHost);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test('publish: an http config with no credential refuses before the builder runs', async () => {
  const config = {
    site: { name: 'example-site', outputDir: 'out' },
    hosting: { driver: 'http', endpoint: '', keyId: '', secret: '', root: '/' },
  };
  await assert.rejects(
    () => publish({ pages: [{ route: '/', title: 'T', sections: [{ type: 'hero' }] }] }, config),
    (err) => {
      assert.ok(err instanceof PublishRefused);
      assert.deepEqual(err.problems, [
        'hosting.endpoint',
        'hosting.credential.keyId',
        'hosting.credential.secret',
      ]);
      return true;
    },
  );
});
