# PartnerOpen Network Admin Control Plane Design

**Date:** 2026-08-30  
**Status:** Approved design direction  
**Scope:** External network UX for WordPress Sites, Site admins, Creators, publication, and administrative events.

## Context

The current PartnerOpen Connector is WordPress-first and local-first:

- WordPress stores the connection, consent, Spaces, published snapshots, and aggregate counters.
- The WordPress admin is the owner surface.
- The Connector exposes a signed REST API and public Space routes.
The current Cloud application is a stateless landing and reference-client surface. The approved landing-release product decision describes PartnerOpen GUI, the MCP Connector, Site-admin email invitations and passwordless magic-link Creator access as available publication/access paths; this document specifies the stateful control-plane implementation behind those paths.
The WordPress Connector itself remains local-first and does not own the external editor, identity or invitation service. Those responsibilities belong to PartnerOpen Cloud and are separate from the Connector package.

## Availability decision reconciliation

The landing redesign's availability decision supersedes this document's earlier baseline wording that GUI, MCP, email invitations and Creator login were not yet provisioned. This is an earlier product decision versus the network-admin design baseline, not a copy defect. The stateful multi-Host network administration and distribution package remains `MILESTONE 2 / BY REQUEST`, while the public landing is required to present the approved GUI, MCP and passwordless invitation paths as available now.

An external network UX therefore requires a stateful Cloud control plane. It must add identity, organizations, access control, connection lifecycle, drafts/revisions, audit events, and a server-side bridge to WordPress. It must not move the published WordPress authority or expose Connector secrets to browsers.

## Decisions

1. **No OAuth for user or site connection.** User access uses the existing passwordless magic-link model. Site connection uses a one-time challenge and explicit Host approval, not an OAuth provider.
2. **External UX owns network administration.** A Site admin adds Creator email addresses, assigns Site/Space access, and manages the assigned network from PartnerOpen Cloud.
3. **Creator access is resource-scoped.** A Creator can see and modify only Sites and Spaces granted by membership records.
4. **Full Creator publication is supported.** Creator can create drafts, edit revisions, and publish validated snapshots through the Cloud server. Site-level destructive controls remain outside Creator permissions.
5. **The hierarchy is `Organization → Site → Space → User access`.** Internal admins can see the whole network; Site admins are scoped to owned Sites; Creators are scoped to assigned Sites/Spaces.
6. **WordPress remains authoritative for published runtime state.** Cloud stores drafts, revisions, connection metadata, and audit events. WordPress remains authoritative for published snapshots, consent, Global Pause, disconnect, and public rendering.
7. **No visitor telemetry is added.** Administrative events are allowed; IPs, cookies, fingerprints, visitor IDs, and raw visitor activity remain excluded.
8. **The current HMAC Connector API remains the site-to-site data protocol.** The network edition adds a challenge handshake and event synchronization without exposing a secret to the browser.

## Roles and permissions

| Capability | Internal admin | Site admin | Creator |
|---|---:|---:|---:|
| See all organizations and Sites | Yes | No | No |
| See assigned Site status and consent | Yes | Yes | Read-only for assigned resources |
| Register/connect a Site | Yes | Yes, for owned Site | No |
| Invite/remove Creators | Yes | Yes, for owned Site | No |
| Assign Space access | Yes | Yes, for owned Site | No |
| Create/edit drafts | Yes | Yes | Yes, for assigned Space |
| Publish a snapshot | Yes | Yes | Yes, for assigned Space |
| See publication history | Yes | Yes | Assigned Space only |
| Pause/resume public Site | Yes | Yes, with confirmation | No |
| Change consent or disconnect | Yes | Yes, with explicit confirmation | No |
| Read Connector secret | Never in browser; server-side only | Never | Never |

A Site admin is the external representation of the WordPress owner for a connected Site. WordPress `manage_options` authorization and the local Connector controls remain the final authority. Cloud cannot publish through a disconnected Site, bypass Global Pause, or override a local consent decision.

## Authentication and invitations

All external users use passwordless magic links. The implementation stores only a hash of the invitation or session token, never the raw token.

### Creator invitation

1. Site admin opens a Site in the external UX.
2. Site admin enters the Creator email and selects one or more Spaces.
3. Cloud creates an invitation with organization, Site/Space grants, inviter, expiry, and a one-use token hash.
4. The existing magic-link delivery sends the Creator a short-lived link.
5. Creator follows the link, verifies the email session, and receives exactly the assigned permissions.
6. Accepting or declining the invitation creates an audit event. Reusing or expiring the link is rejected.

The Creator never receives the WordPress Connector secret. Removing the membership immediately blocks future Cloud operations; in-flight publish requests are checked again at the server boundary.

### Site admin connection

The Site admin starts from the external UX:

1. External UX creates a `pending` Site record and a short-lived one-time connection challenge.
2. It provides an install link and connection instructions for the Connector.
3. The Host opens the Connector connection screen in WordPress and confirms the Site URL, requested scopes, legal notices, and connection.
4. The Connector exchanges the challenge over HTTPS only after explicit Host approval.
5. Cloud validates the Site identity and Connector version, stores the credential in encrypted server-side storage, and reads `/status`.
6. Cloud marks the Site connected and allows the Site admin to invite Creators.

The challenge is one-use and expires quickly. The browser never handles the long-lived Site credential. The existing manual pairing-code flow remains available as a compatibility path, but the external UX uses the challenge flow.

This is an intentional extension of the current local-first boundary: the Connector still makes no unsolicited outbound requests. A connection handshake is the only new outbound operation, and it is gated by the Host's explicit `cloud_connection` approval.

## Cloud control-plane components

1. **Identity/session layer** — passwordless magic-link sessions for internal admins, Site admins, and Creators.
2. **Organization and membership service** — organizations, users, role memberships, Site grants, and Space grants.
3. **Site connection registry** — pending/connected/disconnected state, connector version, scopes, prefix, last seen, and encrypted credential reference.
4. **Draft and revision service** — editable content before publication, immutable revision records, author, timestamps, and publish state.
5. **WordPress connector gateway** — server-side HMAC signing and calls to the existing Connector API. It is the only component allowed to use a Site credential.
6. **Event ingestion and audit service** — normalized append-only administrative events with actor and correlation IDs.
7. **External UX** — network overview, Site detail, Creator access, editor, publish history, and event timeline.

The existing Vercel landing remains a public surface. The control plane requires a persistent database and server-side runtime; it is not implemented by adding browser calls to the current static landing page.

## Data model

The first database schema contains:

- `organizations`: organization identity and status.
- `users`: verified email identity and account status.
- `memberships`: user-to-organization role (`internal_admin`, `site_admin`, or `creator`).
- `sites`: organization, canonical URL, WordPress site ID, status, Connector version, prefix, and last-seen data.
- `site_memberships`: Site-level grants for Site admins and Creators.
- `spaces`: Cloud mirror of Space identity and status; published content remains authoritative in WordPress.
- `space_memberships`: optional narrower Creator grants for individual Spaces.
- `connection_challenges`: one-use challenge hash, Site reference, expiry, and consumed timestamp.
- `site_credentials`: encrypted reference to the Connector secret, key version, rotation metadata, and revocation state.
- `drafts`: current editable document owned by a Site/Space.
- `revisions`: immutable draft and publish revisions with author, snapshot hash, validation result, and publish response.
- `invitations`: email hash, role, Site/Space grants, expiry, status, inviter, and accepted timestamp.
- `audit_events`: append-only Cloud events with actor, resource, action, result, timestamp, and correlation ID.

No table stores visitor identifiers or raw click events. Aggregate click totals remain in WordPress and are read through the existing consent-gated metrics route.

## Publication flow

```text
Creator magic-link session
        ↓
Cloud API permission check
        ↓
Draft/revision saved in Cloud
        ↓
Creator clicks Publish
        ↓
Cloud validates organization, Site, Space, and revision access
        ↓
Cloud gateway signs PUT /spaces/{space}/snapshot
        ↓
WordPress validates snapshot and consent
        ↓
WordPress stores and renders the published snapshot
        ↓
Cloud records publish result and revision metadata
```

The snapshot format remains version 3 for the first network release. The Connector still rejects unknown fields, invalid block structures, disallowed destinations, invalid origins, and unsupported versions.

Publishing must use optimistic concurrency:

- every draft has a revision ID and content hash;
- the publish request includes the expected current revision;
- a stale revision is rejected instead of silently overwriting a newer draft;
- the resulting WordPress `snapshot_version` and response are stored with the revision;
- a failed request produces an error event without changing the published state.

## Administrative events and logs

The external UX exposes an audit timeline, not visitor analytics. Initial event types are:

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

Each event contains:

```text
event_id
organization_id
site_id
space_id (nullable)
actor_id (nullable for system events)
actor_role
source (cloud, wordpress, connector)
action
result
created_at
correlation_id
safe metadata
```

Secrets, tokens, raw request bodies, email contents, IP addresses, and visitor-level activity are excluded. Cloud audit events are retained for 180 days by default. The WordPress Connector maintains a bounded local event queue for events created while Cloud is unavailable, with cursor-based delivery and a 500-event cap; Cloud remains the long-term audit surface.

The Connector adds a signed event-read route:

```text
GET /wp-json/partneropen/v1/events?cursor=<opaque-cursor>
```

The route returns normalized administrative events only. It does not expose the Connector secret or visitor metrics. Cloud acknowledges the cursor after durable storage and de-duplicates by `event_id`.

## API boundaries

### External Cloud API

The browser uses the Cloud API with the magic-link session cookie. Every mutation is authorized again on the server against organization, Site, Space, and role memberships.

Initial resource groups:

```text
POST   /api/site-connections
POST   /api/site-connections/{id}/complete
GET    /api/organizations/{id}/sites
GET    /api/sites/{id}
POST   /api/sites/{id}/invitations
DELETE /api/sites/{id}/members/{userId}
GET    /api/sites/{id}/events
GET    /api/spaces/{id}/draft
PUT    /api/spaces/{id}/draft
POST   /api/spaces/{id}/publish
GET    /api/spaces/{id}/revisions
```

The external API never returns a WordPress secret to a browser. Site actions are executed by the server-side Connector gateway.

### WordPress Connector API

The existing routes remain the execution boundary:

```text
POST /wp-json/partneropen/v1/pair
GET  /wp-json/partneropen/v1/status
GET  /wp-json/partneropen/v1/spaces
PUT  /wp-json/partneropen/v1/spaces/{space}/snapshot
POST /wp-json/partneropen/v1/spaces/{space}/suspend
POST /wp-json/partneropen/v1/spaces/{space}/resume
GET  /wp-json/partneropen/v1/metrics
POST /wp-json/partneropen/v1/disconnect
GET  /wp-json/partneropen/v1/events
```

All routes except initial pairing and the explicitly approved handshake use the existing HMAC headers and consent checks. Cloud signs requests server-side with the stored Site credential. The browser never signs or forwards a Connector secret.

## External UX

### Network overview

Shows organizations, Sites, connection state, published Space count, last sync, and unresolved failures. Internal admins can switch organizations; Site admins see only assigned Sites.

### Site detail

Shows connection facts, consent scopes, Spaces, Creator memberships, publish health, and the administrative event timeline. Destructive actions require an explicit confirmation and display the WordPress effect before execution.

### Creator access

The Site admin enters an email, selects Site/Space grants, and sends or revokes an invitation. Existing invitations show expiry and acceptance state.

### Space editor

The Creator edits supported snapshot blocks, saves drafts, sees validation errors before publishing, reviews revisions, and publishes. The editor does not allow arbitrary server-side code or raw external destinations outside the snapshot validation rules.

### Event timeline

Filters by Site, Space, actor, action, result, and date. The UI clearly labels Cloud actions, WordPress-originated actions, and system events.

## Security and failure handling

- Magic-link tokens are hashed, short-lived, single-use, rate-limited, and invalidated after acceptance.
- Sessions use secure, HttpOnly, same-site cookies with server-side expiry and revocation.
- Authorization is enforced on every Cloud request; frontend hiding is not a security boundary.
- Site credentials are encrypted at rest using a managed key; key version and rotation state are tracked.
- Connector HMAC timestamp, nonce, scope, and site identity checks remain mandatory.
- Global Pause, disconnect, revoked consent, suspended Spaces, and invalid snapshots are authoritative WordPress responses.
- Cloud marks a Site degraded after repeated gateway failures but does not claim publication succeeded without a confirmed Connector response.
- Connection challenges and invitation links are redacted from application logs and expire even if unused.
- Audit writes are idempotent by event ID and correlation ID.
- Cloud stores no visitor-level analytics and does not turn aggregate counters into user profiles.

## Rollout order

1. Add persistent Cloud runtime, magic-link sessions, organizations, users, memberships, and Site registry.
2. Add pending Site onboarding and explicit WordPress challenge handshake.
3. Add server-side Connector gateway using encrypted credentials.
4. Add Creator invitations, Site/Space permissions, drafts, revisions, and publish.
5. Add Connector event queue, signed event-read route, Cloud audit ingestion, and timeline.
6. Add Site health, retry visibility, credential rotation, and operational alerts.

The current landing remains deployable throughout. Existing manually paired Connector sites continue using the current API until they opt into Cloud registration.

## Acceptance criteria

- A Site admin can create a pending Site in external UX and complete connection after explicit WordPress approval without OAuth.
- A Site admin can invite a Creator by email and the Creator can enter only through a valid magic link.
- A Creator can see only assigned Sites/Spaces and can create, edit, revise, validate, and publish a snapshot.
- A Creator cannot read a Connector secret or execute Site-level connection, consent, pause, or disconnect actions.
- Internal admins can inspect the full organization network and event timeline.
- Published snapshots are confirmed by the WordPress Connector and remain governed by WordPress consent and Global Pause.
- Host-originated and Cloud-originated administrative events appear in the external timeline with actor, source, result, and correlation ID.
- Replayed magic links, connection challenges, HMAC requests, and event deliveries are rejected or de-duplicated.
- No visitor identifiers, raw request bodies, secrets, or visitor-level click events enter the Cloud data model.
