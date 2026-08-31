# PartnerOpen privacy notice

_Last updated: 2026-08-24_

Canonical notice: https://partneropen.com/privacy

This notice describes the **PartnerOpen Connector** and its optional connection to the stateless **PartnerOpen Cloud** public site. In M1, the Connector is the durable system of record: connection state, consent records, delegated Space metadata, published snapshots, and aggregate daily click counters remain in the site's WordPress database. PartnerOpen Cloud has no tenant database, hosted editor, pairing store, publish store, metrics-ingest store, or Connector snapshot copy in this milestone.

The site owner chooses a URL prefix and, when needed, records a partner email during setup. The owner reviews and grants each scope independently. `cloud_connection` is required for pairing and `content_sync` is required for snapshot publication. `partner_email` is optional in the stored data structure and is required only when an invitation is sent. The Connector sends no email itself. The exact scope metadata is:

| ID | Required | Label | Purpose | Fields | Recipient | Retention |
|---|---:|---|---|---|---|---|
| `cloud_connection` | true | Cloud connection | Pair this site with the signed PartnerOpen Cloud client so delegated Space requests can be authenticated. | site URL; URL prefix; technical site identifier; Connector version | An allowlisted PartnerOpen Cloud host, when a paired client connects | Until consent is withdrawn or the site is disconnected. |
| `partner_email` | false | Partner invitation email | Record the partner address used for an invitation and service notices for this Space. | partner email address; site URL; Space name | Stored on this site and shared with the paired client during pairing | Until consent is withdrawn. The Connector sends no email itself. |
| `content_sync` | true | Content sync | Receive the published page snapshot that this site renders. | typed page blocks; SEO title and description; link metadata; allowed destination hosts; snapshot version | This site, through the signed Connector API | The latest snapshot remains until replacement, explicit deletion, or uninstall. |
| `agent_pack` | false | Agent context files | Publish `AGENTS.md`, `llms.txt`, `ai-context.json`, `manifest.json`, and `sitemap.xml` for the delegated Space. | public Space title and summary; public page URLs; allowed block types | Public visitors and AI agents | Served while the Space is published; local snapshot data remains until replacement, deletion, or uninstall. |
| `aggregate_metrics` | false | Aggregate click counters | Record daily click totals per placement so the partner can measure placements. | date; placement identifier; click count | Stored on this site and readable by a paired client through the signed metrics route | 90 days on this site, then deleted by the daily cron or earlier on uninstall. |
| `affiliate_service` | false | Affiliate service links | Allow approved links supplied by a connected affiliate service to be published in this Space with disclosure. | approved public link identifier; placement identifier; disclosure text; allowlisted destination host | Published on this site with disclosure; no service credentials are stored here | Until consent is withdrawn, the snapshot is deleted, or uninstall. |

The default Cloud host allowlist is `partneropen.com` and `www.partneropen.com`. The `partneropen_connector_cloud_hosts` filter or comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace this list with configured HTTPS origins. Cloud-related recipients are limited to the resulting allowlist.

## Never collected

The Connector never collects or sends cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, or visitor-level click events. Measurement is limited to aggregate daily counts per placement. A destination service may process a visitor after a redirect under that service's own notice; the Connector does not add visitor identity to the redirect.

Snapshot canonical and image URLs must be same-origin with the connected site's `home_url()` (scheme, host, and port must match). Remote image URLs are rejected and never proxied, so a third-party image host cannot receive a visitor's IP address or User-Agent through the Connector without consent.

## Local and Cloud data

The Connector is local-first and makes zero outbound HTTP requests of its own in M1, including before and after pairing. The signed reference client at `apps/cloud/scripts/sync-demo.mjs` may connect to the site's Connector after `cloud_connection` consent and pairing. It sends only payload fields allowed by the granted scope through the signed API. The site secret is stored in a non-autoloaded WordPress option, returned exactly once during pairing, and never included in REST status, snapshots, public HTML, or agent files.

The latest published snapshot stays in WordPress until the owner explicitly deletes it. Aggregate click counters are retained locally for 90 days and then pruned by the daily `partneropen_connector_prune_clicks` event. Withdrawing `aggregate_metrics` stops counter collection immediately. Withdrawing `affiliate_service` stops link publication: labels become plain text with no anchor and the resolver returns `404` with reason `consent`. Withdrawing `agent_pack` returns its generated routes as no-store `404` responses. Optional-scope withdrawal does not revoke the site key; only full **Withdraw consent & disconnect** revokes it.

PartnerOpen Cloud keeps no snapshot, metrics, or agent-file copy in M1 because it has no Connector ingest endpoint or durable store. If a future Cloud release stores a copy, that service's own notice governs its retention; disconnecting this site does not claim to erase data held by another service.

## Package variants

The full `partneropen-connector` package retains the resolver and optional affiliate-link capability for direct self-hosted installation. The separate directory-safe package is the WordPress.org candidate: it rejects link and affiliate blocks and hard-disables resolver redirects. The variants share the same local-first consent and storage model; the directory-safe restrictions are enforced in that package and do not create a Cloud data store.

## Payment boundary

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 supplies publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is a deferred M2 capability and is not available in this milestone.

## Withdrawal, disconnect, retention, and uninstall

The owner has two independent site-wide controls: **Global Pause** and **Withdraw consent & disconnect**. Disconnect withdraws all consent and unpairs the site; it revokes the site secret, stops signed data exchange, records `status = disconnected`, and keeps local snapshots until explicit deletion. It is not partner removal or a second content-management switch. Reconnection requires fresh consent and a fresh pairing code.

Global Pause temporarily overlays all public Space, resolver, and agent routes with a no-store `404`; it does not delete state or revoke consent. Resuming restores the retained published snapshot when its Space is active.

Activation schedules the daily `partneropen_connector_prune_clicks` cron, deactivation clears it, and the callback deletes click rows older than 90 days. Uninstall deletes all plugin options (including connection, consent, secret, pause, Space registry, every `partneropen_snapshot_*` option, and clicks) and plugin transients. This cleanup is separate from consent withdrawal and disconnect.

The Connector is single-site only. It has no multisite network-management or network-wide consent behavior.

## Site-owner responsibility

The site owner is responsible for choosing lawful destinations, maintaining an accurate disclosure, publishing any additional notice required for the site's audience, and reviewing configured consent scopes. PartnerOpen does not claim that an external destination has a particular legal, commercial, or editorial status. PartnerOpen does not provide legal advice, destination approval, hosted partner editing, tenant management, email delivery, Cloud-side storage, payment processing, or visitor-level analytics.

Canonical Terms of Use: https://partneropen.com/terms
