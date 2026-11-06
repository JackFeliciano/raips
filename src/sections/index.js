// The section templates. Each one takes the props a member wrote in the site
// JSON and returns an HTML fragment. A section never reads the config, never
// touches the network and never writes a file, which keeps the render pure and
// the tests cheap.

import { esc, safeUrl, attrs, lines } from '../html.js';

export function header(props = {}) {
  const nav = lines(props.links)
    .map((l) => {
      const href = safeUrl(l.href);
      if (!href) return '';
      return `<a href="${href}">${esc(l.label)}</a>`;
    })
    .join('\n        ');
  const cta = safeUrl(props.cta?.href)
    ? `<a class="cta" href="${safeUrl(props.cta.href)}">${esc(props.cta.label)}</a>`
    : '';
  return `<header class="r-header">
      <a class="brand" href="/">${esc(props.brand)}</a>
      <nav>
        ${nav}
      </nav>
      ${cta}
    </header>`;
}

export function hero(props = {}) {
  const buttons = lines(props.buttons)
    .map((b) => {
      const href = safeUrl(b.href);
      if (!href) return '';
      const style = b.style === 'secondary' ? ' class="secondary"' : ' class="primary"';
      return `<a${style} href="${href}">${esc(b.label)}</a>`;
    })
    .filter(Boolean)
    .join('\n        ');
  const image = safeUrl(props.image);
  return `<section class="r-hero">
      ${props.eyebrow ? `<p class="eyebrow">${esc(props.eyebrow)}</p>` : ''}
      <h1>${esc(props.heading)}</h1>
      ${props.lead ? `<p class="lead">${esc(props.lead)}</p>` : ''}
      <div class="actions">
        ${buttons}
      </div>
      ${image ? `<img src="${image}" alt="${esc(props.imageAlt ?? '')}" loading="lazy">` : ''}
    </section>`;
}

export function overview(props = {}) {
  const items = lines(props.items)
    .map((i) => `<p>${esc(i)}</p>`)
    .join('\n      ');
  return `<section class="r-overview">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      ${items}
    </section>`;
}

export function features(props = {}) {
  const cards = lines(props.items)
    .map(
      (i) => `<article${attrs({ class: 'card' })}>
        <h3>${esc(i.title)}</h3>
        <p>${esc(i.text)}</p>
      </article>`,
    )
    .join('\n      ');
  return `<section class="r-features">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      <div class="grid">
      ${cards}
      </div>
    </section>`;
}

export function pricing(props = {}) {
  const plans = lines(props.plans)
    .map(
      (p) => `<article class="plan${p.featured ? ' featured' : ''}">
        <h3>${esc(p.name)}</h3>
        <p class="price">${esc(p.price)}</p>
        ${p.note ? `<p class="note">${esc(p.note)}</p>` : ''}
        ${
          safeUrl(p.href)
            ? `<a class="cta" href="${safeUrl(p.href)}">${esc(p.cta ?? 'Choose')}</a>`
            : ''
        }
      </article>`,
    )
    .join('\n      ');
  return `<section class="r-pricing">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      <div class="plans">
      ${plans}
      </div>
    </section>`;
}

export function testimonials(props = {}) {
  const quotes = lines(props.items)
    .map(
      (q) => `<figure class="quote">
        <blockquote>${esc(q.text)}</blockquote>
        <figcaption>${esc(q.author)}${q.role ? `, ${esc(q.role)}` : ''}</figcaption>
      </figure>`,
    )
    .join('\n      ');
  return `<section class="r-testimonials">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      ${quotes}
    </section>`;
}

export function faq(props = {}) {
  const items = lines(props.items)
    .map(
      (q) => `<details${attrs({ open: q.open === true })}>
        <summary>${esc(q.q)}</summary>
        <div>${esc(q.a)}</div>
      </details>`,
    )
    .join('\n      ');
  return `<section class="r-faq">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      ${items}
    </section>`;
}

export function gallery(props = {}) {
  const shots = lines(props.items)
    .map((s) => {
      const href = safeUrl(s.src);
      if (!href) return '';
      return `<figure><img src="${href}" alt="${esc(s.alt ?? '')}" loading="lazy">${
        s.caption ? `<figcaption>${esc(s.caption)}</figcaption>` : ''
      }</figure>`;
    })
    .filter(Boolean)
    .join('\n      ');
  return `<section class="r-gallery">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      ${shots}
    </section>`;
}

export function blog(props = {}) {
  const posts = lines(props.items)
    .map((p) => {
      const href = safeUrl(p.href);
      const title = href
        ? `<a href="${href}">${esc(p.title)}</a>`
        : `<span>${esc(p.title)}</span>`;
      return `<article class="post">
        <h3>${title}</h3>
        ${p.date ? `<p class="date">${esc(p.date)}</p>` : ''}
        ${p.summary ? `<p>${esc(p.summary)}</p>` : ''}
      </article>`;
    })
    .join('\n      ');
  return `<section class="r-blog">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      ${posts}
    </section>`;
}

export function callout(props = {}) {
  const href = safeUrl(props.href);
  return `<section class="r-callout">
      <h2>${esc(props.heading)}</h2>
      ${props.text ? `<p>${esc(props.text)}</p>` : ''}
      ${href ? `<a class="cta" href="${href}">${esc(props.cta ?? 'Get started')}</a>` : ''}
    </section>`;
}

export function contact(props = {}) {
  const action = safeUrl(props.action) || '#';
  return `<section class="r-contact">
      ${props.heading ? `<h2>${esc(props.heading)}</h2>` : ''}
      <form method="post" action="${action}">
        <label>Name<input name="name" required></label>
        <label>Email<input name="email" type="email" required></label>
        <label>Message<textarea name="message" rows="5" required></textarea></label>
        <button type="submit">${esc(props.cta ?? 'Send')}</button>
      </form>
    </section>`;
}

export function about(props = {}) {
  const image = safeUrl(props.image);
  const body = lines(props.paragraphs)
    .map((p) => `<p>${esc(p)}</p>`)
    .join('\n      ');
  return `<section class="r-about">
      ${image ? `<img src="${image}" alt="${esc(props.imageAlt ?? '')}" loading="lazy">` : ''}
      <div>
        <h2>${esc(props.heading)}</h2>
        ${body}
      </div>
    </section>`;
}

export function footer(props = {}) {
  const nav = lines(props.links)
    .map((l) => {
      const href = safeUrl(l.href);
      if (!href) return '';
      return `<a href="${href}">${esc(l.label)}</a>`;
    })
    .filter(Boolean)
    .join('\n        ');
  const social = lines(props.social)
    .map((s) => {
      const href = safeUrl(s.href);
      if (!href) return '';
      return `<a href="${href}" rel="noopener">${esc(s.label)}</a>`;
    })
    .filter(Boolean)
    .join('\n        ');
  return `<footer class="r-footer">
      <a class="brand" href="/">${esc(props.brand)}</a>
      <nav>
        ${nav}
      </nav>
      <nav class="social">
        ${social}
      </nav>
      ${props.legal ? `<p class="legal">${esc(props.legal)}</p>` : ''}
      <p class="copy">${esc(props.copyright)}</p>
    </footer>`;
}

export const sections = Object.freeze({
  header,
  hero,
  overview,
  features,
  pricing,
  testimonials,
  faq,
  gallery,
  blog,
  callout,
  contact,
  about,
  footer,
});

export function sectionNames() {
  return Object.keys(sections);
}
