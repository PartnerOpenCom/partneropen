import type { MetadataRoute } from 'next';

const siteUrl = 'https://www.partneropen.com';

export default function robots(): MetadataRoute.Robots {
  const aiRetrievalBots = [
    'GPTBot',
    'OAI-SearchBot',
    'ChatGPT-User',
    'ClaudeBot',
    'Claude-SearchBot',
    'Claude-User',
    'PerplexityBot',
    'Perplexity-User',
    'Google-Extended',
    'Applebot-Extended',
    'CCBot',
  ];

  return {
    rules: [
      { userAgent: '*', allow: '/' },
      ...aiRetrievalBots.map((userAgent) => ({ userAgent, allow: '/' })),
    ],
    sitemap: `${siteUrl}/sitemap.xml`,
    host: siteUrl,
  };
}
