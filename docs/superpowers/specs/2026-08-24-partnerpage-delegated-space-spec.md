# PartnerOpen Delegated WordPress Space Specification

## 1. Positioning

PartnerOpen is a neutral system for publishing delegated WordPress Spaces. A site owner installs **PartnerOpen Connector**, chooses a URL prefix, records a partner contact, and explicitly grants data scopes. The Connector remains useful locally and makes no outbound HTTP request of its own in M1.

A partner publishes an assigned Space through signed Connector REST requests, normally via `apps/cloud/scripts/sync-demo.mjs`. Every public link is same-origin, visibly disclosed, and rendered with `rel="sponsored nofollow noopener"`; published HTML never contains a raw external destination. The product does not hide destinations, promise ranking or moderation outcomes, or use visitor tracking.

The Connector is single-site only. Multisite network activation, network-wide consent, network-level options, and network administration are not implemented.

## 2. Product split and milestones

### M1 — Connector MVP (ships in this repository)

The Connector is the durable system of record. Pairing state, consent records, Spaces, published snapshots, and aggregate click counters are stored in the WordPress database through the Connector options layer. M1 includes:

- owner setup with a prefix, partner email, policy version, and per-scope consent;
- a local-first zero-outbound-HTTP guarantee for the Connector;
- one-time pairing codes and a signed `partneropen/v1` REST API;
- delegated publishing through signed requests and the reference client (there is no hosted partner editor);
- strict format-3 snapshot validation and rendering;
- same-origin resolver links with an HTTPS destination allowlist and mandatory disclosure;
- canonical agent-context assets with scope gating;
- aggregate daily click counters with 90-day local retention;
- Global Pause as a publication overlay; and
- Withdraw consent & disconnect, including secret revocation and unpairing.

PartnerOpen Cloud is a stateless public site in M1. Its live surfaces are the landing page, `/api/health`, `/api/consent-scopes`, and `/api/connector-manifest`. It has no pairing, tenant, publish, metrics-ingest, hosted-editor, or durable-storage server routes and keeps no state in module scope or an in-memory map. The Cloud web app serves canonical legal pages at `https://partneropen.com/terms` and `https://partneropen.com/privacy`.

### M2 — Cloud tenant backend (deferred; not built)

M2 is a separate milestone and must not be represented as an M1 capability. It may add passwordless partner login, tenant/site/Space isolation, a hosted typed page builder, email invitations and notifications, Cloud-side metrics storage, billing, and network adapters. The named prerequisites are managed Postgres (or an equivalent durable store), a session/token authentication service, and a transactional email provider. Credentials for those services do not exist in this environment. No M2 feature is stubbed in M1.

### Distribution variants

The full `partneropen-connector` package is for direct self-hosted installation and retains the same-origin resolver and optional affiliate-link capability. The separate directory-safe artifact is the WordPress.org candidate; it rejects link and affiliate blocks and hard-disables resolver redirects. The two artifacts share the M1 local-first Connector, consent model, storage, and lifecycle cleanup. The directory-safe restrictions are package behavior, not a second Cloud architecture.

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 supplies publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is deferred to M2 and is not available.

## 3. Space and page model

The owner selects a prefix such as `/partner/`; `Support\Validation::prefix()` requires a lowercase slug of 2–32 characters, rejects reserved WordPress paths and email-like values, and prevents PII-shaped prefixes. Owner setup does not create individual Spaces. A Space has an id, slug, title, status, `snapshot_version`, and publication time. The Connector permits at most five Spaces.

On the first signed `PUT /partneropen/v1/spaces/{space}/snapshot`, the route resolves `{space}` by existing id and then by slug. If neither exists, it auto-provisions a published Space with id `space-{slug}` after the snapshot validates. A first publish returns `201`; a replacement returns `200`. If five Spaces already exist and the route names a new Space, it returns `409 partneropen_space_limit`.

A canonical snapshot has format `version` `3`, a `space` object, typed blocks, and a `links` map. Required top-level members are `version`, `space`, `blocks`, and `links` (`links` may be empty). `seo`, `allowed_hosts`, and `agent` are optional. The Connector validates against [`docs/snapshot-schema.json`](../../snapshot-schema.json) and returns `400 partneropen_invalid_snapshot` with `data.errors`, a list of invalid field paths, for schema/origin/link/block failures. A declared format other than `3` returns `400 partneropen_unsupported_snapshot_version`. Unknown properties and invalid shapes are rejected rather than silently accepted.

The block allowlist is `hero`, `text`, `cards`, `cta`, `link`, `faq`, `comparison`, `table`, and `image`. Rich text is sanitized and anchors are removed. Partner content cannot install PHP, JavaScript, an iframe, a theme, or a plugin.

A canonical URL and every image URL are same-origin with the connected site's `home_url()` on both write and read: scheme, host, and port must match. A foreign `seo.canonical` is rejected with `partneropen_invalid_snapshot` and a field path in `data.errors`. A remote `image.url` is rejected with the same error and never proxied, because a third-party image host could receive visitor IP and User-Agent data without consent.

The option keys are fixed:

- `partneropen_connection` — connection status and non-secret connection metadata;
- `partneropen_consent` — per-scope grant and revocation records;
- `partneropen_secret` — the non-autoloaded site secret, never returned by REST or agent output;
- `partneropen_pause` — the owner pause flag and change time;
- `partneropen_spaces` — Space registry metadata;
- `partneropen_snapshot_{space_id}` — one canonical snapshot per Space; and
- `partneropen_clicks` — date and placement aggregate counters.

Pairing uses transient `partneropen_connector_pair_code` for 900 seconds. Signature replay markers use `partneropen_connector_nonce_<nonce>` for 600 seconds; a nonce longer than 128 characters is rejected. Replay protection depends on the WordPress Transients API and a persistent object cache/durable transient backend for a cross-process guarantee; a flushed or non-persistent cache can lose markers before TTL expiry.

## 4. Roles and authority

### Owner

During setup the owner chooses the prefix, records the partner email, selects each consent scope, and accepts the applicable policy. After connection the owner has exactly two controls: **Global Pause** and **Withdraw consent & disconnect**. The owner can inspect technical status, but does not approve or reject individual publications, manage partner content, revoke a partner as a content action, pause one Space, or operate an in-product chat. Optional-scope withdrawal and explicit local-snapshot deletion are separate status actions, not additional site-wide controls.

### Partner

In M1 the partner operates the delegated Space using signed REST requests, normally through the reference sync client. A valid content-sync request may publish without owner approval, subject to strict snapshot validation and the Connector's Space and connection checks. This is owner-authorized delegated publishing, not auto-posting, scraping, or content spinning. A hosted partner login, editor, and invitation flow are M2 items, not M1 behavior.

### Cloud and service adapters

M1 Cloud exposes only stateless public information surfaces and signing/client support. Optional service adapters are a future Cloud concern. They do not move credentials, payout records, or private service identifiers into WordPress or public snapshots.

## 5. Setup, consent, pairing, and disconnect

Immediately after activation, the Connector is local-first. It does not contact Cloud, send email, publish content, send snapshots, send metrics, or call an external service until matching consent is granted; the Connector itself makes zero outbound requests in this milestone. The six scopes are exactly:

| ID | Required | Label | Purpose | Fields (in order) | Recipient | Retention |
|---|---:|---|---|---|---|---|
| `cloud_connection` | true | Cloud connection | Pair this site with the signed PartnerOpen client so delegated Space requests can be authenticated. | `site URL`; `URL prefix`; `technical site identifier`; `connector version` | An allowlisted PartnerOpen Cloud host, when a paired client connects | Until consent is withdrawn or the site is disconnected. |
| `partner_email` | false | Partner invitation email | Record the partner address used for an invitation and service notices for this Space. | `partner email address`; `site URL`; `Space name` | Stored on this site and shared with the paired client during pairing | Until consent is withdrawn. The Connector sends no email itself. |
| `content_sync` | true | Content sync | Receive the published page snapshot that this site renders. | `typed page blocks`; `SEO title and description`; `link metadata`; `allowed destination hosts`; `snapshot version` | This site, through the signed Connector API | Latest snapshot until replacement, deletion, or uninstall. No Cloud copy is kept in M1. |
| `agent_pack` | false | Agent context files | Publish AGENTS.md, llms.txt, ai-context.json, manifest.json and sitemap.xml for the delegated Space. | `public Space title and summary`; `public page URLs`; `allowed block types` | Public visitors and AI agents | Served while the Space is published; local data until replacement, deletion, or uninstall. |
| `aggregate_metrics` | false | Aggregate click counters | Record daily click totals per placement so the partner can measure placements. | `date`; `placement identifier`; `click count` | Stored on this site and readable by a paired client through the signed metrics route | 90 days on this site, then deleted by daily cron or uninstall. |
| `affiliate_service` | false | Affiliate service links | Allow approved links supplied by a connected affiliate service to be published in this Space with disclosure. | `approved public link identifier`; `placement identifier`; `disclosure text`; `allowlisted destination host` | Published on this site with disclosure; no service credentials are stored here | Until consent withdrawal, snapshot deletion, or uninstall. |

`Domain\Consent::scope_meta()` returns these label, purpose, fields, recipient, retention, and required values in this order. Never-collected data is cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, and visitor-level click events.

`agent_pack` is enforced locally at publication time: without that scope, all six generated routes return `404` with no-store semantics, while the Space page and resolver remain governed by Global Pause, Space status, and their own gates.

`affiliate_service` is enforced at rendering and resolution: withdrawal emits labels as plain text with no anchor and makes the resolver return `404` with reason `consent`. `aggregate_metrics` is enforced at collection: withdrawal stops new counter writes. Optional-scope withdrawal does not revoke the site key. Full **Withdraw consent & disconnect** revokes the secret, withdraws all scopes, and unpairs.

Setup stores a prefix and partner email locally, then `Application\Pairing::issue_code()` creates a 12-character one-time code in transient `partneropen_connector_pair_code` for 900 seconds. `POST /wp-json/partneropen/v1/pair` consumes that code only after Cloud host and optional-email validation succeed and returns the site id, secret exactly once, granted scopes, prefix, and policy version. An invalid Cloud host returns `400 partneropen_invalid_cloud_base` without consuming the code. The reference client keeps the returned secret for signing; the Connector never returns it from status, metrics, or agent assets.

### Disconnect is consent withdrawal and unpairing

**Withdraw consent & disconnect** is a consent-withdrawal and unpairing mechanism, separate from content controls and separate from administration of a partner. It is not a second content-management switch and it is never described as removing or revoking a partner.

On disconnect the Connector revokes the site secret, stops all outbound calls, records `connection.status = disconnected`, records the disconnect time, and withdraws connection consent. The local published snapshots remain in WordPress until the owner explicitly deletes them. No Cloud-side snapshot, metrics or agent-file copy is stored in this M1; future service data is governed by that service's notice. Reconnecting requires fresh consent and a fresh pairing code. Global Pause remains the independent publication overlay.

## 6. Global Pause

Global Pause is an owner-controlled publication overlay, not disconnect and not deletion. When `partneropen_pause.owner_paused` is true, every public Connector surface returns `404`, `Cache-Control: no-store, no-cache, must-revalidate`, and `X-Robots-Tag: noindex`:

- `/{prefix}/{space}/`;
- `/{prefix}/AGENTS.md`, the canonical agent asset;
- `/{prefix}/llms.txt`;
- `/{prefix}/ai-context.json`;
- `/{prefix}/manifest.json`;
- `/{prefix}/sitemap.xml`; and
- `/partneropen/go/{link_id}/{placement_id}`.

Pause never deletes connection state, consent records, Spaces, snapshots, or aggregate counters. It also does not revoke the secret or alter Cloud-side state. When resumed, a published and otherwise active Space renders its retained snapshot again. A suspended Space remains unavailable until resumed independently through the signed Space route.

## 7. Connector API, signing, and link resolver

The Connector registers the `partneropen/v1` namespace. Every route has a `permission_callback`; pairing uses the one-time code and all other Cloud operations use signed headers and the site secret.

WordPress rewrite/query variables use `partneropen_space`, `partneropen_asset`, `partneropen_link`, and `partneropen_placement`; these are internal names for the public Space, agent-asset, and resolver paths.

The signature canonical string is five newline-separated fields:

```text
METHOD\nPATH\nTIMESTAMP\nNONCE\nsha256_hex(BODY)
```

`METHOD` is uppercase. `PATH` starts with `/partneropen/v1/` and excludes the query string. The HMAC is SHA-256 with the site secret. Requests require non-empty `X-PartnerOpen-Site`, `X-PartnerOpen-Timestamp`, `X-PartnerOpen-Nonce`, `X-PartnerOpen-Signature`, and `X-PartnerOpen-Scopes`. The site header must equal the stored site id and the scopes header must contain the route's required scope; missing/mismatch produces `401 partneropen_signature_invalid` or missing scope produces `403 partneropen_consent_required`. Timestamps must be within +/-300 seconds; a nonce is recorded as `partneropen_connector_nonce_<nonce>` for 600 seconds and cannot be replayed; nonces over 128 characters are rejected. A persistent transient backend is needed for reliable replay protection across workers.

The public resolver path is `/partneropen/go/{link_id}/{placement_id}`. A rendered link contains no raw external `href`; it points to the same-origin resolver, shows the mandatory disclosure plus visible `Goes to <host>` text, and uses `rel="sponsored nofollow noopener"`. The resolver checks Global Pause, the target Space's status/publication, Affiliate service consent, link status, HTTPS destination allowlisting, same-Space placement membership, and canonical origin before returning a `302`. Any failed check is a `404`, never an external redirect. The resolver is scoped per Space: an id/placement pair from a suspended Space cannot resolve using a matching id in another Space.

The snapshot route auto-provisions a Space on first valid publish: `{space}` resolves by id and then slug, or creates id `space-{slug}` when no match exists. It returns `201` for that creation and `200` for replacement. A fifth existing Space makes a new slug fail with `409 partneropen_space_limit`; owner setup only sets the prefix and does not create Spaces. The Connector assigns `snapshot_version` monotonically for each Space and preserves `suspended` status while syncing.

Canonical SEO URLs and image URLs must match `home_url()` by scheme, host, and port on write and read. Images are never proxied. Strict validation returns field paths in `data.errors` for invalid snapshots.

## 8. Agent-context pack

For prefix `partner`, the canonical asset is `/partner/AGENTS.md`. `/partner/agents.md` returns `308` to the canonical uppercase asset. The other generated assets are `/partner/llms.txt`, `/partner/ai-context.json`, `/partner/manifest.json`, and `/partner/sitemap.xml`. The sitemap lists canonical URLs only.

`Public\AgentPack` builds public context from a positive field allowlist, not from a filter over arbitrary input. Each section contributes only named fields — Space `slug`, `title`, `status`; SEO `title`, `description`, `canonical`; `agent` `summary`, `instructions`, and `entities` limited to `name` and `type` — and each block type contributes only its own public fields. A `text` block contributes sanitized HTML converted to plain text; it cannot add anchors or raw destinations. Link destinations, emails, site/tenant/partner identifiers, secrets, credentials, and unknown fields are excluded. Generated assets are gated by `agent_pack` and return no-store `404` after withdrawal.

The rendered Space page enqueues the public stylesheet handle `partneropen-connector-public` from `assets/css/partneropen.css`. Stylesheet enqueue is part of the public Space-page behavior, not an agent-pack asset.

## 9. Measurement and external-service boundary

M1 measurement is aggregate and privacy-preserving: one daily count per placement, stored on the site and retained for 90 days. The Connector does not collect cookies, IP addresses, User-Agent values, referrers, fingerprints, or unique visitor identifiers. Counters are readable through the signed `/metrics` route only when `aggregate_metrics` and `cloud_connection` are granted; `ClickCounter::record()` writes nothing when `aggregate_metrics` is withdrawn. The Connector itself sends no metrics payload outbound in M1.

The default Cloud hosts are `partneropen.com` and `www.partneropen.com`. The `partneropen_connector_cloud_hosts` filter or comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace the list with configured HTTPS hosts. If a future Cloud adapter uses an external link or service network, credentials, payout rules, postbacks, conversion records, and private ledgers stay outside WordPress and public snapshots. WordPress receives only an approved public HTTPS destination, placement context, and visible disclosure. The `affiliate_service` scope is an explicit opt-in for that adapter; it does not turn the Connector into a service network or payment processor.

## 10. Lifecycle, cron, uninstall, and acceptance

Activation schedules daily `partneropen_connector_prune_clicks`; deactivation clears it; the callback deletes counters older than 90 days. Uninstall is guarded by `WP_UNINSTALL_PLUGIN` and deletes connection, consent, secret, pause, Spaces, every `partneropen_snapshot_*` option, click counters, and plugin transients. Disconnect and optional-scope withdrawal intentionally do not delete local snapshots.

### M1 Connector MVP acceptance (enforced in this repository)

1. Connector activation works without Cloud and the Connector makes zero outbound HTTP before or after pairing in M1.
2. Owner setup stores a valid prefix and partner email and displays six independent consent scopes with required metadata.
3. Pairing consumes a one-time 12-character code only after allowlist validation, returns the secret once, and uses `partneropen/v1` signed requests thereafter.
4. Signed requests require matching non-empty site identity and a scope declaration containing the route's required scope; failures return the documented errors.
5. `content_sync` gates snapshot publication; strict format-3 validation returns `400 partneropen_invalid_snapshot` with `data.errors`; `agent_pack` gates public context assets; `aggregate_metrics` gates collection; optional `partner_email` and `affiliate_service` remain explicit opt-ins.
6. A signed client can publish a validated snapshot, auto-provisioning the named Space on first publish and assigning a monotonic `snapshot_version`; `GET /spaces` exposes only its documented fields.
7. Rendered outbound links use the same-origin resolver, visible disclosure, `Goes to <host>` text, and `rel="sponsored nofollow noopener"`; raw external `href` values are absent. The resolver is Space-scoped and returns `404` on suspension, pause, or withdrawn Affiliate service consent.
8. Same-origin canonical and image rules compare scheme, host, and port on write/read; remote images are rejected and never proxied.
9. The canonical agent pack is generated, redacted, text blocks contribute plain text, and is served with uppercase `AGENTS.md` plus lowercase `308` alias; public Space pages enqueue the public stylesheet.
10. The resolver returns a `302` only for an active, allowlisted HTTPS destination and records only aggregate placement counts while the metrics scope is granted.
11. Global Pause returns the documented no-store `404` overlay for every public asset, Space page, and resolver, and Resume restores retained publication.
12. Withdraw consent & disconnect revokes the secret, stops outbound calls, marks the connection disconnected, keeps local snapshots, and rejects subsequent signed calls; reconnect requires new consent and pairing.
13. Daily pruning, deactivation cleanup, and uninstall cleanup remove the documented lifecycle data, and the plugin does not implement multisite network handling.
14. The reference sync client performs pair, publish, status, and metrics calls against a Connector base URL and does not act as a stateful server.

### M2 deferred acceptance (not implemented)

The following are intentionally deferred until the named durable-store, auth, and email prerequisites exist: passwordless partner login; tenant/site/Space isolation in Cloud; a hosted typed page builder; email invitations and notifications; Cloud-side metrics storage; billing; and Cloud service/network adapters. They must not be presented as available M1 behavior.

### Non-goals

PartnerOpen does not provide owner publication approval/rejection, an owner partner-revoke control, per-Space owner pause, a user-facing audit log, in-product chat, hidden links, cloaking, guaranteed ranking or moderation outcomes, or per-visitor tracking. Cloud does not use fake or in-memory persistence in place of the required durable backend.

## OMP naming limitation

The external OMP chat session title is not controlled by this repository. All project-facing references in this specification use **PartnerOpen**; this documentation does not claim that a repository or Vercel setting can rename the external chat title.
