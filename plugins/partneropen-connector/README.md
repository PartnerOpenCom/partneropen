# PartnerOpen Connector

PartnerOpen Connector is the local-first, GPL-licensed WordPress plugin that keeps the durable PartnerOpen record on the site. It stores the connection, consent records, delegated Space metadata, published snapshots, and aggregate click counters in WordPress options. PartnerOpen Cloud is a stateless public site and signed reference-client surface; it is not the system of record and has no hosted editor, tenant service, or Cloud-side storage in this milestone.

## Owner surface

The first-run PartnerOpen screen lets the owner choose a URL prefix, record a partner contact locally, review the six consent scopes, acknowledge the canonical Terms of Use and Privacy Notice, and issue a one-time pairing code. Required scopes are Cloud connection and Content sync. Partner invitation email is optional at the option level and is needed only if an invitation is sent; the Connector sends no email itself.

- connection status, paired-at time, last sync, URL prefix;
- the number of published Spaces;
- granted consent scopes; and
- Global Pause state.

The owner retains exactly two site-wide controls:

1. **Global Pause**, which makes every PartnerOpen URL return 404 while preserving partner work and the latest local snapshot; and
2. **Withdraw consent & disconnect**, which withdraws all consent, stops data exchange, revokes this site's key, marks the connection disconnected, and keeps local published snapshots until they are explicitly deleted.

Optional consent scopes can be withdrawn independently. Optional withdrawal stops only that scope's behavior and does not revoke the site key: withdrawing `affiliate_service` makes labels plain text with no anchor and makes the resolver return 404 with reason `consent`; withdrawing `aggregate_metrics` stops counter writes; withdrawing `agent_pack` returns its generated routes as no-store 404 responses. The Status screen also has a separate explicit action for deleting local published snapshots. Reconnecting requires a fresh pairing code and fresh consent.

The Connector is single-site only. It has no multisite network-management or network-wide consent behavior.

## Package variants

This source tree builds the full `partneropen-connector` package for direct self-hosted installation. It retains the same-origin resolver and optional affiliate-link capability. The separate directory-safe artifact, built with `distribution/build-directory-plugin.sh`, is the WordPress.org candidate: it rejects link and affiliate blocks and hard-disables resolver redirects. Do not submit the full package to the WordPress.org directory.

## Local-first guarantee and external service

The Connector makes zero outbound HTTP requests of its own in this milestone. The signed reference client at `apps/cloud/scripts/sync-demo.mjs` may connect to the site's Connector after the owner grants Cloud connection consent and completes pairing. The client is not a hosted partner editor or tenant backend. Each data category is gated by its own consent scope, and PartnerOpen Cloud keeps no Connector snapshot, metrics, or agent-file copy in this milestone.

Default Cloud hosts are `partneropen.com` and `www.partneropen.com`. The `partneropen_connector_cloud_hosts` filter or comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace this allowlist; only configured HTTPS origins are accepted. The canonical legal URLs are [Terms of Use](https://partneropen.com/terms) and [Privacy Notice](https://partneropen.com/privacy).

The Connector never collects cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, or visitor-level click events. Aggregate counters contain only date, placement, and count.

M2 capabilities such as passwordless partner login, tenant/site/Space isolation, a hosted typed page builder, email delivery, Cloud-side metrics storage, billing, and service/network adapters are deferred and not built. They require managed Postgres (or an equivalent durable store), session/token authentication, and transactional email; no placeholder M2 service is provided.

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 provides publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties; billing is deferred to M2 and is not available in this milestone.

## Public URL surface

With a configured prefix such as `partner`, the connector serves:

- `/{prefix}/{space}/` — the published snapshot;
- `/{prefix}/AGENTS.md` — canonical agent context;
- `/{prefix}/agents.md` — redirect to the canonical file;
- `/{prefix}/llms.txt`;
- `/{prefix}/ai-context.json`;
- `/{prefix}/manifest.json`; and
- `/{prefix}/sitemap.xml`.

Every rendered outbound link goes through `/partneropen/go/{link_id}/{placement_id}` on the same site. Published HTML never exposes a raw external destination as an `href`; the existing `partneropen-space__disclosure` includes a visible disclosure and `Goes to <host>`, and the anchor uses `rel="sponsored nofollow noopener"`. The resolver is scoped to the matching Space, checks the allowlisted HTTPS destination, and returns 404 for Global Pause, suspended/unpublished Spaces, unknown links, invalid placements, or withdrawn `affiliate_service` consent.

Snapshot image URLs are same-origin only: scheme, host, and port must match the connected site's `home_url()`. Remote images are rejected; the Connector does not proxy them, because a third-party image host could receive visitor IP and User-Agent data without consent.

## REST API

The Connector REST namespace is `/wp-json/partneropen/v1`. Route authentication, consent gates, pairing, strict snapshot validation, Space status, aggregate metrics, and disconnect semantics are defined in [`docs/connector-api.md`](../../docs/connector-api.md) and implemented by `includes/Http/RestApi.php`.

Signed requests must include non-empty `X-PartnerOpen-Site` equal to the stored technical site id and non-empty `X-PartnerOpen-Scopes` containing the route's required scope. Missing or mismatched site identity returns `401 partneropen_signature_invalid`; missing required scope returns `403 partneropen_consent_required`. `cloud_base` must use the Cloud host allowlist. Invalid snapshots return `400 partneropen_invalid_snapshot` with `data.errors` field paths. `GET /spaces` returns only `{spaces: [{id, slug, title, status, snapshot_version, published_at}]}`.

## Retention, cron, and uninstall

Aggregate click counters are retained for 90 days. Activation schedules the daily `partneropen_connector_prune_clicks` event; deactivation clears it; the callback prunes old counters. Uninstall deletes connection, consent, secret, pause, Space registry, every `partneropen_snapshot_*` option, click counters, and plugin transients. Uninstall is explicit cleanup and is separate from optional-scope withdrawal and from disconnect, which retains local snapshots.

## Running the plugin tests

The tests are standalone PHP scripts. From this plugin directory, run them inside the PHP 8.2 CLI container used by the repository:

```sh
for test in tests/test-*.php; do
    php "$test" || exit 1
done
```

No Composer dependencies are required.
