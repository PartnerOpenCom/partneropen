import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const repositoryRoot = join(root, '..', '..', '..');
const cloudFiles = [
  'apps/cloud/package.json',
  'apps/cloud/README.md',
  'apps/cloud/.env.example',
  'apps/cloud/app/layout.tsx',
  'apps/cloud/app/page.tsx',
  'apps/cloud/app/connector-manifest.ts',
  'apps/cloud/app/consent-scopes.ts',
  'apps/cloud/app/legal-content.ts',
  'apps/cloud/app/terms/page.tsx',
  'apps/cloud/app/privacy/page.tsx',
  'apps/cloud/app/components/HeroPhonePreview.tsx',
  'apps/cloud/app/components/M2RequestDialog.tsx',
  'apps/cloud/app/api/health/route.ts',
  'apps/cloud/lib/signature.ts',
  'apps/cloud/scripts/sync-demo.mjs',
  'apps/cloud/scripts/sample-snapshot.json',
  'package.json',
  'package-lock.json',
];
const sources = await Promise.all(cloudFiles.map((file) => readFile(join(repositoryRoot, file), 'utf8')));
const sourceByFile = Object.fromEntries(cloudFiles.map((file, index) => [file, sources[index]]));
const cloudPackage = sourceByFile['apps/cloud/package.json'];
const rootPackage = sourceByFile['package.json'];
const rootLock = sourceByFile['package-lock.json'];
const allSource = sources.join('\n');
const legacyIdentifiers = [
  ['PARTNER', 'PAGE'].join(''),
];

await test('Cloud package and service metadata use PartnerOpen identifiers', () => {
  assert.equal(JSON.parse(cloudPackage).name, '@partneropen/cloud');
  assert.equal(JSON.parse(rootPackage).name, 'partneropen');
  assert.equal(JSON.parse(rootLock).name, 'partneropen');
  assert.match(allSource, /partneropen-cloud/);
  assert.match(allSource, /partneropen-connector/);
});

await test('Cloud client and public surfaces use the PartnerOpen contract', () => {
  assert.match(allSource, /\/partneropen\/v1\/pair/);
  assert.match(allSource, /\/partneropen\/v1\/status/);
  assert.match(allSource, /\/partneropen\/v1\/spaces\/\$\{space\}\/snapshot/);
  assert.match(allSource, /\/partneropen\/v1\/metrics/);
  assert.match(allSource, /\/partneropen\/go\/\{link_id\}\/\{placement_id\}/);
  assert.match(allSource, /x-partneropen-site/);
  assert.match(allSource, /https:\/\/partneropen\.com/);
});

await test('Cloud source contains no legacy product identifiers', () => {
  assert.doesNotMatch(allSource, /PartnerPage(?!\.io)/);
  assert.doesNotMatch(allSource, /partnerpage(?!\.io)/);
  for (const identifier of legacyIdentifiers) {
    assert.equal(allSource.includes(identifier), false, `stale identifier: ${identifier}`);
  }
});
