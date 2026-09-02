import type { Metadata } from 'next';
import './globals.css';

const siteUrl = 'https://www.partneropen.com';
const siteName = 'PartnerOpen';
const title = 'PartnerOpen — GPL WordPress Connector for Partner Spaces & PRM';
const description =
  'PartnerOpen is a GPL WordPress Connector for Host-controlled partner Spaces: consent-first Creator collaboration, a signed API, MCP Connector, PartnerOpen GUI and aggregate placement metrics. No visitor tracking, no external services on the Host side.';

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: title,
    template: '%s · PartnerOpen',
  },
  description,
  applicationName: siteName,
  category: 'technology',
  keywords: [
    'WordPress partner plugin',
    'partner directory WordPress',
    'PRM WordPress',
    'partner relationship management',
    'partner space',
    'creator collaboration',
    'GPL connector',
    'consent-first publishing',
    'MCP connector',
    'CRM integration WordPress',
    'partner page WordPress',
  ],
  authors: [{ name: siteName, url: siteUrl }],
  creator: siteName,
  publisher: siteName,
  alternates: {
    canonical: '/',
  },
  openGraph: {
    type: 'website',
    url: siteUrl,
    siteName,
    title,
    description,
    locale: 'en_US',
  },
  twitter: {
    card: 'summary_large_image',
    title,
    description,
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-image-preview': 'large',
      'max-snippet': -1,
      'max-video-preview': -1,
    },
  },
  appleWebApp: {
    capable: true,
    title: siteName,
  },
  formatDetection: {
    telephone: false,
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <head>
        <link rel="describedby" href="/llms.txt" />
      </head>
      <body>{children}</body>
    </html>
  );
}
