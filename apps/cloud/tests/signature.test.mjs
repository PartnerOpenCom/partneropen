import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash, createHmac } from 'node:crypto';
import { canonicalString, sign } from '../lib/signature.ts';

const method = 'put';
const routePath = '/partneropen/v1/spaces/reviews/snapshot';
const timestamp = 1700000000;
const nonce = 'nonce-123';
const body = '{"hello":"world"}';
const secret = 'secret';
const bodyHash = createHash('sha256').update(body, 'utf8').digest('hex');
const expectedCanonical = `PUT\n${routePath}\n${timestamp}\n${nonce}\n${bodyHash}`;
const expectedSignature = '70e1d410f07e16a0d4936f0917203b55bf117679dc14ea2f32100d0d7fb886ba';

await test('canonical string uses the Connector field order and literal newlines', () => {
  assert.equal(canonicalString(method, routePath, timestamp, nonce, body), expectedCanonical);
  assert.equal(expectedCanonical.split('\n').length, 5);
  assert.match(expectedCanonical, /^PUT\n\/partneropen\/v1\/spaces\/reviews\/snapshot\n1700000000\nnonce-123\n[a-f0-9]{64}$/);
});

await test('sign matches the fixed HMAC-SHA256 vector and manual implementation', () => {
  const manual = createHmac('sha256', secret).update(expectedCanonical, 'utf8').digest('hex');
  assert.equal(manual, expectedSignature);
  assert.equal(sign(method, routePath, timestamp, nonce, body, secret), expectedSignature);
});

await test('changing any signed input changes the signature', () => {
  const inputs = [
    ['post', routePath, timestamp, nonce, body],
    [method, '/partneropen/v1/spaces/other/snapshot', timestamp, nonce, body],
    [method, routePath, timestamp + 1, nonce, body],
    [method, routePath, timestamp, 'nonce-other', body],
    [method, routePath, timestamp, nonce, '{"hello":"tampered"}'],
  ];
  for (const [changedMethod, changedPath, changedTimestamp, changedNonce, changedBody] of inputs) {
    assert.notEqual(sign(changedMethod, changedPath, changedTimestamp, changedNonce, changedBody, secret), expectedSignature);
  }
});
