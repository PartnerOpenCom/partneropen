import { NextResponse } from 'next/server';
import { getDb } from '@/lib/db';
import { newMagicToken, normalizeEmail } from '@/lib/auth';
import { sendMagicLinkEmail } from '@/lib/mailer';

export const runtime = 'nodejs';

const TOKEN_TTL_MINUTES = 15;
// Simple per-email in-memory throttle; sufficient for a single-region deployment.
const recent = new Map<string, number>();
const THROTTLE_MS = 60_000;

export async function POST(request: Request) {
  let email: string | null = null;
  try {
    ({ email } = await request.json());
  } catch {
    return NextResponse.json({ error: 'Invalid request' }, { status: 400 });
  }

  const normalized = normalizeEmail(email);
  if (!normalized) {
    return NextResponse.json({ error: 'Enter a valid email address' }, { status: 400 });
  }

  const now = Date.now();
  const last = recent.get(normalized) ?? 0;
  if (now - last < THROTTLE_MS) {
    return NextResponse.json({ ok: true });
  }
  recent.set(normalized, now);
  if (recent.size > 1000) {
    for (const [key, ts] of recent) if (now - ts > THROTTLE_MS) recent.delete(key);
  }

  let origin = process.env.APP_ORIGIN || new URL(request.url).origin;
  try {
    const db = getDb();
    const { token, tokenHash } = newMagicToken();

    await db`
      INSERT INTO users (email) VALUES (${normalized})
      ON CONFLICT (email) DO NOTHING
    `;
    const [user] = await db`SELECT id FROM users WHERE email = ${normalized}`;
    if (!user) {
      return NextResponse.json({ ok: true });
    }

    await db`
      INSERT INTO magic_tokens (token_hash, user_id, expires_at)
      VALUES (${tokenHash}, ${user.id}, now() + (${TOKEN_TTL_MINUTES} * interval '1 minute'))
    `;

    const link = `${origin}/api/auth/verify?token=${encodeURIComponent(token)}`;
    await sendMagicLinkEmail({ to: normalized, link });

    return NextResponse.json({ ok: true });
  } catch (error) {
    console.error('[auth/request]', error);
    return NextResponse.json({ error: 'Could not process the request' }, { status: 500 });
  }
}
