import { test } from 'node:test';
import assert from 'node:assert/strict';
import { loadConfig } from '../lib/config.ts';

test('production configuration fails closed when DATABASE_URL is missing', () => {
  assert.throws(
    () => loadConfig({ NODE_ENV: 'production' }),
    /DATABASE_URL is required in production/,
  );
});

test('development configuration keeps optional integrations explicit', () => {
  const config = loadConfig({ NODE_ENV: 'development' });

  assert.equal(config.nodeEnv, 'development');
  assert.equal(config.databaseUrl, undefined);
  assert.equal(config.email.provider, 'resend');
  assert.equal(config.appBaseUrl, 'http://localhost:3001');
});

test('invalid encryption key material is rejected before use', () => {
  assert.throws(
    () => loadConfig({
      NODE_ENV: 'development',
      ENCRYPTION_KEY: 'not-hex',
    }),
    /ENCRYPTION_KEY must be 32-byte hex/,
  );
});
