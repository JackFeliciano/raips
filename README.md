# RAIPS

RAIPS turns a page written as JSON into a website and publishes it to hosting that lives on another server. One package holds the builder, the section templates, the preview server and the publish step. The application that a member clicks in keeps the page it was given and hands the work here.

Nothing in this repository knows about a customer, a panel vendor or a hosting account. A site project names its own hosting endpoint in its config, and the credential stays on the operator's disk.

## What it does

A site project is a directory with two files: `raips.config.json` for the hosting side and `site.json` for the pages. The builder renders each page through the section templates, the preview server puts the result on loopback so a member can look at it, and the publish step signs every request with the hosting credential and sends the files to the panel.

```
site.json  ->  builder  ->  preview (loopback)
                       \
                        ->  publish  ->  hosting panel
```

## Install and run

Node 20 or newer. There are no dependencies to install.

```
git clone https://github.com/JackFeliciano/raips.git
cd raips
npm test
node bin/raips.js build examples/site
node bin/raips.js preview examples/site
node bin/raips.js publish examples/site
```

`build` prints every file it would publish. `preview` writes the site to `examples/site/.raips/preview` and serves it on `http://127.0.0.1:4173/`. `publish` sends the files to the driver the config names.

The example project ships with `"driver": "fake"`, so the publish verb runs with no credential and writes the site to `.raips/fake-host` on disk.

## The config

Copy `raips.config.sample.json` into a site project as `raips.config.json` and fill the hosting side. The real file lives in `.gitignore`.

```json
{
  "site": { "name": "example-site", "outputDir": "out" },
  "hosting": {
    "driver": "http",
    "endpoint": "https://panel.example.com",
    "root": "/sites/example",
    "credential": { "keyId": "", "secret": "" }
  }
}
```

`driver` is `http` for a real panel, `fake` for a test host on disk, and `none` for preview only. The loader refuses an `http` config with an empty `keyId` or `secret` and names the field it found empty. A publish through `http` never starts while one of them is blank.

## The credential

The hosting panel issues a key id and a secret. `src/crypto.js` holds both in memory, builds a canonical string from the method, path, timestamp and a SHA-256 digest of the body, and signs it with HMAC-SHA256.

```
PUT\n/sites/example/index.html\n1800000000\n<sha256 of the body>
```

The three headers carry the result:

```
x-raips-key: <keyId>
x-raips-timestamp: <unix seconds>
x-raips-signature: <hex hmac>
```

A server reconstructs the same string and compares in constant time. The signature covers the body, so a proxy that rewrites a page invalidates it. The timestamp stops a captured request from being replayed: the reference verifier accepts a window of 300 seconds.

The secret never leaves the process that reads the config. `redact()` prints a key id with its middle hidden, and the CLI prints only that.

## What the hosting panel must implement

Two endpoints. A manifest arrives first, then one request per file.

```
POST /__raips/manifest   {"site":"...","files":[{"path":"index.html","type":"...","bytes":3859}]}
PUT  /__raips/index.html <the file body>
```

A panel answers any `2xx` and the publish continues. Anything else stops the publish at that path and raises `PublishError` with the status, the path and the first 400 bytes of the answer. A panel that verifies signatures can take `verifyRequest` from `src/hosting/fake-server.js` as its reference. The fake server in this repository does exactly that over real HTTP.

## The section templates

Thirteen templates live in `src/sections/index.js`, each one a function that takes props and returns a fragment: `header`, `hero`, `overview`, `features`, `pricing`, `testimonials`, `faq`, `gallery`, `blog`, `callout`, `contact`, `about`, `footer`.

A page names a type and passes props:

```json
{ "type": "hero", "props": { "heading": "A page written as data", "buttons": [{ "label": "Start", "href": "#contact" }] } }
```

Every value is escaped. A link is kept only when it points at `http`, `https`, `mailto`, `tel`, a path under the site or an anchor, so a `javascript:` value never reaches the output.

## The build rules

A page needs a `route`, a `title` and at least one section. A route that walks out of the output directory is refused, and two pages that land on the same file stop the build before anything renders. The build returns the full file list to the caller, so the publish step and the preview step share one path.

## The tests

`npm test` runs 48 checks across six files.

- `test/sections.test.js` covers each template and the escaping.
- `test/builder.test.js` covers validation, routing and the shared stylesheet.
- `test/crypto.test.js` covers the signature, the body digest and the clock window.
- `test/publish.test.js` publishes to a fake host on disk and reads the files back.
- `test/http.test.js` starts the fake hosting server on a port, publishes over real HTTP, and checks that a wrong key, a tampered signature and a stale timestamp each get a 401.
- `test/serve.test.js` covers the static server the live box uses, including the paths it refuses.

## The live box

`showroom/server.js` is a single process that serves a site the builder rendered on start, offers a page that builds a new site from a section template on a visitor's press, and serves the result at `/run/<id>/`. It runs behind a proxy and binds loopback.

```
RAIPS_PORT=8093 RAIPS_HOST=127.0.0.1 RAIPS_DATA=/var/lib/raips RAIPS_SITE=/srv/raips/showroom node showroom/server.js
```

The data directory holds one directory per press. Nothing outside it is written.

Four variables set the box and a fifth is optional:

| Variable | Meaning |
|---|---|
| `RAIPS_PORT` | Listening port. Defaults to 8093. |
| `RAIPS_HOST` | Bind address. Defaults to 127.0.0.1. |
| `RAIPS_DATA` | Directory that holds one folder per press. |
| `RAIPS_SITE` | Directory that holds `demo.html`. |
| `RAIPS_BASE` | Path the site is served under. Defaults to `/`, set to `/raips/` when a proxy strips a prefix. |

## Layout

| Path | What sits there |
|---|---|
| `src/index.js` | The public API and `loadProject`. |
| `src/builder.js` | Page validation, rendering and the shared stylesheet. |
| `src/sections/` | The thirteen section templates. |
| `src/config.js` | Config loading, validation and publish readiness. |
| `src/crypto.js` | Body digest, canonical string, signing, verification, redaction. |
| `src/hosting/` | The fake host, the HTTP client, the null driver, the fake server. |
| `src/publish.js` | The publish step and the file collection. |
| `src/preview.js` | The preview writer and the loopback server. |
| `src/serve.js` | The static file server the live box and a preview share. |
| `showroom/` | The live box: one process, the landing site and the demo. |
| `bin/raips.js` | The command line: build, preview, publish. |
| `examples/site/` | A two-page example project. |

## Status

Version 0.1.0. The builder, the preview and both hosting drivers work and the suite passes. The panel side is a reference implementation in this repository. A production panel has to speak the same two endpoints and verify the same signature.

## Licence

Mozilla Public License 2.0. See [LICENSE](LICENSE). Changes to the files shipped here stay open, and a product that embeds RAIPS keeps the rest of its own code private.
