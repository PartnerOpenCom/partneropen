'use client';

import { useEffect, useState } from 'react';

export default function JoinForm() {
  const [email, setEmail] = useState('');
  const [status, setStatus] = useState<'idle' | 'sending' | 'sent'>('idle');
  const [error, setError] = useState('');
  const [user, setUser] = useState<{ email: string } | null>(null);

  useEffect(() => {
    fetch('/api/auth/me')
      .then((r) => r.json())
      .then((d) => setUser(d.user))
      .catch(() => {});
  }, []);

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault();
    setStatus('sending');
    setError('');
    try {
      const res = await fetch('/api/auth/request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data.error || 'Something went wrong');
        setStatus('idle');
        return;
      }
      setStatus('sent');
    } catch {
      setError('Network error');
      setStatus('idle');
    }
  }

  if (user) {
    return (
      <div className="join-logged-in">
        <p>
          You are signed in as <strong>{user.email}</strong>.
        </p>
        <form action="/api/auth/logout" method="post">
          <button className="button button-secondary" type="submit">
            Sign out
          </button>
        </form>
      </div>
    );
  }

  if (status === 'sent') {
    return (
      <p className="join-sent" role="status">
        If that address is valid, a sign-in link is on its way. It expires in 15 minutes and works
        once.
      </p>
    );
  }

  return (
    <form className="join-form" onSubmit={onSubmit}>
      <label htmlFor="join-email">Email</label>
      <input
        id="join-email"
        type="email"
        name="email"
        required
        autoComplete="email"
        placeholder="you@example.com"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        disabled={status === 'sending'}
      />
      {error ? (
        <p className="join-error" role="alert">
          {error}
        </p>
      ) : null}
      <button className="button" type="submit" disabled={status === 'sending'}>
        {status === 'sending' ? 'Sending…' : 'Send sign-in link'}
      </button>
    </form>
  );
}
