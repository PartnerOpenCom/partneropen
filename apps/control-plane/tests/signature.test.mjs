import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { canonicalString, sign } from '../lib/connector/signature.ts';

const method = 'put';
const routePath = '/partneropen/v1/spaces/reviews/snapshot';
const timestamp = 1700000000;
const nonce = 'nonce-123';
const body = '{"space":"reviews"}';
const secret = 'test-secret';

const expectedCanonical = [
  'PUT',
  routePath,
  String(timestamp),
  nonce,
  createHash('sha256').update(body, 'utf8').digest('hex'),
].join('\n');

test('control plane uses the Connector canonical field order', () => {
  assert.equal(canonicalString(method, routePath, timestamp, nonce, body), expectedCanonical);
});

test('control plane produces lowercase HMAC-SHA256 signatures', () => {
  assert.match(sign(method, routePath, timestamp, nonce, body, secret), /^[a-f0-9]{64}$/);
});
