// The tests. Each file covers one part of the pipeline: the section
// templates, the builder, the crypto, the fake host on disk, the fake host
// over HTTP, and the publish step end to end.

import test from 'node:test';
import assert from 'node:assert/strict';
import { sections, sectionNames } from '../src/sections/index.js';
import { esc, safeUrl, attrs, lines } from '../src/html.js';

test('html: escaping covers the five characters that break markup', () => {
  assert.equal(esc('<a href="x">&\'</a>'), '&lt;a href=&quot;x&quot;&gt;&amp;&#39;&lt;/a&gt;');
  assert.equal(esc(null), '');
  assert.equal(esc(0), '0');
});

test('html: safeUrl keeps the schemes a page may link to and drops the rest', () => {
  assert.equal(safeUrl('https://example.com/a'), 'https://example.com/a');
  assert.equal(safeUrl('/about'), '/about');
  assert.equal(safeUrl('#top'), '#top');
  assert.equal(safeUrl('mailto:a@example.com'), 'mailto:a@example.com');
  assert.equal(safeUrl('javascript:alert(1)'), '');
  assert.equal(safeUrl('//evil.example/x'), '');
  assert.equal(safeUrl(''), '');
});

test('html: attrs drops empty values and keeps booleans', () => {
  assert.equal(attrs({ class: 'x', open: true, empty: '', zero: 0 }), ' class="x" open zero="0"');
});

test('html: lines accepts an array or a block of text', () => {
  assert.deepEqual(lines(['a', 'b']), ['a', 'b']);
  assert.deepEqual(lines('a\n\nb\n'), ['a', 'b']);
  assert.deepEqual(lines(undefined), []);
});

test('sections: thirteen templates, each one a function', () => {
  const names = sectionNames();
  assert.equal(names.length, 13);
  for (const name of names) assert.equal(typeof sections[name], 'function', name);
  assert.deepEqual(names, [
    'header',
    'hero',
    'overview',
    'features',
    'pricing',
    'testimonials',
    'faq',
    'gallery',
    'blog',
    'callout',
    'contact',
    'about',
    'footer',
  ]);
});

test('sections: hero renders the heading, the lead and both buttons', () => {
  const html = sections.hero({
    eyebrow: 'Eyebrow',
    heading: 'Heading <unsafe>',
    lead: 'Lead',
    buttons: [
      { label: 'One', href: 'https://example.com', style: 'primary' },
      { label: 'Two', href: '/two', style: 'secondary' },
    ],
  });
  assert.match(html, /<h1>Heading &lt;unsafe&gt;<\/h1>/);
  assert.match(html, /class="primary" href="https:\/\/example.com"/);
  assert.match(html, /class="secondary" href="\/two"/);
});

test('sections: a javascript href never reaches the output', () => {
  const html = sections.hero({
    heading: 'Safe',
    buttons: [{ label: 'Bad', href: 'javascript:alert(1)' }],
  });
  assert.doesNotMatch(html, /javascript:/);
});

test('sections: pricing marks the featured plan and skips a plan without a link', () => {
  const html = sections.pricing({
    heading: 'Plans',
    plans: [
      { name: 'A', price: 'Ten', featured: true, href: '/a' },
      { name: 'B', price: 'Twenty' },
    ],
  });
  assert.match(html, /class="plan featured"/);
  assert.equal((html.match(/class="cta"/g) ?? []).length, 1);
});

test('sections: faq opens only the entry marked open', () => {
  const html = sections.faq({
    items: [
      { q: 'One', a: 'Answer one' },
      { q: 'Two', a: 'Answer two', open: true },
    ],
  });
  assert.equal((html.match(/<details/g) ?? []).length, 2);
  assert.equal((html.match(/<details open>/g) ?? []).length, 1);
});

test('sections: contact form posts to a safe action', () => {
  const html = sections.contact({ heading: 'Write', action: 'javascript:alert(1)' });
  assert.match(html, /action="#"/);
  assert.match(html, /<textarea/);
});

test('sections: gallery drops an entry with no image and keeps an alt', () => {
  const html = sections.gallery({
    items: [
      { src: '/a.png', alt: 'A' },
      { caption: 'no source' },
    ],
  });
  assert.equal((html.match(/<img/g) ?? []).length, 1);
  assert.match(html, /alt="A"/);
});

test('sections: every template returns a fragment without a document wrapper', () => {
  for (const name of sectionNames()) {
    const html = sections[name]({});
    assert.equal(typeof html, 'string');
    assert.doesNotMatch(html, /<!doctype/i, name);
    assert.doesNotMatch(html, /<html/i, name);
  }
});
