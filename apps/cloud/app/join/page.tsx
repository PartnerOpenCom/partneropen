import type { Metadata } from 'next';
import JoinForm from '../components/JoinForm';

export const metadata: Metadata = {
  title: 'Join PartnerOpen',
  description: 'Create your PartnerOpen account with a one-time sign-in link.',
};

export default function JoinPage() {
  return (
    <main className="join-page">
      <section className="join-card">
        <h1>Join PartnerOpen</h1>
        <p>
          Enter your email and we will send you a one-time sign-in link. No passwords, no tracking.
        </p>
        <JoinForm />
      </section>
    </main>
  );
}
