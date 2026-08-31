import { createHash, createHmac, randomBytes, timingSafeEqual } from 'node:crypto';

const SESSION_COOKIE = 'po_session';
const SESSION_TTL_SECONDS = 60 * 60 * 24 * 30; // 30 days

function sha256(value: string) {
  return createHash('sha256').update(value).digest('hex');
}

function sessionSecret() {
  const secret = process.env.AUTH_SECRET;
  if (!secret || secret.length < 32) {
    throw new Error('AUTH_SECRET must be set (32+ characters)');
  }
  return secret;
}

export function newMagicToken() {
  const token = randomBytes(32).toString('base64url');
  return { token, tokenHash: sha256(token) };
}

export function hashToken(token: string) {
  return sha256(token);
}

export function newSessionId() {
  return randomBytes(32).toString('base64url');
}

export function signSession(sessionId: string) {
  const mac = createHmac('sha256', sessionSecret()).update(sessionId).digest('base64url');
  return `${sessionId}.${mac}`;
}

export function verifySessionCookie(cookie: string | undefined): string | null {
  if (!cookie) return null;
  const dot = cookie.lastIndexOf('.');
  if (dot <= 0) return null;
  const sessionId = cookie.slice(0, dot);
  const mac = cookie.slice(dot + 1);
  const expected = createHmac('sha256', sessionSecret()).update(sessionId).digest('base64url');
  const a = Buffer.from(mac);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !timingSafeEqual(a, b)) return null;
  return sessionId;
}

export const sessionCookieName = SESSION_COOKIE;
export const sessionCookieOptions = {
  httpOnly: true,
  sameSite: 'lax' as const,
  secure: process.env.NODE_ENV === 'production',
  path: '/',
  maxAge: SESSION_TTL_SECONDS,
};

export function normalizeEmail(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  const email = raw.trim().toLowerCase();
  if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return null;
  return email;
}

export { SESSION_TTL_SECONDS };
