import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { consentScopes } from '../app/consent-scopes.ts';

const pagePaths = [
  new URL('../app/terms/page.tsx', import.meta.url),
  new URL('../app/privacy/page.tsx', import.meta.url),
];
const legalContentPath = new URL('../app/legal-content.ts', import.meta.url);
const [pageSources, legalContentSource] = await Promise.all([
  Promise.all(pagePaths.map((path) => readFile(path, 'utf8'))),
  readFile(legalContentPath, 'utf8'),
]);

const neverCollected = [
  'cookies',
  'IP addresses',
  'user agents',
  'device fingerprints',
  'unique visitor identifiers',
  'visitor-level click events',
];
const cloudHostAllowlist = [
  'partneropen.com',
  'www.partneropen.com',
];
const legacyIdentifiers = [
  ['Partner', 'Page'].join(''),
  ['partner', 'page'].join(''),
  ['PARTNER', 'PAGE'].join(''),
];
const disconnectSemantics = 'consent withdrawal and unpairing, never partner removal';
const localFirstGuarantee = 'zero outbound HTTP';
const retentionRequirements = ['latest published snapshot', '90 days', 'daily job', 'uninstall'];
const deferredEditorStatement = 'tenant-backed partner editor';

await test('terms and privacy routes export static default page components', () => {
  for (const source of pageSources) {
    assert.match(source, /export const dynamic = ['"]force-static['"]/);
    assert.match(source, /export default function \w+Page\(\)/);
    assert.match(source, /consentScopes\.map\(\(scope\) =>/);
  }
});

await test('both legal routes use every catalog scope and required privacy disclosures', () => {
  assert.equal(consentScopes.length, 6);
  for (const scope of consentScopes) {
    assert.ok(scope.id);
    assert.ok(scope.purpose);
    assert.ok(scope.fields.length > 0);
    assert.ok(scope.recipient);
    assert.ok(scope.retention);
  }

  const scopeCatalogContent = consentScopes.flatMap((scope) => [
    scope.id,
    scope.purpose,
    ...scope.fields,
    scope.recipient,
    scope.retention,
  ]);
  const renderedContent = [...pageSources, legalContentSource, ...scopeCatalogContent].join('\n');
  const renderedContentLower = renderedContent.toLowerCase();
  assert.match(renderedContent, /PartnerOpen Cloud/);
  assert.match(renderedContent, /partneropen\/v1/);

  for (const scope of consentScopes) {
    assert.ok(renderedContent.includes(scope.id));
  }
  for (const item of neverCollected) {
    assert.ok(renderedContent.includes(item));
  }
  for (const host of cloudHostAllowlist) {
    assert.ok(renderedContent.includes(host));
  }
  assert.match(renderedContent, /consent withdrawal and unpairing/);
  assert.match(renderedContent, /never partner removal/);
  assert.match(renderedContent, new RegExp(localFirstGuarantee));
  for (const requirement of retentionRequirements) {
    assert.ok(renderedContentLower.includes(requirement.toLowerCase()));
  }
  assert.match(renderedContent, new RegExp(deferredEditorStatement));
  for (const identifier of legacyIdentifiers) {
    assert.equal(renderedContent.includes(identifier), false, `stale identifier: ${identifier}`);
  }
});
