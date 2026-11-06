// Builder tests. A page that is wrong must fail before it renders, and the
// route to file map must not let one page overwrite another.

import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildSite,
  renderPage,
  validatePage,
  routeToFile,
  renderStylesheet,
  BuildError,
} from '../src/builder.js';

const page = (over = {}) => ({
  route: '/',
  title: 'Title',
  sections: [{ type: 'hero', props: { heading: 'Hello' } }],
  ...over,
});

test('builder: a good page renders a document with the title and the section', () => {
  const html = renderPage(page(), { description: 'A description', lang: 'en' });
  assert.match(html, /^<!doctype html>/);
  assert.match(html, /<title>Title<\/title>/);
  assert.match(html, /content="A description"/);
  assert.match(html, /<h1>Hello<\/h1>/);
  assert.match(html, /\/assets\/site\.css/);
});

test('builder: a missing route, title or section list fails with a named problem', () => {
  assert.deepEqual(validatePage({ title: 'T', sections: [] }), [
    'page.route is required',
    'page.sections is empty',
  ]);
  const problems = validatePage({ route: '/', sections: [] });
  assert.ok(problems.includes('page.title is required'));
});

test('builder: an unknown section type stops the build', () => {
  const bad = page({ sections: [{ type: 'nope' }] });
  assert.throws(() => renderPage(bad), (err) => {
    assert.ok(err instanceof BuildError);
    assert.match(err.message, /unknown type nope/);
    return true;
  });
});

test('builder: a route that walks out of the output is refused', () => {
  assert.deepEqual(validatePage(page({ route: '/../etc' })), [
    'page.route is not a safe path: /../etc',
  ]);
});

test('builder: route to file maps the root, a directory and a named file', () => {
  assert.equal(routeToFile('/'), 'index.html');
  assert.equal(routeToFile('/about'), 'about/index.html');
  assert.equal(routeToFile('/about/'), 'about/index.html');
  assert.equal(routeToFile('/feed.xml'), 'feed.xml');
});

test('builder: two pages on the same target stop the build', () => {
  const site = {
    pages: [page({ route: '/about' }), page({ route: '/about/' })],
  };
  assert.throws(() => buildSite(site), (err) => {
    assert.match(err.message, /two pages publish to about\/index\.html/);
    return true;
  });
});

test('builder: a site with no pages is refused', () => {
  assert.throws(() => buildSite({ pages: [] }), /site.pages must hold at least one page/);
});

test('builder: buildSite returns one file per page in route order', () => {
  const site = { pages: [page({ route: '/' }), page({ route: '/about', title: 'About' })] };
  const built = buildSite(site);
  assert.equal(built.pages, 2);
  assert.deepEqual(built.files.map((f) => f.path), ['index.html', 'about/index.html']);
  assert.match(built.files[1].body, /<title>About<\/title>/);
});

test('builder: the stylesheet is a string with a rule for every class the templates use', () => {
  const css = renderStylesheet();
  for (const cls of ['.r-hero', '.r-pricing', '.r-faq', '.r-footer']) {
    assert.ok(css.includes(cls), cls);
  }
});
