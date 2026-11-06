// The live box. One process, one port, four things to look at.
//
//   /              the built site, rendered by this repository's builder
//   /demo/         a page that builds a new site from a section template and
//                  publishes it, on the visitor's press
//   /run/<id>/     the site that press produced
//   /health        a plain readiness answer
//
// Every press writes into its own directory under the data directory, so two
// presses never collide. Nothing outside the data directory is written.

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { buildSite, renderStylesheet } from '../src/builder.js';
import { resolveFile } from '../src/serve.js';
import { sectionNames } from '../src/sections/index.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');

const PORT = Number(process.env.RAIPS_PORT ?? 8093);
const HOST = process.env.RAIPS_HOST ?? '127.0.0.1';
const DATA = process.env.RAIPS_DATA ?? path.join(root, '.data');
const SITE = process.env.RAIPS_SITE ? path.resolve(process.env.RAIPS_SITE) : here;
const KEY = process.env.RAIPS_KEY ?? 'demo-key';
const BASE = process.env.RAIPS_BASE ?? '/';

const runs = path.join(DATA, 'runs');
const liveDir = path.join(runs, 'live');
fs.mkdirSync(liveDir, { recursive: true });

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
};

const NAMES = sectionNames();

// ---- the site that answers on / -------------------------------------------

function landingPages() {
  return [
    {
      route: '/',
      title: 'RAIPS',
      description:
        'A website system: a page written as JSON, rendered by section templates, published to a hosting panel.',
      sections: [
        {
          type: 'header',
          props: {
            brand: 'RAIPS',
            links: [
              { label: 'Sections', href: '#sections' },
              { label: 'Demo', href: '/demo/' },
            ],
            cta: { label: 'Build a page', href: '/demo/' },
          },
        },
        {
          type: 'hero',
          props: {
            eyebrow: 'Website system',
            heading: 'A page written as JSON, published to a panel',
            lead: 'This page came out of the builder in this repository. Every section on it is a template taking props, and the publish step signs what it sends.',
            buttons: [
              { label: 'Build a page', href: '/demo/', style: 'primary' },
              { label: 'Read the code', href: 'https://github.com/JackFeliciano/raips', style: 'secondary' },
            ],
          },
        },
        {
          type: 'features',
          props: {
            heading: 'The path a page takes',
            items: [
              { title: 'site.json', text: 'A route, a title and a list of sections, each with its props.' },
              { title: 'Builder', text: 'Checks the route, renders each section through its template, writes the files.' },
              { title: 'Publish', text: 'Sends a manifest, then one signed request per file.' },
            ],
          },
        },
        {
          type: 'overview',
          props: {
            heading: 'Thirteen section templates',
            items: NAMES.map((n) => `${n}: a function that takes props and returns a fragment.`),
          },
        },
        {
          type: 'callout',
          props: {
            heading: 'Watch it build',
            text: 'The demo builds a page from whichever template you pick and puts it on this host.',
            cta: 'Open the demo',
            href: '/demo/',
          },
        },
        {
          type: 'footer',
          props: {
            brand: 'RAIPS',
            links: [{ label: 'Demo', href: '/demo/' }],
            social: [{ label: 'Repository', href: 'https://github.com/JackFeliciano/raips' }],
            legal: 'Mozilla Public License 2.0',
          },
        },
      ],
    },
    {
      route: '/about',
      title: 'How it works',
      description: 'The path from a page object to files on a hosting panel.',
      sections: [
        { type: 'header', props: { brand: 'RAIPS', links: [{ label: 'Home', href: '/' }] } },
        {
          type: 'about',
          props: {
            heading: 'How it works',
            paragraphs: [
              'A site project holds site.json and a config. The config names the hosting driver, the endpoint and the credential.',
              'The builder validates every route before it renders anything, so a page that would escape the output directory stops the build rather than writing outside it.',
              'The publish step collects the files, sends a manifest, then one request per file. Each request carries three headers: the key id, a unix timestamp and an HMAC-SHA256 signature over the method, the path, the timestamp and a digest of the body.',
              'A panel that verifies the signature with a shared secret and a 300 second window will reject a tampered body, a wrong key and a replayed request.',
            ],
          },
        },
        {
          type: 'callout',
          props: { heading: 'Read the protocol', text: 'The signature and the two endpoints are documented in the README.', cta: 'Repository', href: 'https://github.com/JackFeliciano/raips' },
        },
        { type: 'footer', props: { brand: 'RAIPS', legal: 'Mozilla Public License 2.0' } },
      ],
    },
  ];
}

function writeSite(dir, pages) {
  const built = buildSite({ name: 'raips-demo', base: BASE, pages });
  const files = [{ path: 'assets/site.css', body: renderStylesheet() }, ...built.files];
  const base = path.resolve(dir);
  for (const f of files) {
    const full = path.resolve(dir, f.path);
    if (!full.startsWith(base + path.sep)) throw new Error('path escapes the run directory');
    fs.mkdirSync(path.dirname(full), { recursive: true });
    fs.writeFileSync(full, f.body);
  }
  return files;
}

// The landing site is rebuilt on start, so the host always serves what this
// version of the builder renders.
writeSite(liveDir, landingPages());

// ---- helpers --------------------------------------------------------------

function sendJson(res, status, value) {
  const body = JSON.stringify(value);
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(body),
    'cache-control': 'no-store',
  });
  res.end(body);
}

function sendFile(res, dir, pathname) {
  const file = resolveFile(dir, pathname);
  if (!file) {
    sendJson(res, 404, { error: { code: 'not_found', message: 'nothing at this path' } });
    return;
  }
  fs.readFile(file, (err, buf) => {
    if (err) {
      sendJson(res, 404, { error: { code: 'not_found', message: 'nothing at this path' } });
      return;
    }
    const ext = path.extname(file).toLowerCase();
    res.writeHead(200, {
      'content-type': TYPES[ext] ?? 'application/octet-stream',
      'content-length': buf.length,
      'cache-control': ext === '.html' ? 'no-store' : 'public, max-age=300',
      'x-content-type-options': 'nosniff',
    });
    res.end(buf);
  });
}

// The signature a hosting panel would check: HMAC-SHA256 over the method, the
// path, a unix timestamp and a sha256 of the body.
function signature(method, pathname, body, stamp) {
  const digest = crypto.createHash('sha256').update(body ?? '').digest('hex');
  const canonical = `${method}\n${pathname}\n${stamp}\n${digest}`;
  return crypto.createHmac('sha256', KEY).update(canonical).digest('hex');
}

// ---- one press, one run ---------------------------------------------------

function run(section) {
  const id = crypto.randomBytes(6).toString('hex');
  const dir = path.join(runs, id);
  fs.mkdirSync(dir, { recursive: true });

  const props = {
    heading: `The ${section} template, rendered from props`,
    lead: `This page exists because someone pressed a button. The builder read a page object with a ${section} section and wrote these files.`,
    items: [
      { title: 'Props in', text: 'A page object holding the route, the title and the sections.' },
      { title: 'Template', text: `The ${section} function escaped every value and returned a fragment.` },
      { title: 'Files out', text: 'One HTML document and one stylesheet, signed and published.' },
    ],
    paragraphs: [
      `This is the run ${id}.`,
      `The builder rendered the ${section} template from the props above, and the stylesheet beside this document is the one the builder ships.`,
    ],
  };

  const files = writeSite(dir, [
    {
      route: '/',
      title: `RAIPS run ${id}`,
      description: `A page built from the ${section} template.`,
      sections: [
        {
          type: 'header',
          props: { brand: `Run ${id}`, links: [{ label: 'Back to the demo', href: '/demo/' }] },
        },
        { type: section, props },
        {
          type: 'footer',
          props: {
            brand: 'RAIPS',
            legal: `Run ${id}`,
            copyright: `Built by the builder at ${new Date().toISOString()}`,
          },
        },
      ],
    },
  ]);

  const stamp = Math.floor(Date.now() / 1000);
  const manifest = JSON.stringify({
    site: `run-${id}`,
    files: files.map((f) => ({ path: f.path, bytes: Buffer.byteLength(f.body) })),
  });

  return {
    id,
    url: `/run/${id}/`,
    section,
    pages: 1,
    files: files.length,
    bytes: files.reduce((n, f) => n + Buffer.byteLength(f.body), 0),
    manifest: {
      path: '/__raips/manifest',
      timestamp: stamp,
      signature: signature('POST', '/__raips/manifest', manifest, stamp),
      body: manifest,
    },
  };
}

const demoFile = path.join(SITE, 'demo.html');

// ---- the server -----------------------------------------------------------

const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const p = url.pathname;

  if (req.method === 'GET' && p === '/health') {
    sendJson(res, 200, { ok: true, sections: NAMES.length });
    return;
  }

  if (req.method === 'GET' && p === '/section-names') {
    sendJson(res, 200, { sections: NAMES });
    return;
  }

  if (req.method === 'GET' && p === '/demo') {
    res.writeHead(301, { location: './demo/' });
    res.end();
    return;
  }

  if (req.method === 'GET' && p === '/demo/') {
    fs.readFile(demoFile, (err, page) => {
      if (err) {
        sendJson(res, 500, { error: { code: 'demo_missing', message: 'demo page is not on disk' } });
        return;
      }
      res.writeHead(200, {
        'content-type': 'text/html; charset=utf-8',
        'content-length': page.length,
        'cache-control': 'no-store',
      });
      res.end(page);
    });
    return;
  }

  if (req.method === 'POST' && p === '/publish') {
    let raw = '';
    let tooBig = false;
    req.on('data', (chunk) => {
      raw += chunk;
      if (raw.length > 100_000) {
        tooBig = true;
        req.destroy();
      }
    });
    req.on('end', () => {
      if (tooBig) return;
      let body = {};
      try {
        body = raw ? JSON.parse(raw) : {};
      } catch {
        sendJson(res, 400, { error: { code: 'bad_body', message: 'body is not json' } });
        return;
      }
      const section = NAMES.includes(body.section) ? body.section : 'hero';
      try {
        sendJson(res, 200, run(section));
      } catch (err) {
        sendJson(res, 500, { error: { code: 'build_failed', message: String(err && err.message) } });
      }
    });
    return;
  }

  const m = p.match(/^\/run\/([0-9a-f]{6,16})(\/.*)?$/);
  if (req.method === 'GET' && m) {
    sendFile(res, path.join(runs, m[1]), m[2] || '/');
    return;
  }

  if (req.method === 'GET') {
    sendFile(res, liveDir, p);
    return;
  }

  sendJson(res, 405, { error: { code: 'method_not_allowed', message: req.method } });
});

server.listen(PORT, HOST, () => {
  console.log(`raips on http://${HOST}:${PORT}`);
});

const stop = () => server.close(() => process.exit(0));
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
