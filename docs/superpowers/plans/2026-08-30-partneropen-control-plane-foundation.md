# PartnerOpen Control-Plane Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a locally runnable, server-side PartnerOpen control-plane foundation for identity, tenancy, encrypted Connector credentials, authorization, HMAC gateway calls and append-only audit events.

**Architecture:** Keep `apps/cloud` as the existing stateless public landing. Add a separate `apps/control-plane` Next.js service with Postgres-backed repositories and server-only service modules. Keep Connector protocol code in a small shared workspace package; the browser never calls WordPress or receives a Connector secret.

**Tech Stack:** Next.js 16.3.2, React 19, TypeScript, Node `crypto`, `pg`, Postgres 16 in Docker for local integration tests, native `fetch` for the Resend adapter, and Node's built-in test runner.

## Global Constraints

- WordPress remains the authority for published snapshots, consent, Spaces, Global Pause, disconnect, public rendering and aggregate counters.
- The existing HMAC Connector API remains the execution boundary.
- The Connector secret never reaches a browser, cookie, HTML response, client log or external URL.
- Only the server-side Connector gateway signs HMAC requests.
- Raw auth tokens are never stored; email identity is encrypted and indexed only by a keyed lookup hash.
- Request-link abuse protection uses email, ephemeral IP and global buckets without persisting IPs in product data.
- `/auth/verify` sends `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, restrictive same-origin CSP and no external subresources; access logs redact its query string.
- Audit events are append-only to the application role and contain no secrets, tokens, raw bodies, email contents, IPs or visitor data.
- No production no-op, in-memory persistence fallback or fake email delivery is added.
- External provider credentials are not required for local unit tests and are never committed.
- The public landing deployment remains independently deployable from the control plane.

---

### Task 1: Add the control-plane workspace and local runtime

**Files:**
- Create: `apps/control-plane/package.json`
- Create: `apps/control-plane/tsconfig.json`
- Create: `apps/control-plane/next-env.d.ts`
- Create: `apps/control-plane/next.config.ts`
- Create: `apps/control-plane/vercel.json`
- Create: `apps/control-plane/app/layout.tsx`
- Create: `apps/control-plane/app/page.tsx`
- Create: `apps/control-plane/app/api/health/route.ts`
- Create: `apps/control-plane/lib/config.ts`
- Create: `apps/control-plane/tests/config.test.mjs`
- Create: `apps/control-plane/tests/health-route.test.mjs`
- Create: `docker-compose.control-plane.yml`
- Modify: `package.json`
- Modify: `package-lock.json`

**Interfaces:**
- `loadConfig(env: NodeJS.ProcessEnv): ControlPlaneConfig` rejects missing production secrets and returns typed non-secret configuration.
- `GET /api/health` returns `{ "status": "ok", "service": "partneropen-control-plane" }` without touching the database or exposing environment values.
- Local Docker exposes Postgres on `127.0.0.1:55432`; the application reads `DATABASE_URL`.

- [ ] **Step 1: Write failing config and health tests**

```js
assert.throws(() => loadConfig({ NODE_ENV: 'production' }), /DATABASE_URL/);
assert.deepEqual(await GET().then((response) => response.json()), {
  status: 'ok',
  service: 'partneropen-control-plane',
});
```

- [ ] **Step 2: Run the tests and verify the expected missing-module failure**

Run: `node --test apps/control-plane/tests`
Expected: FAIL because the control-plane modules do not exist.

- [ ] **Step 3: Create the workspace, typed config, health route and Docker Postgres service**

Use a separate `@partneropen/control-plane` package. Add the workspace to the root package configuration and install only the dependencies required by this service.

- [ ] **Step 4: Run the tests and build**

Run: `node --test apps/control-plane/tests` and `npm run build:control-plane`.
Expected: PASS and a successful static Next build without requiring `DATABASE_URL` for `/api/health`.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json apps/control-plane docker-compose.control-plane.yml
git commit -m "feat: scaffold control plane runtime"
```

### Task 2: Extract the Connector signing contract

**Files:**
- Create: `packages/connector-protocol/package.json`
- Create: `packages/connector-protocol/src/signature.ts`
- Create: `packages/connector-protocol/src/index.ts`
- Modify: `package.json`
- Modify: `apps/cloud/next.config.ts`
- Modify: `apps/cloud/lib/signature.ts`
- Create: `apps/control-plane/lib/connector/signature.ts`
- Create: `apps/control-plane/tests/signature.test.mjs`
- Modify: `apps/cloud/tests/signature.test.mjs`

**Interfaces:**
- `canonicalString(method, path, timestamp, nonce, body): string` returns the five-line Connector canonical string.
- `sign(method, path, timestamp, nonce, body, secret): string` returns lowercase HMAC-SHA256 hex.
- Existing Cloud signature tests and the new control-plane tests use the same shared implementation.

- [ ] **Step 1: Add shared-package tests that import the desired API**
- [ ] **Step 2: Run signature tests and verify they fail because the package is absent**
- [ ] **Step 3: Implement the shared package and re-export it from Cloud/control plane**
- [ ] **Step 4: Run `node --test apps/cloud/tests/signature.test.mjs apps/control-plane/tests/signature.test.mjs` and verify all cases pass**
- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json packages/connector-protocol apps/cloud/next.config.ts apps/cloud/lib/signature.ts apps/cloud/tests/signature.test.mjs apps/control-plane
git commit -m "refactor: share Connector signing contract"
```

### Task 3: Add Postgres migrations and repository primitives

**Files:**
- Create: `apps/control-plane/db/migrations/001_initial.sql`
- Create: `apps/control-plane/lib/db/client.ts`
- Create: `apps/control-plane/lib/db/migrate.ts`
- Create: `apps/control-plane/scripts/migrate.mjs`
- Create: `apps/control-plane/tests/migration.test.mjs`
- Modify: `apps/control-plane/package.json`

**Interfaces:**
- `getPool(databaseUrl?: string): Pool` returns the process singleton for the configured database.
- `withTransaction<T>(callback: (client: PoolClient) => Promise<T>): Promise<T>` commits on success and rolls back on failure.
- `migrate(pool): Promise<string[]>` applies sorted SQL files once and returns applied migration names.

Migration tables must include `organizations`, `users`, `memberships`, `sites`, `site_memberships`, `spaces`, `space_memberships`, `connection_challenges`, `site_credentials`, `drafts`, `revisions`, `invitations`, `auth_tokens`, `sessions` and `audit_events`. Use UUIDs, UTC `timestamptz`, foreign keys, tenant indexes and unique constraints. `audit_events` has no update/delete repository operation.

- [ ] **Step 1: Write migration integration tests against `DATABASE_URL`**
- [ ] **Step 2: Start local Postgres and run the migration test to verify the schema is absent**

```bash
docker compose -f docker-compose.control-plane.yml up -d postgres
DATABASE_URL=postgres://partneropen:partneropen@127.0.0.1:55432/partneropen_control npm run test:control-plane -- --test-name-pattern migration
```

Expected: FAIL because the migration runner and schema do not exist.

- [ ] **Step 3: Implement the migration runner, pool and complete SQL schema**
- [ ] **Step 4: Run the migration test twice and verify the second run is idempotent**
- [ ] **Step 5: Commit**

```bash
git add apps/control-plane
 git commit -m "feat: add control plane Postgres schema"
```

### Task 4: Implement crypto, token hashing and ephemeral throttling

**Files:**
- Create: `apps/control-plane/lib/security/crypto.ts`
- Create: `apps/control-plane/lib/security/redaction.ts`
- Create: `apps/control-plane/lib/auth/rate-limit.ts`
- Create: `apps/control-plane/lib/auth/verify-headers.ts`
- Create: `apps/control-plane/tests/security.test.mjs`
- Create: `apps/control-plane/tests/rate-limit.test.mjs`

**Interfaces:**
- `encryptSecret(plaintext, keyHex, keyVersion): EncryptedSecret` uses AES-256-GCM.
- `decryptSecret(value, keyHex): string` rejects an invalid key version, IV, tag or authentication tag.
- `hashOpaqueToken(token, pepper): string` returns a keyed SHA-256 digest.
- `normalizeEmail(email): string` trims and lowercases without provider-specific alias rewriting.
- `hashEmailLookup(email, pepper): string` returns the keyed lookup digest.
- `EphemeralRateLimiter.consume(bucket, limit, windowMs, nowMs?): RateLimitResult` keeps email/IP/global buckets in memory only and prunes expired entries.
- `verifySecurityHeaders(): Headers` returns the no-store/no-referrer/CSP header set; CSP permits only same-origin resources.
- `redactSensitive(value): unknown` removes token, secret, authorization, cookie and query-string fields from structured logs.

- [ ] **Step 1: Write failing crypto and limiter tests**
- [ ] **Step 2: Run tests and verify failures cover missing exports and abuse behavior**
- [ ] **Step 3: Implement native Node crypto and bounded ephemeral buckets**
- [ ] **Step 4: Run tests and verify round-trip encryption, tamper rejection, token non-reversibility, limit windows, pruning and headers**
- [ ] **Step 5: Commit**

```bash
git add apps/control-plane
 git commit -m "feat: add control plane security primitives"
```

### Task 5: Add auth repositories, delivery adapter and session lifecycle

**Files:**
- Create: `apps/control-plane/lib/auth/repository.ts`
- Create: `apps/control-plane/lib/auth/service.ts`
- Create: `apps/control-plane/lib/auth/session.ts`
- Create: `apps/control-plane/lib/email/delivery.ts`
- Create: `apps/control-plane/lib/email/resend.ts`
- Create: `apps/control-plane/app/api/auth/request-link/route.ts`
- Create: `apps/control-plane/app/auth/verify/route.ts`
- Create: `apps/control-plane/app/api/auth/session/route.ts`
- Create: `apps/control-plane/app/api/auth/logout/route.ts`
- Create: `apps/control-plane/tests/auth-service.test.mjs`
- Create: `apps/control-plane/tests/auth-route-contract.test.mjs`

**Interfaces:**
- `EmailDelivery.sendMagicLink(input): Promise<void>` is the only email boundary. The production Resend adapter fails closed when its API key or sender is absent; tests inject a recording delivery implementation.
- `requestMagicLink(input, dependencies): Promise<void>` applies email/IP/global throttles, creates a hashed token and sends the link without account enumeration.
- `consumeMagicLink(token, dependencies): Promise<Session>` atomically consumes the token and creates a rotated session.
- `createSessionCookie(sessionToken, secure): string` emits Secure/HttpOnly/SameSite cookie attributes.

Required route behavior:

- request-link returns generic `202` for valid-looking input and does not reveal account existence;
- verify never renders external resources, sets no-store/no-referrer/CSP headers, redacts the query string in request logging and redirects with `303` to a token-free URL;
- logout revokes the server session and clears the cookie;
- malformed, expired and reused tokens return the same generic failure class.

- [ ] **Step 1: Write service and route contract tests with injected database/delivery boundaries**
- [ ] **Step 2: Run tests and verify failures occur before implementation**
- [ ] **Step 3: Implement SQL repositories, auth service, Resend adapter and routes**
- [ ] **Step 4: Run auth tests against local Postgres and verify replay, enumeration, throttling, cookie and header behavior**
- [ ] **Step 5: Commit**

```bash
git add apps/control-plane
git commit -m "feat: add passwordless control plane auth"
```

### Task 6: Add tenancy authorization and Connector gateway

**Files:**
- Create: `apps/control-plane/lib/authorization/permissions.ts`
- Create: `apps/control-plane/lib/authorization/context.ts`
- Create: `apps/control-plane/lib/connector/gateway.ts`
- Create: `apps/control-plane/lib/connector/types.ts`
- Create: `apps/control-plane/lib/audit/service.ts`
- Create: `apps/control-plane/tests/authorization.test.mjs`
- Create: `apps/control-plane/tests/gateway.test.mjs`
- Create: `apps/control-plane/tests/audit.test.mjs`

**Interfaces:**
- `authorize(context, operation, resource): AuthorizationResult` enforces `internal_admin`, `site_admin` and `creator` permissions across organization/site/space grants.
- `ConnectorGateway` exposes `pairSite`, `getSiteStatus`, `listSpaces`, `publishSnapshot`, `readMetrics`, `pauseSite`, `resumeSite` and `disconnectSite`.
- `ConnectorGateway` accepts injected `fetch`, clock, nonce factory and credential store for deterministic tests; production credentials are loaded only inside the gateway.
- `appendAuditEvent(event): Promise<void>` validates safe metadata, inserts once by event/correlation ID and has no update/delete method.

- [ ] **Step 1: Write the role matrix, secret-boundary and audit idempotency tests**
- [ ] **Step 2: Run tests and verify unauthorized cross-tenant calls and gateway behavior fail before implementation**
- [ ] **Step 3: Implement authorization, gateway HMAC calls and append-only audit insertion**
- [ ] **Step 4: Run tests and verify no gateway response contains the decrypted credential, stale/revoked credentials fail, WordPress failures are not reported as success and duplicate events are ignored**
- [ ] **Step 5: Commit**

```bash
git add apps/control-plane
git commit -m "feat: add tenant authorization and Connector gateway"
```

### Task 7: Add local verification, environment documentation and final checks

**Files:**
- Create: `apps/control-plane/.env.example`
- Create: `apps/control-plane/README.md`
- Create: `apps/control-plane/tests/index.js`
- Modify: `package.json`
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-08-30-partneropen-control-plane-foundation-design.md`

**Interfaces:**
- `npm run dev:control-plane` starts the separate app.
- `npm run build:control-plane` builds it without external provider credentials.
- `npm run migrate:control-plane` applies SQL migrations using `DATABASE_URL`.
- `npm run test:control-plane` runs all control-plane tests.

Document exactly which environment variables are required for local, staging and production. State that provider credentials are prerequisites and no no-op fallback exists.

- [ ] **Step 1: Add commands, env example, README and test index**
- [ ] **Step 2: Run the full Cloud and control-plane test suites against local Postgres**
- [ ] **Step 3: Build both Next apps and run `git diff --check`**
- [ ] **Step 4: Run a local health smoke and migration idempotency smoke**
- [ ] **Step 5: Commit**

```bash
git add package.json README.md apps/control-plane docs/superpowers/specs/2026-08-30-partneropen-control-plane-foundation-design.md
git commit -m "docs: add control plane local operations"
```

## Verification checkpoint

Run from the repository root:

```bash
npm run test:cloud
npm run test:control-plane
npm run build:cloud
npm run build:control-plane
```

With local Postgres running:

```bash
DATABASE_URL=postgres://partneropen:partneropen@127.0.0.1:55432/partneropen_control npm run migrate:control-plane
DATABASE_URL=postgres://partneropen:partneropen@127.0.0.1:55432/partneropen_control npm run migrate:control-plane
```

Expected: both migration invocations succeed; the second applies zero migrations. Production deployment is intentionally deferred until Neon, Resend, DNS, encryption secrets and staging WordPress are available.
