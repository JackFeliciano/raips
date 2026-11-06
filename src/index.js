// The public surface of RAIPS.
//
// A caller reads a site file, loads the config, builds, previews and publishes.
// The app keeps the page a member calls; the work happens here.

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { loadConfig } from './config.js';

export { buildSite, renderPage, renderSections, validatePage, routeToFile, renderStylesheet, BuildError } from './builder.js';
export { sections, sectionNames } from './sections/index.js';
export { loadConfig, validateConfig, publishReadiness, configPath, CONFIG_NAME } from './config.js';
export { publish, collectFiles, PublishRefused } from './publish.js';
export { writePreview, servePreview } from './preview.js';
export { FakeHost, HttpHost, NullHost, createHost, PublishError } from './hosting/index.js';
export { signRequest, verifyRequest, bodyDigest, canonical, redact } from './crypto.js';
export { esc, safeUrl } from './html.js';

/**
 * Read a site project from disk. A project is a directory holding
 * raips.config.json and site.json.
 */
export function loadProject(siteDir) {
  const dir = path.resolve(siteDir);
  const config = loadConfig(dir);
  let site;
  try {
    site = JSON.parse(readFileSync(path.join(dir, 'site.json'), 'utf8'));
  } catch (err) {
    if (err instanceof SyntaxError) throw new Error('site.json is not valid JSON');
    throw new Error(`no site.json in ${dir}`);
  }
  if (!site.name) site.name = config.site.name;
  site.baseDir = dir;
  return { config, site, siteDir: dir };
}
