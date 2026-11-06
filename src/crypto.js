// Crypto handling for the hosting credential.
//
// The hosting panel gives a member an API key plus a secret. Both live in
// raips.config.json, which never enters version control. This module reads
// them, signs a request with HMAC-SHA256 over the method, path, timestamp and
// body digest, and returns the headers the hosting server verifies.

import { createHmac, createHash, timingSafeEqual } from 'node:crypto';

export const SIGNATURE_HEADER = 'x-raips-signature';
export const KEY_HEADER = 'x-raips-key';
export const TIMESTAMP_HEADER = 'x-raips-timestamp';

/**
 * Reduce a body to a stable hex digest. An empty body hashes the empty string.
 */
export function bodyDigest(body) {
  const text = body === undefined || body === null ? '' : String(body);
  return createHash('sha256').update(text, 'utf8').digest('hex');
}

/**
 * Build the canonical string the server reconstructs before it verifies.
 * One line per field, lowercase method, path with no query string.
 */
export function canonical({ method, path, timestamp, digest }) {
  if (!method) throw new Error('sign: method is required');
  if (!path) throw new Error('sign: path is required');
  if (!Number.isInteger(timestamp)) throw new Error('sign: timestamp is required');
  if (!digest) throw new Error('sign: digest is required');
  return [String(method).toUpperCase(), path, String(timestamp), digest].join('\n');
}

/**
 * Sign a request. Returns the three headers a hosting server checks.
 * The secret never leaves this function as a return value.
 */
export function signRequest({ secret, method, path, body, timestamp, keyId }) {
  if (typeof secret !== 'string' || secret.length === 0) {
    throw new Error('sign: secret is required');
  }
  const stamp = Number.isInteger(timestamp) ? timestamp : Math.floor(Date.now() / 1000);
  const digest = bodyDigest(body);
  const message = canonical({ method, path, timestamp: stamp, digest });
  const signature = createHmac('sha256', secret).update(message, 'utf8').digest('hex');
  return {
    [KEY_HEADER]: keyId,
    [TIMESTAMP_HEADER]: String(stamp),
    [SIGNATURE_HEADER]: signature,
    'content-sha256': digest,
  };
}

/**
 * Verify a signature the way a hosting server does. The test host and the
 * fake host both call this, so a signature the client builds is checked by
 * an implementation the client does not share code with.
 */
export function verifyRequest({ secret, method, path, body, timestamp, signature, now, window }) {
  const limit = Number.isInteger(window) ? window : 300;
  const current = Number.isInteger(now) ? now : Math.floor(Date.now() / 1000);
  if (!Number.isInteger(timestamp)) return false;
  if (Math.abs(current - timestamp) > limit) return false;
  const digest = bodyDigest(body);
  const message = canonical({ method, path, timestamp, digest });
  const expected = createHmac('sha256', secret).update(message, 'utf8').digest('hex');
  const a = Buffer.from(expected, 'hex');
  const b = Buffer.from(String(signature ?? ''), 'hex');
  if (a.length !== b.length) return false;
  return timingSafeEqual(a, b);
}

export function redact(value) {
  if (!value) return '';
  const text = String(value);
  if (text.length <= 8) return '*'.repeat(text.length);
  return text.slice(0, 4) + '*'.repeat(text.length - 8) + text.slice(-4);
}
