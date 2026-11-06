// Crypto tests. The signature is deterministic, covers the body and stops
// when the clock moves or the body changes.

import test from 'node:test';
import assert from 'node:assert/strict';
import {
  signRequest,
  verifyRequest,
  bodyDigest,
  canonical,
  redact,
  SIGNATURE_HEADER,
  KEY_HEADER,
  TIMESTAMP_HEADER,
} from '../src/crypto.js';

const secret = 'test-secret-value';
const keyId = 'client-01';
const now = 1_800_000_000;

test('crypto: the digest of an empty body is the digest of the empty string', () => {
  assert.equal(
    bodyDigest(''),
    'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  );
  assert.equal(bodyDigest(undefined), bodyDigest(''));
  assert.equal(bodyDigest('abc'), bodyDigest('abc'));
  assert.notEqual(bodyDigest('abc'), bodyDigest('abd'));
});

test('crypto: the canonical string is one line per field', () => {
  assert.equal(canonical({ method: 'put', path: '/a', timestamp: 5, digest: 'd' }), 'PUT\n/a\n5\nd');
  assert.throws(() => canonical({ method: 'PUT', path: '/a', timestamp: 5 }), /digest is required/);
});

test('crypto: the same input signs to the same headers twice', () => {
  const a = signRequest({ secret, method: 'PUT', path: '/a.html', body: 'x', timestamp: now, keyId });
  const b = signRequest({ secret, method: 'PUT', path: '/a.html', body: 'x', timestamp: now, keyId });
  assert.deepEqual(a, b);
  assert.equal(a[KEY_HEADER], keyId);
  assert.equal(a[TIMESTAMP_HEADER], String(now));
  assert.match(a[SIGNATURE_HEADER], /^[0-9a-f]{64}$/);
});

test('crypto: a changed body, path or secret fails verification', () => {
  const headers = signRequest({ secret, method: 'PUT', path: '/a.html', body: 'x', timestamp: now, keyId });
  const base = {
    secret,
    method: 'PUT',
    path: '/a.html',
    timestamp: now,
    signature: headers[SIGNATURE_HEADER],
    now,
  };
  assert.equal(verifyRequest({ ...base, body: 'x' }), true);
  assert.equal(verifyRequest({ ...base, body: 'y' }), false);
  assert.equal(verifyRequest({ ...base, body: 'x', path: '/b.html' }), false);
  assert.equal(verifyRequest({ ...base, body: 'x', secret: 'other' }), false);
});

test('crypto: a stale timestamp fails and the window can be widened', () => {
  const headers = signRequest({ secret, method: 'POST', path: '/m', body: '{}', timestamp: now, keyId });
  const args = {
    secret,
    method: 'POST',
    path: '/m',
    body: '{}',
    timestamp: now,
    signature: headers[SIGNATURE_HEADER],
  };
  assert.equal(verifyRequest({ ...args, now: now + 100 }), true);
  assert.equal(verifyRequest({ ...args, now: now + 400 }), false);
  assert.equal(verifyRequest({ ...args, now: now + 400, window: 600 }), true);
});

test('crypto: a signature of the wrong length returns false without throwing', () => {
  assert.equal(
    verifyRequest({ secret, method: 'PUT', path: '/a', body: '', timestamp: now, signature: 'ab', now }),
    false,
  );
});

test('crypto: redact keeps the ends and hides the middle', () => {
  assert.equal(redact('abcdefghijkl'), 'abcd****ijkl');
  assert.equal(redact('short'), '*****');
  assert.equal(redact(''), '');
});
