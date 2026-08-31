import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const source = await readFile(join(root, '..', 'app', 'page.tsx'), 'utf8');
const phoneSource = await readFile(join(root, '..', 'app', 'components', 'HeroPhonePreview.tsx'), 'utf8');
const legacyIdentifiers = [
  ['PARTNER', 'PAGE'].join(''),
];

test('landing copy makes the Host path clear', () => {
  assert.match(source, /PartnerOpen/);
  assert.match(source, /GPL WordPress Connector/);
  assert.match(source, /SET PARTNER SAFE SPACE VIA GPL WORDPRESS CONNECTOR/);
  assert.match(source, /Partner directory\. WordPress control/);
  assert.match(source, /best Creators|any Creator/);
  assert.match(source, /PRM \(Partner Relationship Management\) and CRM \(Client Relationship Management\)/);
  assert.match(source, /<a href="#how-it-works">How to connect<\/a>/);
  assert.match(source, /<a href="#where">Use cases<\/a>/);
  assert.match(source, /Get WordPress Connector/);
  assert.match(source, /ANY CREATOR/);
  assert.match(source, /M1 \/ AVAILABLE NOW/);
  assert.match(source, /SIMPLE INSTALL|NO EXTERNAL TOOLING/);
  assert.match(source, /FREE FOR THE USER/);
  assert.match(source, /ANY COMMERCIAL TERMS/);
  assert.match(source, /Get Hosts for your content at scale/);
  assert.match(source, /06 SCOPES|six scopes/);
  assert.match(source, /How to connect/);
  assert.match(source, /aggregate placement metrics|verified usage data/);
  assert.match(source, /PartnerOpen GUI/);
  assert.match(source, /MCP/);
  assert.match(source, /FULL ADMIN LOGS/);
  assert.match(source, /Creator[^.]{0,80}email|email[^.]{0,80}Creator/);
  assert.match(source, /external tooling or services/);
  assert.match(source, /separate database/);
  assert.match(source, /magic link/);
  assert.match(source, /MILESTONE 2 \/ BY REQUEST/);
  assert.match(source, /Connect with us to discuss/);
  assert.match(source, /AVAILABLE NOW/);
  assert.match(source, /Download Connector v0\.1\.0/);
  assert.match(source, /href="\/partneropen-connector-0\.1\.0\.zip"/);
  assert.match(source, /download>Download Connector v0\.1\.0/);
  assert.match(source, /id="trust-proof"/);
  assert.match(source, /WordPress owns durable state/);
  assert.match(source, /Pause or disconnect/);
  assert.match(source, /visitor dossiers/i);
  assert.match(source, /No visitor tracking provided/);
  assert.match(source, /kill switch/);
  assert.match(source, /control panel and dashboards/);
  assert.match(source, /thoughtful participant protection/);
  assert.match(source, /Host controls the site boundary, not Creator content/);
  assert.match(source, /Together: grow traffic and get mutual benefits from it/);
  assert.match(source, /partneropen-connector-0\.1\.0\.zip\.sha256/);
  assert.match(source, /\/api\/connector-manifest/);
  assert.doesNotMatch(source, /MPC/);
  assert.doesNotMatch(source, /PartnerOpen tracking/);
  assert.doesNotMatch(source, /MCP Connector.*planned|GUI.*unavailable|not available now/);
  assert.match(source, /Yes\. PartnerOpen GUI is available as a Creator-facing publication surface alongside the signed API and MCP Connector/);
  assert.match(source, /base Connector, signed API, MCP Connector and PartnerOpen GUI are available now/);
  assert.match(source, /Owner does not moderate, partially delete, hide or change Creator data/);
  assert.match(source, /Creator can withdraw their consent and disconnect their participation/);
  assert.match(source, /Keep visitors safe\. Just data\./);
  assert.match(source, /AGGREGATE_METRICS/);
  assert.match(source, /PartnerOpen: light tooling to the partnership networks world/);
  assert.match(source, /Does PartnerOpen host a partner editor today\?/);
  assert.match(source, /What can Milestone 2 \(M2\) include\?/);
  assert.match(source, /Is Milestone 2 available now\?/);
  assert.doesNotMatch(source, /Creator: Get professional access/);
  assert.doesNotMatch(source, /comparison-mark/);
  assert.doesNotMatch(source, /M1 live today/);
  assert.doesNotMatch(source, /Every logs saved/);
  assert.doesNotMatch(source, /PartnerPage(?!\.io)/);
  assert.doesNotMatch(source, /partnerpage(?!\.io)/);
  for (const identifier of legacyIdentifiers) {
    assert.equal(source.includes(identifier), false, `stale identifier: ${identifier}`);
  }
});

test('landing copy explains the existing PRM and CRM fit', () => {
  assert.match(source, /Connect your PRM and CRM — without replacing them/);
  assert.match(source, /lightweight PRM endpoint/);
  assert.match(source, /Designed to work alongside/);
  assert.match(source, /Partnero/);
  assert.match(source, /Introw/);
  assert.match(source, /PartnerStack/);
  assert.match(source, /Kiflo Partner Directory/);
  assert.match(source, /PartnerPage\.io/);
  assert.match(source, /https:\/\/www\.kiflo\.com\/product\/partner-directory/);
  assert.match(source, /https:\/\/partnerpage\.io/);
  assert.match(source, /target="_blank" rel="noopener noreferrer">Partnero/);
  assert.match(source, /target="_blank" rel="noopener noreferrer">Kiflo Partner Directory/);
  assert.doesNotMatch(source, /RPM endpoint/);
  assert.doesNotMatch(source, /Typical external partner page/);
});

test('phone preview presents safe setup and administrative audit state', () => {
  assert.match(phoneSource, /WordPress Connector/);
  assert.match(phoneSource, /SAFE/);
  assert.match(phoneSource, /EASY TO SET/);
  assert.match(phoneSource, /FULL ADMIN LOGS/);
  assert.match(phoneSource, /CONSENT GRANTED/);
  assert.match(phoneSource, /snapshot\.published/);
  assert.match(phoneSource, /global_pause\.changed/);
  assert.doesNotMatch(phoneSource, /visitor[- ]tracking|per-user|fingerprint/i);
});
