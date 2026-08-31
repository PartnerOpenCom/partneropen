import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { getDb } from '@/lib/db';
import { sessionCookieName, verifySessionCookie } from '@/lib/auth';

export const runtime = 'nodejs';

export async function POST(request: Request) {
  const store = await cookies();
  const sessionId = verifySessionCookie(store.get(sessionCookieName)?.value);
  store.delete(sessionCookieName);
  if (sessionId) {
    try {
      const db = getDb();
      await db`DELETE FROM sessions WHERE session_id = ${sessionId}`;
    } catch (error) {
      console.error('[auth/logout]', error);
    }
  }
  return NextResponse.redirect(new URL('/', request.url));
}
