// HTML escaping and the small helpers every template shares.

const ESCAPES = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

export function esc(value) {
  if (value === undefined || value === null) return '';
  return String(value).replace(/[&<>"']/g, (ch) => ESCAPES[ch]);
}

/**
 * A link is dropped unless it points somewhere a page may point: http, https,
 * mailto, tel, or an anchor inside the page. A javascript: value never reaches
 * the output.
 */
export function safeUrl(value) {
  const text = String(value ?? '').trim();
  if (text === '') return '';
  if (text.startsWith('#')) return esc(text);
  if (/^(https?:|mailto:|tel:)/i.test(text)) return esc(text);
  if (text.startsWith('/') && !text.startsWith('//')) return esc(text);
  return '';
}

export function attrs(map) {
  return Object.entries(map)
    .filter(([, v]) => v !== undefined && v !== null && v !== false && v !== '')
    .map(([k, v]) => (v === true ? ` ${k}` : ` ${k}="${esc(v)}"`))
    .join('');
}

export function lines(value) {
  if (Array.isArray(value)) return value;
  if (typeof value === 'string') return value.split(/\r?\n/).filter((s) => s.trim() !== '');
  return [];
}
