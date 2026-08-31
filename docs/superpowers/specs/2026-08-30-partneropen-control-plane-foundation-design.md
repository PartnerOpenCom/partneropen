# PartnerOpen Control-Plane Foundation Design

**Date:** 2026-08-30  
**Status:** Approved design direction  
**Scope:** The first tooling vertical: a separate, server-side PartnerOpen Cloud control-plane foundation.

## Goal

Build the stateful security and data foundation required for PartnerOpen GUI, MCP publication, passwordless Creator access, Site administration and later M2 network distribution without moving published runtime authority out of WordPress.

## Product boundary

The existing `apps/cloud` deployment remains a public, stateless landing and evidence surface. The new `apps/control-plane` service owns identity, authorization, connection metadata, drafts/revisions, encrypted credential references and administrative events. It is a separate deployment and cannot be implemented by adding browser calls to the landing app.

The WordPress Connector remains authoritative for:

- consent and consent withdrawal;
- pairing state and the Connector secret;
- Spaces and published snapshots;
- public rendering and same-origin resolver behavior;
- Global Pause and disconnect;
- aggregate placement counters.

The control plane can request an operation, but it cannot bypass a WordPress consent decision, Global Pause, disconnect, snapshot validation or the signed REST contract.

## Architecture

```text
Creator / Site admin / Internal admin
                    |
                    v
          apps/control-plane UX/API
                    |
          authorization + service layer
                    |
          server-side Connector gateway
                    |
          WordPress Connector REST API
                    |
       WordPress durable published authority
```

### Services

1. **Control-plane web/API** — Next.js server runtime with route handlers and server-only service modules.
2. **Postgres** — durable identity, tenancy, access, connection, draft, revision and audit state.
3. **Magic-link delivery adapter** — transactional email provider; the first production adapter targets Resend through a server-only API key.
4. **Credential encryption module** — envelope encryption with a versioned application key; the first production deployment uses a 32-byte key from a server-only secret and can later delegate wrapping to KMS.
5. **Connector gateway** — the only module permitted to load/decrypt a Site credential and sign HMAC requests.
6. **Retention worker** — scheduled deletion of expired auth tokens and audit events older than 180 days; normal request handlers cannot mutate audit rows.

The first managed database target is Neon-compatible Postgres through a standard `DATABASE_URL`. Provider-specific SDK calls are prohibited in domain code.

## Identity and authentication

### Tables

```text
users
  id, email_ciphertext, email_lookup_hash, email_key_version,
  status, created_at, verified_at

auth_tokens
  id, user_id, token_hash, purpose, expires_at, used_at, created_at

sessions
  id, user_id, session_token_hash, expires_at, revoked_at,
  created_at, last_seen_at
```

Email is required for invitations and magic links, so the product stores the minimum necessary identity data: encrypted email for delivery plus a keyed lookup hash for uniqueness and lookup. Raw tokens are never stored. Token hashes use a server-side pepper and constant-time comparison.

### Request-link abuse controls

`POST /api/auth/request-link` must resist both account enumeration and mass email bombing:

- always returns the same `202` response for valid-looking requests;
- applies a bucket keyed by email lookup hash;
- applies a separate ephemeral bucket keyed by client IP;
- applies an ephemeral global/application bucket and the email-provider quota;
- IP and rate-limit keys are held only by the edge or process-local throttler and are never written to Postgres, audit events or application logs;
- rate-limit rejection uses the same outward response as an accepted request;
- email delivery is queued only after all abuse checks pass.

The edge bucket is the authoritative distributed limiter. A process-local limiter is a defense in depth for local development and an outage fallback; it is not treated as a durable security boundary.

### Verify flow

`GET /auth/verify?token=...`:

1. responds with `Cache-Control: no-store` and `Referrer-Policy: no-referrer`;
2. has no external scripts, images, stylesheets, fonts, analytics or other external subresources;
3. hashes and atomically consumes the one-use token before creating a session;
4. rejects expired, used or malformed tokens without revealing account state;
5. sets a Secure, HttpOnly, SameSite session cookie;
6. redirects to a token-free application URL with `303 See Other`.

The token is never placed in a client-side analytics payload, outbound URL, HTML attribute, log line, access-log query string or `Referer` header. Edge and application access logs must redact the complete query string for `/auth/verify` before persistence. The verify response also sends a restrictive same-origin Content Security Policy and `X-Content-Type-Options: nosniff`.

Sessions are server-revocable, short-lived, rotated after verification and invalidated on logout or security action. Frontend route hiding is not authorization.

## Roles and tenancy

The hierarchy is:

```text
Organization → Site → Space → User access
```

Roles:

- `internal_admin` — network-wide operational visibility;
- `site_admin` — owned Site administration;
- `creator` — only assigned Site/Space publication work.

Every tenant-owned row carries `organization_id`. Every service mutation receives an authorization context and checks organization membership, Site grant, optional Space grant and operation permission. A Creator cannot read credentials or execute Site connection, consent, pause, resume or disconnect actions.

## Connector gateway

The gateway exposes typed server-only operations:

```text
pairSite(connectionInput)
getSiteStatus(siteId)
listSpaces(siteId)
publishSnapshot(siteId, spaceId, revision)
readMetrics(siteId)
pauseSite(siteId)
resumeSite(siteId)
disconnectSite(siteId)
```

Each operation:

1. authorizes the caller;
2. loads the Site record and credential reference;
3. decrypts the credential only inside the gateway process;
4. validates the configured HTTPS Site origin;
5. signs the existing HMAC request with Site identity, timestamp, nonce and required consent scope;
6. sends the request server-side;
7. maps the confirmed WordPress response into a typed result;
8. emits an administrative event without secrets or raw bodies.

The browser never receives the Connector secret, signs a request or calls a WordPress REST route directly. A failed or disconnected WordPress response cannot be reported as a successful publish.

The first connection path supports the existing one-time pairing code for compatibility. The later challenge handshake is an additive Connector/API change and is not faked with a browser-held secret.

## Credential encryption

```text
site_credentials
  id, site_id, ciphertext, iv, auth_tag, key_version,
  created_at, rotated_at, revoked_at
```

- plaintext credentials exist only for the duration of a gateway operation;
- database access roles cannot select decrypted values;
- encryption keys are never stored in Postgres;
- rotation writes a new ciphertext and key version before revoking the old reference;
- revocation is checked before every gateway operation;
- credentials, tokens and secret-shaped values are redacted from structured logs and error payloads.

## Durable data model

The first schema contains:

```text
organizations
users
memberships
sites
site_memberships
spaces
space_memberships
connection_challenges
site_credentials
drafts
revisions
invitations
auth_tokens
sessions
audit_events
```

Published content remains authoritative in WordPress. Cloud drafts and revisions include revision ID, content hash, author, validation result, expected current revision and publish response metadata. Publishing uses optimistic concurrency and rejects stale revisions rather than overwriting a newer draft.

No table stores visitor identifiers, raw click events, IP addresses, cookies, fingerprints, raw request bodies, Connector secrets or email contents in audit metadata. Aggregate counters remain in WordPress under the existing `aggregate_metrics` consent scope.

## Audit events

```text
audit_events
  event_id              UUID primary key
  organization_id       UUID not null
  site_id               UUID nullable
  space_id              UUID nullable
  actor_id              UUID nullable
  actor_role            text nullable
  source                text not null
  action                text not null
  result                text not null
  created_at             timestamptz not null
  correlation_id        UUID not null
  safe_metadata         jsonb not null
```

Initial actions include:

```text
site.created
site.connection_started
site.connected
site.connection_failed
creator.invited
creator.invitation_accepted
creator.access_granted
creator.access_revoked
snapshot.drafted
snapshot.revised
snapshot.published
snapshot.publish_failed
consent.changed
space.suspended
space.resumed
global_pause.changed
site.disconnected
api_request.failed
```

The application database role can insert events but cannot update or delete them. A separate retention job deletes rows older than 180 days. Idempotency is enforced by `event_id` and `correlation_id`.

## First API surface

```text
POST /api/auth/request-link
GET  /auth/verify
GET  /api/auth/session
POST /api/auth/logout

POST /api/organizations
GET  /api/organizations/{organizationId}/sites

POST /api/site-connections
GET  /api/sites/{siteId}
GET  /api/sites/{siteId}/events
```

The API uses generic errors for authentication and invitation flows. Authorization failures do not disclose whether another organization, Site or user exists. Destructive operations require explicit confirmation and show the expected WordPress effect before execution.

## Rollout

1. **Runtime bootstrap** — create the separate app, Postgres connection adapter, migration runner, server-only environment validation and structured redacted logging.
2. **Crypto and auth** — implement encrypted email/credential primitives, token hashing, request-link abuse limits, verify response headers, session lifecycle and auth tests.
3. **Tenancy and authorization** — add organizations, memberships, Site grants, Space grants and an authorization matrix tested for every role.
4. **Site registry and gateway** — add pending/connected/disconnected Sites, compatibility pairing, encrypted credential references and server-only signed Connector calls.
5. **Audit and retention** — add append-only events, idempotency, safe metadata allowlist and the 180-day retention worker.
6. **Staging acceptance** — connect a disposable WordPress site, exercise pairing/status/publish/pause/disconnect, verify no secret/browser exposure and run failure-path tests.
7. **Production enablement** — provision `app.partneropen.com`, Neon-compatible Postgres, Resend, encryption/session secrets and rate-limit edge configuration; deploy only after staging acceptance.

GUI/editor, full Creator draft UX, event synchronization from WordPress and multi-Host network distribution consume this foundation in later verticals. They are not implemented as fake placeholders in the foundation slice.

## Acceptance criteria

- A request-link endpoint cannot be used to enumerate accounts or send unlimited mail across many addresses; email, ephemeral IP and global buckets all apply without persisting IPs.
- A verification response sends `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, restrictive CSP and no external subresources; the token is consumed once and never appears in the redirected URL.
- Raw email tokens and session tokens are absent from database rows and logs.
- A Creator cannot cross organization, Site or Space boundaries.
- No browser response, cookie, HTML payload or client log contains a Connector secret.
- Only the server-side gateway signs Connector HMAC requests.
- A disconnected, paused, consent-denied or invalid WordPress response cannot be represented as a successful publish.
- Audit rows are append-only to the application role, idempotent and free of secrets, raw bodies, email contents and visitor data.
- WordPress remains the source of truth for published snapshots, consent, Global Pause, disconnect and aggregate metrics.
- The stateless landing deployment remains deployable independently from the control plane.
- Staging exercises both successful and failed gateway paths against the real Connector REST contract.

## Required production inputs

- managed Neon-compatible Postgres connection;
- transactional email provider credentials and verified sender domain;
- `CONTROL_PLANE_ENCRYPTION_KEY`;
- `CONTROL_PLANE_TOKEN_PEPPER`;
- session signing/pepper secret;
- edge/global rate-limit configuration;
- `app.partneropen.com` DNS and HTTPS;
- disposable staging WordPress site with the PartnerOpen Connector installed;
- updated privacy notice covering encrypted account email, sessions and invitation data.
