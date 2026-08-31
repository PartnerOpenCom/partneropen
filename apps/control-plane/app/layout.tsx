import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'PartnerOpen Control Plane',
  description: 'Server-side identity, tenancy and Connector orchestration for PartnerOpen.',
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
