# PartnerOpen Rename Specification

## Purpose and status

This specification freezes the product and technical identity for the clean cutover from the unreleased PartnerPage name to **PartnerOpen**. It is the authoritative naming table for all later implementation, smoke, packaging, deployment, legal, and support work. The existing M1/M2 behavior is unchanged by the rename.

The old identifiers appear below only to define the replacement contract. They are not compatibility instructions or supported runtime paths.

## Authoritative replacement table

| Previous identifier | PartnerOpen identifier |
|---|---|
| `PartnerPage` | `PartnerOpen` |
| `PartnerPage Cloud` | `PartnerOpen Cloud` |
| `PartnerPage Connector` | `PartnerOpen Connector` |
| `partnerpage-connector` | `partneropen-connector` |
| `PartnerPage\\Connector` | `PartnerOpen\\Connector` |
| `partnerpage/v1` | `partneropen/v1` |
| `partnerpage_*` | `partneropen_*` |
| `pp_*` | `partneropen_connector_*` |
| `/partnerpage/go/` | `/partneropen/go/` |
| `@partnerpage/cloud` | `@partneropen/cloud` |
| `partnerpage repository` | `partneropen repository` |
| `partnerpage Vercel project` | `partneropen Vercel project` |
| `partnerpage.dev` | `partneropen.com` |

The same table in the handoff format used by later tasks is:

```text
PartnerPage                     → PartnerOpen
PartnerPage Cloud               → PartnerOpen Cloud
PartnerPage Connector           → PartnerOpen Connector
partnerpage-connector           → partneropen-connector
PartnerPage\\Connector          → PartnerOpen\\Connector
partnerpage/v1                  → partneropen/v1
partnerpage_*                   → partneropen_*
pp_*                            → partneropen_connector_*
/partnerpage/go/                → /partneropen/go/
@partnerpage/cloud              → @partneropen/cloud
partnerpage repository          → partneropen repository
partnerpage Vercel project      → partneropen Vercel project
partnerpage.dev                 → partneropen.com
```

The replacement is a clean cutover. No public compatibility path, old alias, dual read/write path, re-export, migration shim, or legacy runtime identifier is required because the product has no public installations. All existing local smoke fixtures are re-created from the new identifiers; they are not kept as old-name fixtures.

## Product and technical identity

- Product: **PartnerOpen**.
- WordPress plugin display name: **PartnerOpen Connector**.
- Plugin slug, directory, and package: `partneropen-connector`.
- PHP namespace: `PartnerOpen\\Connector\\`.
- REST namespace: `partneropen/v1`.
- Cloud package: `@partneropen/cloud`.
- Cloud application directory: `apps/cloud`.
- Repository: `partneropen`.
- Vercel project: `partneropen`.
- Canonical public site: `https://partneropen.com`.
- Canonical legal pages: `https://partneropen.com/terms` and `https://partneropen.com/privacy`.

The WordPress technical surface uses the `partneropen` prefix throughout: options such as `partneropen_connection`, `partneropen_consent`, `partneropen_secret`, `partneropen_pause`, `partneropen_spaces`, `partneropen_snapshot_{space_id}`, and `partneropen_clicks`; transients such as `partneropen_connector_pair_code` and `partneropen_connector_nonce_{nonce}`; and query variables `partneropen_space`, `partneropen_asset`, `partneropen_link`, and `partneropen_placement`. Signed headers use the `X-PartnerOpen-*` prefix. The public resolver is `/partneropen/go/{link_id}/{placement_id}`.

The authoritative Cloud host list is `partneropen.com` and `www.partneropen.com`. During deployment cutover, `partnerpage-swart.vercel.app` may remain as a clearly labelled temporary fallback so existing deployment access can be verified. It is not a canonical or branded PartnerOpen host and must be removed after PartnerOpen.com DNS and HTTPS are registered and verified. No new `partneropen-swart.vercel.app` hostname is invented.

## Milestone boundary preserved by the rename

### M1 — Connector MVP (implemented)

M1 remains the local-first Connector contract. WordPress stores pairing state, consent records, Spaces, published format-3 snapshots, and aggregate daily click counters in its durable database. M1 includes owner setup and six consent scopes, one-time pairing, the signed `partneropen/v1` API, strict snapshot validation, same-origin resolver links with HTTPS destination allowlisting and disclosure, the consent-gated agent pack, aggregate 90-day counters, Global Pause, and Withdraw consent & disconnect. The Connector makes no outbound HTTP request in M1.

PartnerOpen Cloud remains a stateless public site in M1. Its live surfaces are the landing page, `/api/health`, `/api/consent-scopes`, `/api/connector-manifest`, `/terms`, and `/privacy`. It has no pairing store, tenant backend, hosted editor, publish store, metrics-ingest store, or other durable Cloud state in M1.

### M2 — Cloud tenant backend (deferred; not built)

M2 remains deferred until its named prerequisites exist: a durable managed store (or equivalent), session/token authentication, and transactional email. Potential M2 capabilities include passwordless partner login, tenant/site/Space isolation, a hosted typed page builder, invitations and notifications, Cloud-side metrics storage, billing, and Cloud service/network adapters. No M2 capability is presented as available M1 behavior, and no M2 placeholder is introduced by this rename.

## Rename acceptance checklist

- [ ] Every product-facing reference uses **PartnerOpen**, **PartnerOpen Cloud**, or **PartnerOpen Connector** as applicable.
- [ ] Every technical identifier uses the replacement table, including the PHP namespace, REST namespace, package name, option/transient/query-variable prefixes, signed headers, and resolver route.
- [ ] Canonical examples use `https://partneropen.com`, `https://site.example/wp-json/partneropen/v1`, and `/partneropen/go/{link_id}/{placement_id}`.
- [ ] Canonical legal links use `https://partneropen.com/terms` and `https://partneropen.com/privacy`.
- [ ] Existing local smoke fixtures are re-created from PartnerOpen identifiers, with no old-name fixture retained.
- [ ] No public compatibility path, alias, shim, dual technical identifier, or old runtime route is retained.
- [ ] Any temporary deployment fallback is explicitly labelled non-canonical and is not used as PartnerOpen branding.
- [ ] M1/M2 descriptions remain factual: M1 is local-first and stateless Cloud; M2 is deferred and not implemented.
- [ ] The external OMP chat-session title limitation is documented without claiming that repository or Vercel settings changed it.

## OMP naming limitation

The external OMP chat session title is not controlled by this repository. All project-facing references in repository files, deployment metadata, smoke fixtures, and future work use **PartnerOpen**. This specification does not claim that a repository, GitHub, or Vercel setting can rename the external chat session title.

## Historical-reference rule

Later documentation and implementation work MUST use the PartnerOpen identifiers as current names. An old identifier may remain only in an explicit historical clean-cutover mapping such as the table above, or in the clearly labelled temporary deployment-fallback note. Old identifiers MUST NOT be presented as user-facing runtime instructions, supported aliases, or current canonical branding.
