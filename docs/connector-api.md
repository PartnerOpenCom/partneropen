# PartnerOpen Connector API

The free **PartnerOpen Connector** exposes the `partneropen/v1` namespace from the WordPress REST API. If the site URL is `https://site.example`, the base URL is:

```text
https://site.example/wp-json/partneropen/v1
```

Every route declares a `permission_callback`. Pairing is the only route that accepts a one-time code; all subsequent client requests are authenticated with the site secret and signed headers. The Connector is the durable system of record: these calls read and write WordPress options, not Cloud application memory. M1 has no hosted editor, tenant backend, Cloud pairing store, publish store, metrics-ingest store, or other durable Cloud state. PartnerOpen Cloud serves the public information routes and canonical legal pages at `https://partneropen.com/terms` and `https://partneropen.com/privacy`.

M2 tenant login, site/Space isolation, hosted editing, email delivery, Cloud-side metrics storage, billing, and service/network adapters are deferred and not built. They require a durable managed store (or equivalent), session/token authentication, and transactional email. PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares; payment terms and settlement remain outside PartnerOpen under participant agreements.

The full `partneropen-connector` package retains the same-origin resolver and optional affiliate-link capability for direct self-hosted installation. The separate directory-safe artifact is the WordPress.org candidate; it rejects link and affiliate blocks and hard-disables resolver redirects. This API contract describes the full package where resolver behavior is discussed.

## Consent and route matrix

These are the six consent scopes, in canonical order. `partner_email` is optional in the data structure but is required only if an invitation is sent. The Connector sends no email itself.

| ID | Required | Label | Purpose | Fields | Recipient | Retention |
|---|---:|---|---|---|---|---|
| `cloud_connection` | true | Cloud connection | Pair this site with the signed PartnerOpen client so delegated Space requests can be authenticated. | `site URL`, `URL prefix`, `technical site identifier`, `connector version` | An allowlisted PartnerOpen Cloud host, when a paired client connects | Until consent is withdrawn or the site is disconnected. |
| `partner_email` | false | Partner invitation email | Record the partner address used for an invitation and service notices for this Space. | `partner email address`, `site URL`, `Space name` | Stored on this site and shared with the paired client during pairing | Until consent is withdrawn. The Connector sends no email itself. |
| `content_sync` | true | Content sync | Receive the published page snapshot that this site renders. | `typed page blocks`, `SEO title and description`, `link metadata`, `allowed destination hosts`, `snapshot version` | This site, through the signed Connector API | Latest snapshot until replacement, explicit deletion, or uninstall. No Cloud copy is kept in M1. |
| `agent_pack` | false | Agent context files | Publish AGENTS.md, llms.txt, ai-context.json, manifest.json and sitemap.xml for the delegated Space. | `public Space title and summary`, `public page URLs`, `allowed block types` | Public visitors and AI agents | Served while the Space is published; local snapshot until replacement, deletion, or uninstall. |
| `aggregate_metrics` | false | Aggregate click counters | Record daily click totals per placement so the partner can measure placements. | `date`, `placement identifier`, `click count` | Stored on this site and readable by a paired client through the signed metrics route | 90 days on this site, then deleted by the daily cron or earlier on uninstall. |
| `affiliate_service` | false | Affiliate service links | Allow approved links supplied by a connected affiliate service to be published in this Space with disclosure. | `approved public link identifier`, `placement identifier`, `disclosure text`, `allowlisted destination host` | Published on this site with disclosure; no service credentials are stored here | Until consent is withdrawn, snapshot deletion, or uninstall. |

Never-collected data: cookies, IP addresses, User-Agent values, referrers, device fingerprints, unique visitor identifiers, and visitor-level click events.

| Route | Method | Authentication | Required consent |
|---|---|---|---|
| `/pair` | `POST` | One-time pairing code in the JSON body | `cloud_connection` |
| `/status` | `GET` | Signed request | `cloud_connection` |
| `/spaces` | `GET` | Signed request | `cloud_connection` |
| `/spaces/(?P<space>[a-z0-9-]{2,64})/snapshot` | `PUT` | Signed request | `content_sync` |
| `/spaces/(?P<space>[a-z0-9-]{2,64})/suspend` | `POST` | Signed request | `cloud_connection` |
| `/spaces/(?P<space>[a-z0-9-]{2,64})/resume` | `POST` | Signed request | `cloud_connection` |
| `/metrics` | `GET` | Signed request | `aggregate_metrics` |
| `/disconnect` | `POST` | Signed request | `cloud_connection` |

The route table is normative. A missing required consent scope is rejected even when the signature is valid. Optional-scope withdrawal changes only that scope's behavior and does not revoke the site secret; only `/disconnect` or the owner’s full **Withdraw consent & disconnect** action revokes the secret.

`agent_pack` is enforced locally at publication time rather than as an outbound-sync toggle. When it is not granted, the `AGENTS.md` route, lowercase `agents.md` alias, `llms.txt`, `ai-context.json`, `manifest.json`, and `sitemap.xml` each return `404` with no-store semantics. The Space page and same-origin resolver remain governed by Global Pause, Space status, and their own consent gates.

`affiliate_service` is enforced at rendering and resolution. When it is not granted, every link label is emitted as plain text with no anchor, and `Public\LinkResolver::resolve()` returns `{'status':404,'reason':'consent'}`. `aggregate_metrics` is enforced at collection: `Application\ClickCounter::record()` writes nothing unless that scope is granted. Existing counters remain readable only while the metrics route's scope is granted and are removed by retention cleanup or uninstall.

## Cloud host allowlist and pairing

The default Cloud host allowlist is:

```text
partneropen.com
www.partneropen.com
```

The `partneropen_connector_cloud_hosts` filter and the comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace the default list. Each configured value is an HTTPS host/origin accepted by the Connector; pairing rejects every other `cloud_base` with `400 partneropen_invalid_cloud_base`. A failed host validation does **not** consume the one-time pairing code, so the same code remains usable after a rejected host. The Connector itself makes zero outbound HTTP requests in M1; the allowlist constrains the signed reference client's Cloud base URL.

Pairing is local-first and uses no signature. The body is JSON:

```json
{
  "code": "AB12CD34EF56",
  "cloud_base": "https://partneropen.com",
  "partner_email": "partner@example.test"
}
```

`code` is the 12-character one-time value from the owner setup page. `cloud_base` is an HTTPS origin on the configured allowlist. `partner_email` is optional and is stored/shared only under `partner_email` consent.

A successful `200` response returns the secret exactly once:

```json
{
  "site_id": "site-identifier",
  "secret": "returned-once",
  "scopes": ["cloud_connection", "content_sync"],
  "prefix": "partner",
  "policy_version": "2026-08-24"
}
```

The Connector stores the secret in the non-autoloaded `partneropen_secret` option. It is never returned by `/status`, `/spaces`, `/metrics`, public HTML, or agent assets. A missing, expired, or already-consumed code returns `403 partneropen_pairing_invalid`. An invalid `cloud_base` returns `400 partneropen_invalid_cloud_base` without consuming the code. An invalid optional `partner_email` returns `400 partneropen_invalid_email`.

## Signing and authorization

The client signs the exact HTTP method, route path, timestamp, nonce, and request body. The canonical string is five lines, with a literal newline between fields:

```text
METHOD
PATH
TIMESTAMP
NONCE
sha256_hex(BODY)
```

- `METHOD` is uppercase, for example `PUT`.
- `PATH` starts with `/partneropen/v1/` and has no query string.
- `TIMESTAMP` is the Unix timestamp in seconds as an ASCII decimal string.
- `NONCE` is a fresh opaque value for each request and MUST be no longer than 128 characters.
- `sha256_hex(BODY)` is lowercase hexadecimal SHA-256 of the exact bytes sent. An empty body is hashed as the empty string.
- The signature is lowercase hexadecimal HMAC-SHA256 over the canonical string, keyed by the site secret.

Every signed request sends all of these headers, each non-empty:

```text
X-PartnerOpen-Site: <technical site identifier>
X-PartnerOpen-Timestamp: <Unix seconds>
X-PartnerOpen-Nonce: <fresh nonce, <=128 characters>
X-PartnerOpen-Signature: <lowercase hex HMAC-SHA256>
X-PartnerOpen-Scopes: <comma-separated granted scopes>
```

`X-PartnerOpen-Site` MUST equal the stored `connection.site_id`; missing or mismatched identity is rejected with `401 partneropen_signature_invalid`. `X-PartnerOpen-Scopes` MUST contain the route's required scope; missing or incomplete declarations are rejected with `403 partneropen_consent_required`. These checks happen before a route handler changes state.

The Connector accepts a timestamp only within +/-300 seconds of the current time. It stores a replay marker in transient `partneropen_connector_nonce_<nonce>` for 600 seconds; a nonce cannot be used twice, including with a different body or signature. Nonces longer than 128 characters are rejected with `401 partneropen_signature_invalid`.

The replay marker uses the WordPress Transients API. A persistent object cache or equivalent durable transient backend is required for replay protection across requests and workers; a non-persistent, flushed, or misconfigured object cache can lose a marker before its 600-second TTL and therefore cannot provide the same cross-process replay guarantee. The nonce length cap also prevents unbounded transient-key input.

The reference implementation is byte-compatible with `apps/cloud/lib/signature.ts`; `PartnerOpen\Connector\Http\Signature::sign()` is the PHP implementation.

## `POST /pair`

See [Cloud host allowlist and pairing](#cloud-host-allowlist-and-pairing). Pairing is the only unsigned operation and consumes `partneropen_connector_pair_code` only after consent, code, host, and optional email validation succeed. The code has a 15-minute TTL. A failed `cloud_base` validation leaves it usable.

## `GET /status`

A signed request returns the connection and publication summary without the secret, email, or click data:

```json
{
  "status": "local|connected|disconnected",
  "prefix": "partner",
  "owner_paused": false,
  "spaces": [
    {
      "id": "space-1",
      "slug": "reviews",
      "status": "published",
      "snapshot_version": 3,
      "published_at": 1724457600
    }
  ],
  "scopes": ["cloud_connection", "content_sync"],
  "connector_version": "0.1.0",
  "last_sync_at": 1724457600
}
```

The status response never adds a secret, partner email, or raw click counter.

## `GET /spaces`

A signed request returns **exactly** an object with one `spaces` member. Each entry has exactly these six fields and no others: `id`, `slug`, `title`, `status`, `snapshot_version`, and `published_at`.

```json
{
  "spaces": [
    {
      "id": "space-1",
      "slug": "reviews",
      "title": "Partner reviews",
      "status": "published",
      "snapshot_version": 3,
      "published_at": 1724457600
    }
  ]
}
```

At most five Spaces can be registered. The owner setup form sets only the prefix; individual Spaces are provisioned by the first signed snapshot publish. Draft Spaces are never rendered publicly.

## `PUT /spaces/{space}/snapshot`

The `{space}` path value is a lowercase slug matching `[a-z0-9-]{2,64}`. The signed JSON body is the canonical format-3 snapshot described in [`snapshot-schema.json`](snapshot-schema.json). `version` must be `3`; it identifies the snapshot format, while the per-Space monotonic `snapshot_version` is assigned by the Connector.

```json
{
  "version": 3,
  "space": {"id": "space-1", "slug": "reviews", "title": "Partner reviews", "status": "published"},
  "blocks": [],
  "links": {}
}
```

`seo`, `allowed_hosts`, and `agent` are optional. `links` may be empty. If `agent` is absent, the agent pack falls back to the Space title and SEO description. Unknown properties, missing required fields, invalid types, unsupported block shapes, invalid link references, and invalid destination/image origins fail strict validation; they are not silently accepted as a valid snapshot.

Every snapshot is validated against [`snapshot-schema.json`](snapshot-schema.json) and the Connector's same-origin/allowlist rules. Invalid JSON or schema data returns `400 partneropen_invalid_snapshot`, and its REST error `data.errors` is a list of invalid field paths (for example `blocks[0].link_id`, `links.l1.destination`, or `blocks[0].url`). A declared format other than `3` returns `400 partneropen_unsupported_snapshot_version`. If the route names a new Space while five Spaces already exist, it returns `409 partneropen_space_limit`.

The `seo.canonical` URL, when supplied, must be same-origin with the connected site's `home_url()`: scheme, host, and port must match on both write and read. A canonical pointing at another origin is rejected with `partneropen_invalid_snapshot` and a field path in `data.errors`; it never becomes a public canonical. Every image block `url` must also be HTTPS and same-origin with `home_url()` (scheme, host, and port); remote image URLs are rejected with `partneropen_invalid_snapshot` and are never proxied. This prevents a third-party image host from receiving visitor IP/User-Agent data without consent.

Link destinations remain HTTPS and must use an allowed host. The resolver, not the snapshot, is the only outbound link target in published HTML. A first publish returns `201`; a replacement returns `200`. Both return the updated Space record and normalized snapshot:

```json
{
  "space": {"id": "space-1", "slug": "reviews", "title": "Partner reviews", "status": "published", "snapshot_version": 3, "published_at": 1724457600},
  "snapshot": {"version": 3, "space": {"id": "space-1", "slug": "reviews", "title": "Partner reviews", "status": "published"}, "blocks": [], "links": {}}
}
```

Publishing a snapshot for a suspended Space syncs and versions the local snapshot but preserves `status = suspended`; a later signed resume makes that retained snapshot public again.

## `POST /spaces/{space}/suspend` and `POST /spaces/{space}/resume`

The body is empty. A signed `POST /suspend` changes the registered Space status to `suspended`; a signed `POST /resume` changes it back to `published`. Each successful request returns `200` with `{"space": <updated Space record>}`. An unknown Space returns `404 partneropen_space_not_found`. Suspension is independent of Global Pause: a suspended Space returns `404` on its page and its links never redirect, even when the global overlay is off. The resolver is scoped per Space, so a link id/placement pair is resolved only from the Space containing that placement; a matching id in another Space cannot bypass suspension.

## `GET /metrics`

A signed request with `aggregate_metrics` returns aggregate counters only:

```json
{
  "clicks": {
    "2026-08-24": {"hero": 3, "card-1": 1}
  },
  "retention_days": 90
}
```

There are no visitor-level events, cookies, IP addresses, User-Agent values, referrers, device fingerprints, or unique identifiers. `ClickCounter::record()` writes nothing when `aggregate_metrics` is withdrawn. Without `aggregate_metrics`, the response is `403 partneropen_consent_required`.

## `POST /disconnect`

The body is empty. A signed request revokes the secret, withdraws all consent, records `status = disconnected`, and stops future signed operations. It returns:

```json
{"status":"disconnected"}
```

Local Spaces and snapshots remain available in the WordPress database for explicit owner deletion; no Cloud-side snapshot, metrics or agent-file copy is stored in this M1. A later request signed with the old secret returns `401 partneropen_signature_invalid` (or `partneropen_connection_required` when connection state is checked first). Reconnection requires fresh consent and a fresh pairing code. Optional-scope withdrawal is narrower: it stops only the named behavior and does not revoke the secret or delete local snapshots.

## Error codes and HTTP status

Errors are JSON REST errors with a stable `code`, human-readable `message`, and (for invalid snapshots) `data.errors` field-path list. These are the Connector's REST error codes:

| HTTP | Code | Meaning |
|---:|---|---|
| 400 | `partneropen_invalid_cloud_base` | Pairing received a non-HTTPS URL or a host outside the configured Cloud allowlist. |
| 400 | `partneropen_invalid_email` | The optional pairing email is not valid. |
| 400 | `partneropen_invalid_snapshot` | Snapshot JSON is empty or fails strict schema, origin, link, or block validation; `data.errors` lists invalid field paths. |
| 400 | `partneropen_unsupported_snapshot_version` | Snapshot format version is not `3`. |
| 401 | `partneropen_signature_invalid` | Signature, required site header, timestamp, nonce, nonce length, or secret is invalid or replayed. |
| 403 | `partneropen_pairing_invalid` | Pairing code is missing, expired, or already consumed. |
| 403 | `partneropen_consent_required` | The required consent scope is not granted, the scope declaration is missing, or connection consent is absent. |
| 403 | `partneropen_connection_required` | The site is not paired/connected for this operation. |
| 404 | `partneropen_space_not_found` | No registered Space matches the route slug. |
| 409 | `partneropen_space_limit` | A new Space would exceed the five-Space limit. |

Public resolver failures are not REST errors. They return `404` with an internal reason of `paused`, `suspended`, `unknown_link`, `inactive`, `placement`, `destination`, or `consent`; no private state is disclosed. Timestamp-window, missing/mismatched site, malformed-header, nonce-length, and nonce-replay failures surface as `partneropen_signature_invalid` (`401`).

## Public URL surface

With the owner prefix `partner`, the Connector serves:

| URL | Content type/status | Behavior |
|---|---|---|
| `/partner/{space}/` | `text/html; charset=utf-8` | Rendered published snapshot, with same-origin resolver links; the public stylesheet is enqueued for the request. |
| `/partner/AGENTS.md` | `text/markdown; charset=utf-8` | Canonical agent context, built from the public field allowlist. |
| `/partner/agents.md` | `308` | Redirects to `/partner/AGENTS.md`; not a second document. |
| `/partner/llms.txt` | `text/plain; charset=utf-8` | Allowlisted text context. |
| `/partner/ai-context.json` | `application/json` | Allowlisted structured context. |
| `/partner/manifest.json` | `application/json` | Allowlisted public manifest. |
| `/partner/sitemap.xml` | `application/xml; charset=utf-8` | Canonical public URLs only. |
| `/partneropen/go/{link_id}/{placement_id}` | `302` or `404` | Same-origin resolver; redirects only to an allowlisted HTTPS destination and only within the matching Space. |
WordPress rewrite/query variables use the public names `partneropen_space`, `partneropen_asset`, `partneropen_link`, and `partneropen_placement`. They are internal route variables; callers use the public paths above.

The public stylesheet handle is `partneropen-connector-public`; it is enqueued on a rendered Space page. Public agent assets are gated by `agent_pack` and return no-store `404` when that scope is withdrawn.

All outbound links render as `/partneropen/go/{link_id}/{placement_id}` with visible disclosure, visible `Goes to <host>` text inside `partneropen-space__disclosure`, and `rel="sponsored nofollow noopener"`. No public rendered HTML includes a raw external `href`. If `affiliate_service` is withdrawn, link labels remain visible as plain text but no anchor or resolver `href` is emitted.

`AgentPack` contributes text blocks as plain text in generated agent files: sanitized text HTML is stripped of tags and appended as text, so a text block cannot add an anchor or raw external destination to the pack. Agent output uses a positive field allowlist and excludes link destinations, emails, secrets, partner identifiers, and private metadata.

### Global Pause semantics

When `partneropen_pause.owner_paused` is true, every URL in the public table returns `404` with:

```text
Cache-Control: no-store, no-cache, must-revalidate
X-Robots-Tag: noindex
```

The resolver never returns a `302` while paused. Global Pause does not revoke pairing, delete local snapshots, change consent records, or stop signed client requests. Resume restores the latest retained published snapshot when its Space is active. A suspended Space remains a `404` until resumed separately.

## Lifecycle, cron, uninstall, and platform boundary

Activation schedules daily `partneropen_connector_prune_clicks`. Deactivation clears that event. Its callback prunes aggregate click data older than 90 days. `uninstall.php` is guarded by `WP_UNINSTALL_PLUGIN` and deletes connection, consent, secret, pause, Spaces, every `partneropen_snapshot_*` option, clicks, and plugin transients. Optional-scope withdrawal and disconnect do not perform uninstall cleanup; disconnect intentionally retains local snapshots.

This contract is for a single WordPress site. The plugin has no multisite network handling, network-wide consent, network activation semantics, or network-level storage API.

## OMP naming limitation

The external OMP chat session title is not controlled by this repository. All project-facing references in this API contract use **PartnerOpen**; no repository or Vercel setting is represented as changing the external chat title.
