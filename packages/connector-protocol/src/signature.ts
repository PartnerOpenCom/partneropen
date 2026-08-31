import { createHash, createHmac } from 'node:crypto';

export function canonicalString(
  method: string,
  path: string,
  timestamp: number,
  nonce: string,
  body: string,
): string {
  const bodyHash = createHash('sha256').update(body, 'utf8').digest('hex');
  return [method.toUpperCase(), path, String(timestamp), nonce, bodyHash].join('\n');
}

export function sign(
  method: string,
  path: string,
  timestamp: number,
  nonce: string,
  body: string,
  secret: string,
): string {
  return createHmac('sha256', secret)
    .update(canonicalString(method, path, timestamp, nonce, body), 'utf8')
    .digest('hex');
}
