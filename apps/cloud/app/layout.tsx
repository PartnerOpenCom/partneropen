import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'PartnerOpen Cloud',
  description: 'A stateless public site for PartnerOpen Connector and delegated WordPress Space publishing.',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
