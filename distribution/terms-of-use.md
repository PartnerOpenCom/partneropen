# PartnerOpen terms of use

_Last updated: 2026-08-24_

Canonical terms: https://partneropen.com/terms

These terms describe use of **PartnerOpen Connector** and the public **PartnerOpen Cloud** site in the M1 Connector MVP. The Connector is a GPL-2.0-or-later WordPress plugin and the durable system of record for connection state, consent records, delegated Spaces, published snapshots, and aggregate daily click counters. The M1 Cloud site is stateless: it does not provide a hosted partner editor, tenant backend, pairing store, publish store, metrics-ingest store, or other durable Connector state.

## M1 and M2 boundary

M1 provides local-first owner setup, granular consent, one-time pairing, signed `partneropen/v1` Connector requests, strict format-3 snapshot validation, same-origin disclosed links in the full package, consent-gated public context assets, aggregate daily counters retained for 90 days, Global Pause, and Withdraw consent & disconnect. A partner publishes through the signed reference client at `apps/cloud/scripts/sync-demo.mjs`; there is no hosted editor or passwordless partner login in M1. The Connector itself makes zero outbound HTTP requests in this milestone.

M2 is deferred and not built. It may add passwordless partner login, tenant/site/Space isolation, a hosted typed page builder, email invitations and notifications, Cloud-side metrics storage, billing, and service/network adapters. These capabilities require managed Postgres (or an equivalent durable store), a session/token authentication service, and a transactional email provider. Credentials for those prerequisites do not exist in this environment, and no M2 capability is stubbed or promised as available M1 behavior.

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 supplies publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is deferred to M2 and is not available in this milestone.

## Owner controls and consent

The site owner is responsible for installing the plugin, choosing a valid URL prefix, supplying a partner email when needed, reviewing the six scopes, and accepting the applicable privacy notice. `partner_email` is optional in the stored data structure but is required only if an invitation is sent. The Connector sends no email itself. Consent is granular and can be withdrawn. The Connector makes no outbound HTTP request of its own in M1; the signed reference client may connect to the site's Connector only after the relevant consent and pairing. Each payload is independently gated by its named scope.

The six scopes send or publish only the following data:

| ID | Required | Data | Recipient | Retention |
|---|---:|---|---|---|
| `cloud_connection` | true | Site URL, URL prefix, technical site identifier, Connector version | An allowlisted PartnerOpen Cloud host, when a paired client connects | Until consent is withdrawn or the site is disconnected. |
| `partner_email` | false | Partner email address, site URL, Space name | Stored on this site and shared with the paired client during pairing | Until consent is withdrawn. The Connector sends no email itself. |
| `content_sync` | true | Typed page blocks, SEO title and description, link metadata, allowed destination hosts, snapshot version | This site, through the signed Connector API | Latest snapshot until replacement, explicit deletion, or uninstall. No Cloud copy is kept in M1. |
| `agent_pack` | false | Public Space title and summary, public page URLs, allowed block types | Public visitors and AI agents | Served while the Space is published; local snapshot until replacement, deletion, or uninstall. |
| `aggregate_metrics` | false | Date, placement identifier, click count | Stored on this site and readable by the paired client through the signed metrics route | 90 days on this site, then deleted by the daily cron or earlier on uninstall. |
| `affiliate_service` | false | Approved public link identifier, placement identifier, disclosure text, allowlisted destination host | Published on this site with disclosure; no service credentials are stored here | Until consent is withdrawn, snapshot deletion, or uninstall. |

The default Cloud host allowlist is `partneropen.com` and `www.partneropen.com`. The `partneropen_connector_cloud_hosts` filter or comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace this allowlist with configured HTTPS origins. Cloud-related recipients are limited to the resulting allowlist.

The scope labels and purposes are fixed: **Cloud connection** pairs the site so signed delegated Space requests can be authenticated; **Partner invitation email** records the partner address used for an invitation and service notices; **Content sync** receives the published page snapshot that this site renders; **Agent context files** publishes the canonical public context files; **Aggregate click counters** records daily placement totals on this site; and **Affiliate service links** allows disclosed links supplied by a connected service.

`affiliate_service` is enforced locally: when withdrawn, labels are plain text with no anchor and the same-origin resolver returns `404` with reason `consent`. `aggregate_metrics` is enforced at collection: withdrawing it stops new counter writes. `agent_pack` is enforced locally at publication time: withdrawal makes its six generated routes return `404` with no-store semantics. Optional-scope withdrawal does not revoke the site key; only full **Withdraw consent & disconnect** revokes it.

Cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, and visitor-level click events are never collected by the Connector. The Connector is single-site only and does not implement multisite network handling.

## Distribution artifacts

The full `partneropen-connector` package is for direct self-hosted installation. It retains the same-origin resolver and optional affiliate-link capability. The separate directory-safe artifact, built by `distribution/build-directory-plugin.sh`, is the WordPress.org candidate: it rejects link and affiliate blocks and hard-disables resolver redirects. Both artifacts use the same local-first Connector, consent model, storage, and lifecycle cleanup; do not submit the full package to the WordPress.org directory.

## Publishing and links

A partner publishes an assigned Space only through a signed `partneropen/v1` REST request, normally using the reference client at `apps/cloud/scripts/sync-demo.mjs`. This is owner-authorized delegated publishing: the owner pairs the site and grants `content_sync`, and the Connector strictly validates the typed snapshot before storing it locally. The plugin does not auto-post to third-party sites, scrape feeds, spin or rewrite content, install code, or provide an automated content-spinning service. There is no hosted partner editor, passwordless login, tenant backend, email delivery, or Cloud-side publish store in M1.

In the full package, every external destination must be HTTPS and on the snapshot's allowed host list. Public HTML uses a same-origin resolver, shows the mandatory disclosure **“Disclosure: This is an affiliate link.”**, includes visible `Goes to <host>` destination-host text inside the disclosure, and uses `rel="sponsored nofollow noopener"`. The rendered page never exposes a raw external `href`. The resolver is scoped to the matching Space and returns `404` for Global Pause, suspended or unpublished Spaces, unknown links, invalid placements, or withdrawn `affiliate_service` consent. The directory-safe package rejects these link blocks and returns `404` from the resolver by design.

Snapshot image URLs must be same-origin with the connected site's `home_url()`; scheme, host, and port must match. Remote image URLs are rejected and never proxied, so a third-party image host cannot receive visitor IP or User-Agent data through the Connector without consent.

## Disconnect, pause, retention, and uninstall

**Withdraw consent & disconnect** withdraws all consent and unpairs the site. It revokes the site secret, stops signed data exchange, marks the connection disconnected, and retains local snapshots until explicit deletion. It is separate from content and administration controls and is not partner removal. No Cloud-side snapshot, metrics, or agent-file copy is stored in M1; future service data is governed by that service's notice. Reconnection requires a fresh pairing code and fresh consent.

Global Pause is a separate publication overlay. It makes public Space pages, agent assets, and resolver requests return a no-store `404`; it does not delete state, revoke a partner, or change the local snapshot. Resume restores an active published snapshot.

Activation schedules the daily `partneropen_connector_prune_clicks` event; deactivation clears it; its callback deletes counters older than 90 days. Uninstall deletes connection, consent, secret, pause, Space registry, every `partneropen_snapshot_*` option, click counters, and plugin transients. Optional-scope withdrawal and disconnect are not substitutes for explicit uninstall cleanup; disconnect intentionally retains local snapshots until the owner deletes them.

## Support and responsibility

Support is handled through the repository issue tracker and the WordPress.org support forum after publication. Support does not include custom content, legal or affiliate-compliance advice, destination approval, hosted editing, tenant management, email delivery, Cloud-side storage, payment processing, multisite network support, or visitor-level analytics.

PartnerOpen does not guarantee ranking, placement, moderation, approval, conversions, revenue, payment, or availability of any external destination. Users must comply with the laws, terms, and policies applicable to their sites and destinations. The site owner is responsible for choosing lawful destinations, maintaining accurate disclosures, and publishing any additional notice required for the site's audience.

Canonical Privacy Notice: https://partneropen.com/privacy
