# raips

A small website system. It reads a JSON description of a site, renders pages
from a fixed set of section templates, previews the result on a local port, and
publishes the built files to a hosting panel over signed HTTP. It also asks a
registrar which domain names are free.

Plain PHP 8.2. No Composer, no vendor directory, no dependencies outside the
extensions that ship with PHP. The test runner is a file in `tests/`.

## What it does

- **Build.** `site.json` describes a set of pages. Each page carries a list of
  sections. The builder turns each section into HTML through a template and
  writes the site to disk.
- **Preview.** Serves the build on a loopback port with correct content types,
  so a change can be read in a browser before it goes anywhere.
- **Publish.** Sends the built files to a hosting panel. Every request carries a
  signature over the method, the path, the timestamp and a digest of the body.
  The panel rejects a body edited in flight and a wrong secret.
- **Domains.** Asks a registrar which of a list of names are free, what they
  cost, and whether a name is premium.

## Sections

Thirteen templates, each a function of its props: `header`, `hero`, `features`,
`overview`, `pricing`, `faq`, `about`, `gallery`, `blog`, `contact`,
`callToAction`, `testimonials`, `footer`. A template reads no file, no clock and
no session, so the same input always renders the same bytes.

## Install

```sh
git clone https://github.com/JackFeliciano/raips-php.git
cd raips-php
cp raips.config.sample.json raips.config.json
```

Edit `raips.config.json`. Git ignores that file, and it holds the only place
a credential may appear.

## The four commands

```sh
php bin/raips build examples/site
php bin/raips preview examples/site 8080
php bin/raips publish examples/site
php bin/raips domains cobaltstudio com,net,org,io,dev
```

`build` writes into `<fakeRoot>`, `preview` serves what would be published, and
`domains` prints one line per name with its state.

## Configuration

| Key | What it sets |
| --- | --- |
| `site.dir` | The folder holding `site.json`, relative to the folder holding the config |
| `site.base` | The path prefix the pages assume, when the site does not sit at the host root. `RAIPS_BASE` in the environment beats it |
| `domains.driver` | `fixture` reads a table in the config, `dynadot` asks the registrar |
| `domains.keyEnv` | The name of the environment variable that carries the registrar key |
| `domains.sandbox` | Sends registrar calls to the sandbox host |
| `hosting.driver` | `fake` writes to disk, `http` sends signed requests, `null` counts files |
| `hosting.fakeRoot` | Where the `fake` driver writes, relative to the config folder |
| `hosting.baseUrl` | The panel address for the `http` driver |
| `hosting.credential.keyId` | The key id the panel knows |
| `hosting.credential.secret` | The shared secret, the one value that must never be committed |

The registrar key never goes in the config. It lives in the environment under
the name `domains.keyEnv` gives, and the code reads it from there.

## The signing

The panel and this client agree on one string:

```
METHOD
path
timestamp
sha256(body)
```

`hash_hmac('sha256', ...)` over that string, with the shared secret as the key,
travels in a header beside the key id and the timestamp. The panel rebuilds the
string from what it received. A timestamp outside five minutes is stale.

## Tests

```sh
php tests/run.php
```

Eighty-six checks across eleven files. The publish tests start a real panel on
a loopback port with a secret generated for that run, so the signature path is
exercised end to end rather than against a stub.

## Layout

```
src/            the library, one class per file
src/Hosting/    three drivers behind one interface
src/Domains/    registrar drivers and the search panel
src/Showroom/   the live box that answers on four paths
bin/raips       the command line
showroom/       the demo page and the front controller
examples/site/  a small site that doubles as an acceptance test
tests/          the runner and the checks
deploy/         a systemd unit and an nginx block
```

## The showroom

```sh
RAIPS_SITE=showroom RAIPS_BASE=/raips/ php -S 127.0.0.1:8093 -t showroom showroom/index.php
```

One process on the built-in server answers on four paths: the built site at
`/`, a demo page at `/demo/` that builds a page from a template the visitor
picks, the run it produced at `/run/<id>/`, and `/health`. Each press writes
into its own folder under the data directory, so two presses never collide.

## Licence

Mozilla Public License 2.0. See `LICENSE`.
