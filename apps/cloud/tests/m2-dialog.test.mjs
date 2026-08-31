import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const source = await readFile(join(root, '..', 'app', 'components', 'M2RequestDialog.tsx'), 'utf8');

test('M2 dialog explains a by-request network scope without fake submission', () => {
  assert.match(source, /role="dialog"/);
  assert.match(source, /aria-modal="true"/);
  assert.match(source, /aria-labelledby=\{titleId\}/);
  assert.match(source, /aria-describedby=\{descriptionId\}/);
  assert.match(source, /event\.key === 'Escape'/);
  assert.match(source, /event\.key !== 'Tab'/);
  assert.match(source, /triggerRef\.current\?\.focus\(\)/);
  assert.match(source, /MILESTONE 2 \/ BY REQUEST/);
  assert.match(source, /MCP Connector/);
  assert.match(source, /PartnerOpen GUI/);
  assert.match(source, /Available now:/);
  assert.match(source, /By request:/);
  assert.match(source, /Multi-Host network administration and audit timeline/);
  assert.match(source, /PartnerOpen does not process payments/);
  assert.doesNotMatch(source, /PartnerOpen GUI and administrative audit timeline/);
  assert.doesNotMatch(source, /Site-admin Creator invitations by email/);
  assert.doesNotMatch(source, /Scoped access and passwordless magic links/);
  assert.doesNotMatch(source, /MCP Connector.*planned/);
  assert.doesNotMatch(source, /not provisioned/);
  assert.doesNotMatch(source, /<form/);
  assert.doesNotMatch(source, /<fieldset disabled>/);
  assert.doesNotMatch(source, /no request is submitted or stored/);
});
