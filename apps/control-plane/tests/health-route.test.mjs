import { test } from 'node:test';
import assert from 'node:assert/strict';
import { GET } from '../app/api/health/route.ts';

test('health route is public and does not require external services', async () => {
  const response = GET();

  assert.equal(response.status, 200);
  assert.deepEqual(await response.json(), {
    status: 'ok',
    service: 'partneropen-control-plane',
  });
  assert.equal(response.headers.get('cache-control'), 'no-store');
});
