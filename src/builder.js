// The builder. It takes the page a member wrote as JSON, checks the shape,
// renders each section through its template and returns a document plus the
// list of files the publish step will push.

import path from 'node:path';
import { sections } from './sections/index.js';
import { esc } from './html.js';

export class BuildError extends Error {
  constructor(problems) {
    super(`build failed with ${problems.length} problem(s): ${problems.join('; ')}`);
    this.name = 'BuildError';
    this.problems = problems;
  }
}

const SAFE_ROUTE = /^\/[a-z0-9\-/]*$/;

export function validatePage(page) {
  const problems = [];
  if (!page || typeof page !== 'object') return ['page: an object is required'];
  if (!page.route) problems.push('page.route is required');
  else if (!SAFE_ROUTE.test(page.route)) problems.push(`page.route is not a safe path: ${page.route}`);
  if (!page.title) problems.push('page.title is required');
  if (!Array.isArray(page.sections)) {
    problems.push('page.sections must be an array');
    return problems;
  }
  if (page.sections.length === 0) problems.push('page.sections is empty');
  page.sections.forEach((s, i) => {
    if (!s || typeof s !== 'object') {
      problems.push(`section ${i}: an object is required`);
      return;
    }
    if (!s.type) problems.push(`section ${i}: type is required`);
    else if (!Object.hasOwn(sections, s.type)) problems.push(`section ${i}: unknown type ${s.type}`);
  });
  return problems;
}

export function renderSections(list) {
  return list
    .map((s) => {
      const template = sections[s.type];
      return template(s.props ?? {});
    })
    .join('\n    ');
}

export function renderPage(page, site = {}) {
  const problems = validatePage(page);
  if (problems.length > 0) throw new BuildError(problems);
  const lang = page.lang ?? site.lang ?? 'en';
  const description = page.description ?? site.description ?? '';
  const body = renderSections(page.sections);
  return `<!doctype html>
<html lang="${esc(lang)}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${esc(page.title)}</title>
    ${description ? `<meta name="description" content="${esc(description)}">` : ''}
    <link rel="stylesheet" href="/assets/site.css">
  </head>
  <body>
    ${body}
  </body>
</html>
`;
}

export function routeToFile(route) {
  if (route === '/' || route === '') return 'index.html';
  const clean = route.replace(/^\/+/, '').replace(/\/+$/, '');
  if (path.extname(clean)) return clean;
  return `${clean}/index.html`;
}

/**
 * Build every page of a site. Returns the files the publish step uploads,
 * each one an object with a path relative to the hosting root and its body.
 */
export function buildSite(site) {
  const problems = [];
  if (!site || typeof site !== 'object') throw new BuildError(['site: an object is required']);
  if (!Array.isArray(site.pages) || site.pages.length === 0) {
    throw new BuildError(['site.pages must hold at least one page']);
  }
  const seen = new Set();
  const files = [];
  site.pages.forEach((page, i) => {
    const pageProblems = validatePage(page).map((p) => `pages[${i}]: ${p}`);
    problems.push(...pageProblems);
    if (pageProblems.length > 0) return;
    const target = routeToFile(page.route);
    if (seen.has(target)) problems.push(`pages[${i}]: two pages publish to ${target}`);
    seen.add(target);
    files.push({ path: target, body: renderPage(page, site), type: 'text/html; charset=utf-8' });
  });
  if (problems.length > 0) throw new BuildError(problems);
  return { files, pages: site.pages.length };
}

export function renderStylesheet() {
  return `:root { color-scheme: light; }
* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, sans-serif; line-height: 1.55; color: #1c1c1a; }
.r-hero, .r-overview, .r-features, .r-pricing, .r-testimonials, .r-faq, .r-gallery, .r-blog, .r-callout, .r-contact, .r-about { padding: 2.5rem 1.25rem; max-width: 68rem; margin: 0 auto; }
.r-hero .eyebrow { text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.8rem; color: #880000; }
section, header, footer { padding: 2.5rem 1.25rem; max-width: 68rem; margin: 0 auto; }
.r-header { display: flex; gap: 1.5rem; align-items: center; }
.r-header nav { display: flex; gap: 1rem; flex-wrap: wrap; }
.brand { font-weight: 700; text-decoration: none; color: #880000; }
h1 { font-size: clamp(2rem, 5vw, 3rem); }
h2 { font-size: 1.5rem; color: #880000; }
.grid, .plans { display: grid; gap: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); }
.card, .plan, .quote { border: 1px solid #ddd; border-radius: 0.5rem; padding: 1.25rem; }
.plan.featured { border-color: #880000; }
.actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem; }
a.primary, button, .cta { background: #880000; color: #fff; padding: 0.7rem 1.2rem; border-radius: 0.35rem; text-decoration: none; border: 0; }
a.secondary { border: 1px solid #880000; color: #880000; padding: 0.7rem 1.2rem; border-radius: 0.35rem; text-decoration: none; }
img { max-width: 100%; height: auto; border-radius: 0.5rem; }
form { display: grid; gap: 0.9rem; max-width: 30rem; }
label { display: grid; gap: 0.35rem; }
input, textarea { padding: 0.6rem; border: 1px solid #ccc; border-radius: 0.3rem; font: inherit; }
details { border-bottom: 1px solid #e5e5e5; padding: 0.75rem 0; }
.r-footer { display: grid; gap: 0.75rem; border-top: 1px solid #e5e5e5; }
.legal, .copy { font-size: 0.85rem; color: #666; }
`;
}
