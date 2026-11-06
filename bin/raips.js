#!/usr/bin/env node
// The command line. Three verbs: build, preview, publish.
//
//   node bin/raips.js build   examples/site
//   node bin/raips.js preview examples/site
//   node bin/raips.js publish examples/site

import path from 'node:path';
import { loadProject, writePreview, servePreview, collectFiles, publish, redact } from '../src/index.js';

const USAGE = `raips <build|preview|publish> <site-dir>

  build    render the site and report the files it would publish
  preview  render the site, write it to .raips/preview and serve it
  publish  render the site and push it to the hosting driver in raips.config.json
`;

function main(argv) {
  const [verb, siteDir] = argv;
  if (!verb || !siteDir) {
    process.stdout.write(USAGE);
    return 2;
  }
  const dir = path.resolve(siteDir);

  return Promise.resolve()
    .then(() => loadProject(dir))
    .then(async ({ config, site }) => {
      if (verb === 'build') {
        const { files, pages } = collectFiles(site);
        console.log(`built ${pages} page(s), ${files.length} file(s)`);
        for (const f of files) console.log(`  ${f.path}  ${Buffer.byteLength(f.body)} bytes`);
        return 0;
      }

      if (verb === 'preview') {
        const out = path.join(dir, '.raips', 'preview');
        const written = writePreview(site, out);
        const preview = servePreview(out, { port: Number(process.env.RAIPS_PREVIEW_PORT ?? 4173) });
        const url = await preview.listen();
        console.log(`${written.files} file(s) written to ${written.outputDir}`);
        console.log(`preview at ${url}`);
        console.log('ctrl+c to stop');
        return new Promise(() => {});
      }

      if (verb === 'publish') {
        const receipt = await publish(site, config);
        console.log(`published ${receipt.files} file(s) to ${receipt.driver}, ${receipt.bytes} bytes`);
        console.log(`key ${redact(config.hosting.keyId) || '(none)'}`);
        return 0;
      }

      process.stdout.write(USAGE);
      return 2;
    })
    .catch((err) => {
      console.error(err.message);
      if (err.problems) for (const p of err.problems) console.error(`  ${p}`);
      return 1;
    });
}

main(process.argv.slice(2)).then((code) => {
  if (typeof code === 'number') process.exitCode = code;
});
