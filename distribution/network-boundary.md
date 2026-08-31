# PartnerOpen network boundary

The **PartnerOpen Connector** is the WordPress-side durable system of record. It stores only the local connection record, consent records, delegated Space metadata, validated published snapshots, and aggregate placement counters required by the M1 contract. It does not store network credentials, payout ledgers, postbacks, conversion records, private account identifiers, or service secrets in public snapshots.

The **PartnerOpen Cloud** site is stateless in M1. Its public web app exposes the landing page, canonical legal pages at `https://partneropen.com/terms` and `https://partneropen.com/privacy`, and documented information routes; the reference client signs requests to a Connector base URL. A tenant-backed partner editor and service adapters are deferred until managed Postgres (or an equivalent durable store), a session/token authentication service, and a transactional email provider are provisioned. The Connector itself makes zero outbound HTTP requests in M1.

## Cloud host and link exchange

The default Cloud host allowlist is `partneropen.com` and `www.partneropen.com`. The `partneropen_connector_cloud_hosts` filter or comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace the list with configured HTTPS origins. Cloud-related recipients are limited to the resulting allowlist.

The full self-hosted package may receive an approved public link from an optional connected service through a signed request. The Connector receives only the public link identifier, placement identifier, destination allowed by the HTTPS host list, and visible disclosure text. The `affiliate_service` consent scope must be granted. Every rendered full-package link uses the same-origin resolver `/partneropen/go/{link_id}/{placement_id}`, visible `Goes to <host>` destination text, and `rel="sponsored nofollow noopener"`; no raw external `href` is published. Withdrawing the scope emits labels as plain text and makes the resolver return `404` with reason `consent`.

The separate directory-safe package is the WordPress.org candidate. It rejects link and affiliate blocks and hard-disables resolver redirects; it does not expand the network boundary or create a second Cloud service. The full package retains resolver/affiliate capability only for direct self-hosted installation.

Credentials, payout rules, postbacks, conversion and attribution records, financial ledgers, and private service/network identifiers remain outside the Connector. They are not copied into WordPress options, REST responses, rendered HTML, or the redacted agent pack. WordPress is not a credential vault or a payout system. M1 Cloud has no storage or ingest endpoint for these records.

## Payment boundary

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 provides publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is a deferred M2 capability and is not available in M1.

## Measurement boundary

The only M1 measurement is an aggregate daily click total keyed by placement. It contains a date, placement identifier, and count, retained for 90 days. The Connector never collects cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, or visitor-level click events. `aggregate_metrics` is enforced at collection: withdrawing it stops counter writes. Metrics are readable through the signed route while consent is granted; the Connector sends no metrics payload outbound in M1.

Activation schedules `partneropen_connector_prune_clicks` daily; deactivation clears it; the callback prunes counters older than 90 days. Uninstall deletes all plugin options, every `partneropen_snapshot_*` option, counters, and plugin transients.

## Owner-facing behavior and platform boundary

Global Pause overlays all public Space, agent, and resolver URLs with a no-store `404` but does not delete local state. Withdraw consent & disconnect revokes the site secret and stops signed data exchange while retaining local snapshots. Optional-scope withdrawal stops only its named behavior and does not revoke the site key. No Cloud-side snapshot, metrics, or agent-file copy is stored in M1; future service data is governed by that service's notice.

The Connector is single-site only. It has no multisite network-management or network-wide consent behavior.
