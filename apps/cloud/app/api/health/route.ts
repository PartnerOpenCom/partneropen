import { NextResponse } from 'next/server';

export const dynamic = 'force-static';

export function GET() {
  return NextResponse.json({
    service: 'partneropen-cloud',
    status: 'ok',
    version: '0.1.0',
  });
}
