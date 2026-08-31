#!/usr/bin/env node

import { createHash, createHmac, randomBytes } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const DEFAULT_BASE = 'http://127.0.0.1:8080';
const DEFAULT_SPACE = 'reviews';
const SAMPLE_SNAPSHOT = path.join(path.dirname(fileURLToPath(import.meta.url)), 'sample-snapshot.json');
const DEFAULT_CLOUD_BASE = 'https://partneropen.com';

function usage() {
  return `PartnerOpen Connector sync reference client

Usage:
  node apps/cloud/scripts/sync-demo.mjs --base <url> --code <pairing-code> --space <slug> [options]

Required:
  --base <url>       WordPress site URL (for example https://site.example)
  --code <code>      One-time Connector pairing code
  --space <slug>     Space slug to publish

Options:
  --cloud-base <url> HTTPS PartnerOpen Cloud URL sent while pairing
                     (default: https://partneropen.com)
  --snapshot <file>  Snapshot JSON file (default: scripts/sample-snapshot.json)
  --secret <secret>  Optional signing secret override for this run
  --help             Show this help
`;
}

function parseArgs(argv) {
  const options = {
    base: undefined,
    code: undefined,
    space: DEFAULT_SPACE,
    snapshot: SAMPLE_SNAPSHOT,
    secret: undefined,
    cloudBase: DEFAULT_CLOUD_BASE,
  };
  const keys = {
    '--base': 'base',
    '--code': 'code',
    '--space': 'space',
    '--snapshot': 'snapshot',
    '--secret': 'secret',
    '--cloud-base': 'cloudBase',
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--help' || arg === '-h') {
      console.log(usage());
      process.exit(0);
    }
    if (!Object.hasOwn(keys, arg)) {
      throw new Error(`Unknown argument: ${arg}`);
    }
    const value = argv[index + 1];
    if (!value || value.startsWith('--')) {
      throw new Error(`${arg} requires a value`);
    }
    options[keys[arg]] = value;
    index += 1;
  }

  if (!options.base) throw new Error('--base is required');
  if (!options.code) throw new Error('--code is required');
  return options;
}

function normalizeBase(input) {
  const url = new URL(input);
  if (!['http:', 'https:'].includes(url.protocol)) {
    throw new Error('--base must use http or https');
  }
  url.hash = '';
  url.search = '';
  url.pathname = url.pathname.replace(/\/wp-json\/?$/, '').replace(/\/+$/, '');
  return url.toString().replace(/\/$/, '');
}

function canonicalString(method, routePath, timestamp, nonce, body) {
  const bodyHash = createHash('sha256').update(body, 'utf8').digest('hex');
  return [method.toUpperCase(), routePath, String(timestamp), nonce, bodyHash].join('\n');
}

function signature(method, routePath, timestamp, nonce, body, secret) {
  return createHmac('sha256', secret).update(canonicalString(method, routePath, timestamp, nonce, body), 'utf8').digest('hex');
}

async function readJson(file) {
  let source;
  try {
    source = await readFile(file, 'utf8');
  } catch (error) {
    throw new Error(`Could not read snapshot ${file}: ${error.message}`);
  }
  try {
    const snapshot = JSON.parse(source);
    if (!snapshot || typeof snapshot !== 'object' || Array.isArray(snapshot)) {
      throw new Error('snapshot must be a JSON object');
    }
    return snapshot;
  } catch (error) {
    throw new Error(`Could not parse snapshot ${file}: ${error.message}`);
  }
}

function printResponse(label, status, payload) {
  console.log(`${label}: HTTP ${status}`);
  console.log(JSON.stringify(payload, null, 2));
}

async function callConnector({ base, routePath, method, body = '', secret, siteId, scopes, label }) {
  const timestamp = Math.floor(Date.now() / 1000);
  const nonce = randomBytes(16).toString('hex');
  const headers = { accept: 'application/json' };
  if (body !== '') headers['content-type'] = 'application/json';
  if (secret) {
    headers['x-partneropen-site'] = siteId;
    headers['x-partneropen-timestamp'] = String(timestamp);
    headers['x-partneropen-nonce'] = nonce;
    headers['x-partneropen-signature'] = signature(method, routePath, timestamp, nonce, body, secret);
    headers['x-partneropen-scopes'] = scopes.join(',');
  }

  let response;
  try {
    response = await fetch(`${base}/wp-json${routePath}`, {
      method,
      headers,
      body: body === '' ? undefined : body,
    });
  } catch (error) {
    throw new Error(`${label} request failed: ${error.message}`);
  }

  const text = await response.text();
  let payload;
  try {
    payload = text === '' ? null : JSON.parse(text);
  } catch {
    payload = { raw: text };
  }
  printResponse(label, response.status, payload);
  if (!response.ok) {
    throw new Error(`${label} failed with HTTP ${response.status}`);
  }
  return payload;
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const base = normalizeBase(options.base);
  const pairBody = JSON.stringify({ code: options.code, cloud_base: options.cloudBase });
  const paired = await callConnector({
    base,
    routePath: '/partneropen/v1/pair',
    method: 'POST',
    body: pairBody,
    label: 'pair',
  });

  const secret = options.secret ?? paired?.secret;
  const siteId = paired?.site_id;
  if (typeof secret !== 'string' || secret === '') throw new Error('pair response did not include a secret');
  if (typeof siteId !== 'string' || siteId === '') throw new Error('pair response did not include site_id');

  const snapshot = await readJson(options.snapshot);
  const snapshotBody = JSON.stringify(snapshot);
  const space = encodeURIComponent(options.space);
  await callConnector({
    base,
    routePath: `/partneropen/v1/spaces/${space}/snapshot`,
    method: 'PUT',
    body: snapshotBody,
    secret,
    siteId,
    scopes: ['content_sync'],
    label: 'snapshot',
  });
  await callConnector({
    base,
    routePath: '/partneropen/v1/status',
    method: 'GET',
    secret,
    siteId,
    scopes: ['cloud_connection'],
    label: 'status',
  });
  await callConnector({
    base,
    routePath: '/partneropen/v1/metrics',
    method: 'GET',
    secret,
    siteId,
    scopes: ['aggregate_metrics'],
    label: 'metrics',
  });
}

main().catch((error) => {
  console.error(`sync-demo: ${error.message}`);
  process.exitCode = 1;
});
