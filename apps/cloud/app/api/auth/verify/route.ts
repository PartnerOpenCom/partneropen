import { NextResponse } from 'next/server';
import { getDb } from '@/lib/db';
import { hashToken, newSessionId, sessionCookieName, sessionCookieOptions, signSession } from '@/lib/auth';

export const runtime = 'nodejs';

export async function GET(request: Request) {
  const token = new URL(request.url).searchParams.get('token');
  if (!token) {
    return NextResponse.redirect(new URL('/join?error=missing_token', request.url));
  }

  try {
    const db = getDb();
    const tokenHash = hashToken(token);

    const [row] = await db`
      UPDATE magic_tokens
      SET used_at = now()
      WHERE token_hash = ${tokenHash}
        AND used_at IS NULL
        AND expires_at > now()
      RETURNING user_id
    `;
    if (!row) {
      return NextResponse.redirect(new URL('/join?error=invalid_token', request.url));
    }

    const sessionId = newSessionId();
    await db`
      INSERT INTO sessions (session_id, user_id, expires_at)
      VALUES (${sessionId}, ${row.user_id}, now() + interval '30 days')
    `;
    await db`UPDATE users SET last_login_at = now() WHERE id = ${row.user_id}`;

    const response = NextResponse.redirect(new URL('/join?welcome=1', request.url));
    response.cookies.set(sessionCookieName, signSession(sessionId), sessionCookieOptions);
    return response;
  } catch (error) {
    console.error('[auth/verify]', error);
    return NextResponse.redirect(new URL('/join?error=server', request.url));
  }
}
