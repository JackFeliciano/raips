// Configuration for a site project.
//
// raips.config.json holds the hosting endpoint and the credential. The file
// is listed in .gitignore, and raips.config.sample.json ships in its place
// with every key named and empty.

import { readFileSync } from 'node:fs';
import path from 'node:path';

export const CONFIG_NAME = 'raips.config.json';

export function configPath(siteDir) {
  return path.join(siteDir, CONFIG_NAME);
}

export function loadConfig(siteDir) {
  const file = configPath(siteDir);
  let raw;
  try {
    raw = readFileSync(file, 'utf8');
  } catch (err) {
    if (err.code === 'ENOENT') {
      throw new Error(
        `no ${CONFIG_NAME} in ${siteDir}. Copy raips.config.sample.json and fill the hosting keys.`,
      );
    }
    throw err;
  }
  let parsed;
  try {
    parsed = JSON.parse(raw);
  } catch {
    throw new Error(`${CONFIG_NAME} is not valid JSON`);
  }
  return validateConfig(parsed);
}

export function validateConfig(config) {
  if (!config || typeof config !== 'object') throw new Error('config: an object is required');
  const site = config.site ?? {};
  if (!site.name) throw new Error('config: site.name is required');
  if (!site.outputDir) throw new Error('config: site.outputDir is required');

  const hosting = config.hosting ?? {};
  if (!hosting.driver) throw new Error('config: hosting.driver is required');

  if (hosting.driver !== 'fake' && hosting.driver !== 'none') {
    if (!hosting.endpoint) throw new Error('config: hosting.endpoint is required');
    const cred = hosting.credential ?? {};
    if (!cred.keyId) throw new Error(`config: hosting.credential.keyId is empty. Fill it in ${CONFIG_NAME}`);
    if (!cred.secret) throw new Error(`config: hosting.credential.secret is empty. Fill it in ${CONFIG_NAME}`);
  }

  return {
    site: {
      name: site.name,
      outputDir: site.outputDir,
      sectionsDir: site.sectionsDir ?? path.join('sections'),
      baseUrl: site.baseUrl ?? '',
    },
    hosting: {
      driver: hosting.driver,
      endpoint: hosting.endpoint ?? '',
      root: hosting.root ?? '/',
      keyId: hosting.credential?.keyId ?? '',
      secret: hosting.credential?.secret ?? '',
    },
  };
}

/**
 * A config is ready to publish when it names a driver and carries a full
 * credential. The fake driver needs no credential, which is what makes the
 * test suite runnable with an empty sample file.
 */
export function publishReadiness(config) {
  const problems = [];
  if (!config.hosting.driver) problems.push('hosting.driver');
  if (config.hosting.driver !== 'fake') {
    if (!config.hosting.endpoint) problems.push('hosting.endpoint');
    if (!config.hosting.keyId) problems.push('hosting.credential.keyId');
    if (!config.hosting.secret) problems.push('hosting.credential.secret');
  }
  return { ready: problems.length === 0, problems };
}
