// The publish step. It builds the site, refuses to go further when the config
// is not ready, publishes through the driver the config names and writes a
// receipt the caller can log or store.

import path from 'node:path';
import { buildSite, renderStylesheet } from './builder.js';
import { createHost } from './hosting/index.js';
import { publishReadiness } from './config.js';

export class PublishRefused extends Error {
  constructor(problems) {
    super(`publish refused, missing ${problems.join(', ')}`);
    this.name = 'PublishRefused';
    this.problems = problems;
  }
}

export function collectFiles(site) {
  const built = buildSite(site);
  const files = [
    { path: 'assets/site.css', body: renderStylesheet(), type: 'text/css; charset=utf-8' },
    ...built.files,
  ];
  return { files, pages: built.pages };
}

export async function publish(site, config, options = {}) {
  const readiness = publishReadiness(config);
  if (!readiness.ready && !options.force) {
    throw new PublishRefused(readiness.problems);
  }
  const { files, pages } = collectFiles(site);
  const host = options.host ?? createHost(config, options);
  const receipt = await host.publish(files, { site: { name: config.site.name } });
  return {
    ...receipt,
    pages,
    files: files.length,
    outputDir: path.join(site?.baseDir ?? '.', config.site.outputDir),
  };
}
