import { consentScopes, type ConsentScopeId } from './consent-scopes';

export const legalLastUpdated = '2026-08-24';

export const neverCollected = [
  'cookies',
  'IP addresses',
  'user agents',
  'device fingerprints',
  'unique visitor identifiers',
  'visitor-level click events',
] as const;

export const cloudHostAllowlist = [
  'partneropen.com',
  'www.partneropen.com',
] as const;

export const ownerControls = [
  'Global Pause',
  'Withdraw consent & disconnect',
] as const;

export const scopeIds = consentScopes.reduce<Record<ConsentScopeId, ConsentScopeId>>(
  (ids, scope) => {
    ids[scope.id] = scope.id;
    return ids;
  },
  {} as Record<ConsentScopeId, ConsentScopeId>,
);

export const localFirstGuarantee =
  `Before ${scopeIds.cloud_connection} consent, the Connector makes zero outbound HTTP requests, including no request to Cloud. Each payload is independently gated by its named scope.`;

export const disconnectSemantics =
  'Disconnect is consent withdrawal and unpairing, never partner removal. It revokes the site secret, stops outbound calls, marks the connection disconnected, and retains local snapshots until explicit deletion. No Cloud-side snapshot, metrics or agent-file copy is stored in this M1; future service data is governed by that service\'s notice. Reconnection requires fresh consent and a fresh pairing code.';

export const retentionSummary =
  'The latest published snapshot remains on this site until it is replaced or explicitly deleted. Aggregate click totals are retained locally for 90 days, then pruned by a daily job. Uninstall deletes the plugin options, snapshots, click totals and plugin transients.';

export const agentPackWithdrawal =
  `${scopeIds.agent_pack} is enforced locally at publication time. When it is withdrawn, AGENTS.md, the lowercase agents.md alias, llms.txt, ai-context.json, manifest.json and sitemap.xml return 404 with no-store semantics.`;

export const resolverDisclosure =
  'Every external destination must use HTTPS and an allowed host. Public HTML uses a same-origin resolver, shows “Disclosure: This is an affiliate link.” and uses rel="sponsored nofollow noopener". The rendered page never exposes a raw external href.';

export const deferredEditorStatement =
  'The tenant-backed partner editor is a deferred milestone. Passwordless login, tenant and Space isolation, hosted page editing, email delivery, Cloud-side metrics storage, billing and service/network adapters are not promises of this M1 site.';

export const ownerResponsibility =
  'The site owner is responsible for choosing lawful destinations, maintaining an accurate disclosure, publishing any additional notice required for the site audience and reviewing the configured consent scopes.';

export function formatScopeFields(fields: readonly string[]): string {
  return fields.join('; ');
}
