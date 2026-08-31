import { test } from 'node:test';
import assert from 'node:assert/strict';
import { consentScopes } from '../app/consent-scopes.ts';

const expectedIds = [
  'cloud_connection',
  'partner_email',
  'content_sync',
  'agent_pack',
  'aggregate_metrics',
  'affiliate_service',
];

await test('consent catalog contains the six Connector scopes in contract order', () => {
  assert.deepEqual(consentScopes.map((scope) => scope.id), expectedIds);
  assert.equal(consentScopes.length, 6);
});

await test('only cloud connection and content sync are required', () => {
  assert.deepEqual(
    consentScopes.filter((scope) => scope.required).map((scope) => scope.id),
    ['cloud_connection', 'content_sync'],
  );
});

await test('every scope has complete recipient-facing metadata', () => {
  for (const scope of consentScopes) {
    assert.ok(scope.label.trim());
    assert.ok(scope.purpose.trim());
    assert.ok(scope.fields.length > 0);
    assert.ok(scope.fields.every((field) => field.trim()));
    assert.ok(scope.recipient.trim());
    assert.ok(scope.retention.trim());
  }
});

await test('scope metadata names the PartnerOpen Cloud client', () => {
  const source = JSON.stringify(consentScopes);
  assert.match(source, /PartnerOpen Cloud/);
  for (const identifier of [
    ['Partner', 'Page'].join(''),
    ['partner', 'page'].join(''),
    ['PARTNER', 'PAGE'].join(''),
  ]) {
    assert.equal(source.includes(identifier), false, `stale identifier: ${identifier}`);
  }
});
