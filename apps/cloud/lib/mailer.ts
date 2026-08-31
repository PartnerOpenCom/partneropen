type MagicEmail = {
  to: string;
  link: string;
};

export async function sendMagicLinkEmail({ to, link }: MagicEmail): Promise<{ delivered: boolean; devLink?: string }> {
  const apiKey = process.env.RESEND_API_KEY;
  const from = process.env.EMAIL_FROM || 'PartnerOpen <onboarding@resend.dev>';
  const subject = 'Your PartnerOpen sign-in link';
  const text = [
    'Sign in to PartnerOpen by opening the link below.',
    'It expires in 15 minutes and can be used once.',
    '',
    link,
    '',
    'If you did not request this, you can ignore this email.',
  ].join('\n');

  if (!apiKey) {
    // No email provider configured (e.g. preview deploys): log instead of failing.
    console.log(`[auth] RESEND_API_KEY not set; magic link for ${to}: ${link}`);
    return { delivered: false, devLink: link };
  }

  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ from, to: [to], subject, text }),
  });
  if (!res.ok) {
    console.error('[auth] resend error', res.status, await res.text());
    throw new Error('Email delivery failed');
  }
  return { delivered: true };
}
