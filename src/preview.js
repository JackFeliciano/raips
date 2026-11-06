// The preview step. It writes the built site to a local directory and serves
// it on loopback, so a member sees the page before anything reaches hosting.
// No credential is read on this path.

import { createServer } from 'node:http';
import { mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { collectFiles } from './publish.js';

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
};

export function writePreview(site, outputDir) {
  const { files, pages } = collectFiles(site);
  const root = path.resolve(outputDir);
  rmSync(root, { recursive: true, force: true });
  for (const file of files) {
    const target = path.resolve(root, file.path);
    if (target !== root && !target.startsWith(root + path.sep)) {
      throw new Error(`preview: path escapes the output directory: ${file.path}`);
    }
    mkdirSync(path.dirname(target), { recursive: true });
    writeFileSync(target, file.body, 'utf8');
  }
  return { outputDir: root, files: files.length, pages };
}

export function servePreview(outputDir, { port = 4173, host = '127.0.0.1' } = {}) {
  const root = path.resolve(outputDir);
  const server = createServer((req, res) => {
    const urlPath = decodeURIComponent((req.url ?? '/').split('?')[0]);
    let rel = urlPath.replace(/^\/+/, '');
    if (rel === '' || rel.endsWith('/')) rel += 'index.html';
    const target = path.resolve(root, rel);
    if (target !== root && !target.startsWith(root + path.sep)) {
      res.writeHead(403, { 'content-type': 'text/plain' });
      res.end('forbidden');
      return;
    }
    try {
      const body = readFileSync(target);
      const ext = path.extname(target).toLowerCase();
      res.writeHead(200, { 'content-type': TYPES[ext] ?? 'application/octet-stream' });
      res.end(body);
    } catch {
      res.writeHead(404, { 'content-type': 'text/plain' });
      res.end('not found');
    }
  });
  return {
    server,
    listen() {
      return new Promise((resolve) => {
        server.listen(port, host, () => {
          const address = server.address();
          resolve(`http://${host}:${address.port}/`);
        });
      });
    },
    close() {
      return new Promise((resolve) => server.close(resolve));
    },
  };
}
