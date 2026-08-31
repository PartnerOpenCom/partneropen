import { consentScopes } from './consent-scopes';

export const connectorManifest = {
  name: 'partneropen-connector',
  version: '0.1.0',
  license: 'GPL-2.0-or-later',
  requires: {
    wordpress: '6.5',
    php: '8.1',
  },
  public_url_surface: [
    '/{prefix}/{space}/',
    '/{prefix}/AGENTS.md',
    '/{prefix}/agents.md',
    '/{prefix}/llms.txt',
    '/{prefix}/ai-context.json',
    '/{prefix}/manifest.json',
    '/{prefix}/sitemap.xml',
    '/partneropen/go/{link_id}/{placement_id}',
  ],
  rest_routes: [
    '/pair',
    '/status',
    '/spaces',
    '/spaces/{space}/snapshot',
    '/spaces/{space}/suspend',
    '/spaces/{space}/resume',
    '/metrics',
    '/disconnect',
  ],
  consent_scopes: consentScopes,
  capabilities: [
    'Local-first WordPress Connector with durable state in WordPress options',
    'Delegated Spaces with published snapshots and a five-Space limit',
    'Global Pause publication overlay and consent withdrawal disconnect',
    'Same-origin link resolver with visible disclosure and sponsored nofollow noopener attributes',
    'Redacted agent-context files: AGENTS.md, llms.txt, ai-context.json, manifest.json and sitemap.xml',
    'Privacy-preserving daily aggregate click counters with 90-day retention',
  ],
  state: 'connector-owns-durable-state',
} as const;

export type ConnectorManifest = typeof connectorManifest;
