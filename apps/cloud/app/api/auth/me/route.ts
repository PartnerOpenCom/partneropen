import { NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { getDb } from '@/lib/db';
import { sessionCookieName, verifySessionCookie } from '@/lib/auth';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

export async function GET() {
  try {
    const cookie = (await cookies()).get(sessionCookieName)?.value;
    const sessionId = verifySessionCookie(cookie);
    if (!sessionId) {
      return NextResponse.json({ user: null });
    }
    const db = getDb();
    const [user] = await db`
      SELECT u.email, s.expires_at
      FROM sessions s JOIN users u ON u.id = s.user_id
      WHERE s.session_id = ${sessionId} AND s.expires_at > now()
    `;
    if (!user) {
      return NextResponse.json({ user: null });
    }
    return NextResponse.json({ user: { email: user.email } });
  } catch {
    return NextResponse.json({ user: null });
  }
}
