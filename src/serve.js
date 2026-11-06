// A static server for a built site. The RAIPS box runs this behind a proxy,
// and a preview run uses the same code with the output directory a member just
// built. It serves files and nothing else: no directory index, no write, no
// path that leaves the root.

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
};

const notFound = (res) => {
  res.writeHead(404, { 'content-type': 'application/json; charset=utf-8' });
  res.end(JSON.stringify({ error: { code: 'not_found', message: 'nothing at this path' } }));
};

export function resolveFile(root, pathname) {
  let rel = decodeURIComponent(pathname);
  if (rel === '/' || rel === '') rel = '/index.html';
  if (rel.endsWith('/')) rel += 'index.html';
  const full = path.resolve(root, '.' + rel);
  const base = path.resolve(root);
  if (full !== base && !full.startsWith(base + path.sep)) return null;
  return full;
}

export function createStaticServer({ root, host = '127.0.0.1', port = 0 }) {
  const base = path.resolve(root);

  const server = http.createServer((req, res) => {
    if (req.method !== 'GET' && req.method !== 'HEAD') {
      res.writeHead(405, { 'content-type': 'application/json; charset=utf-8', allow: 'GET, HEAD' });
      res.end(JSON.stringify({ error: { code: 'method_not_allowed', message: req.method } }));
      return;
    }

    const url = new URL(req.url, 'http://localhost');
    const file = resolveFile(base, url.pathname);
    if (!file) {
      notFound(res);
      return;
    }

    fs.readFile(file, (err, buf) => {
      if (err) {
        notFound(res);
        return;
      }
      const type = TYPES[path.extname(file).toLowerCase()] ?? 'application/octet-stream';
      res.writeHead(200, {
        'content-type': type,
        'content-length': buf.length,
        'cache-control': type.startsWith('text/html') ? 'no-store' : 'public, max-age=300',
        'x-content-type-options': 'nosniff',
      });
      res.end(req.method === 'HEAD' ? undefined : buf);
    });
  });

  return {
    server,
    listen(port_ = port) {
      return new Promise((resolve) => {
        server.listen(port_, host, () => {
          const addr = server.address();
          resolve(`http://${host}:${addr.port}`);
        });
      });
    },
    close() {
      return new Promise((resolve) => server.close(() => resolve()));
    },
  };
}
