import { NextResponse } from 'next/server';
import { consentScopes } from '../../consent-scopes';

export const dynamic = 'force-static';

export function GET() {
  return NextResponse.json({ scopes: consentScopes });
}
