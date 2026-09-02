import type { MetadataRoute } from 'next';

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: 'PartnerOpen',
    short_name: 'PartnerOpen',
    description:
      'GPL WordPress Connector for Host-controlled partner Spaces: consent-first Creator collaboration, signed API, MCP and aggregate metrics.',
    start_url: '/',
    display: 'standalone',
    background_color: '#07090d',
    theme_color: '#07090d',
    icons: [
      {
        src: '/icon.svg',
        sizes: 'any',
        type: 'image/svg+xml',
      },
      {
        src: '/icon-512.png',
        sizes: '512x512',
        type: 'image/png',
      },
    ],
  };
}
