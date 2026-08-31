# PartnerOpen Control Plane

This is the separate server-side home for the stateful M2 tooling foundation. It must not be folded into the public landing app.

## Current implementation

The current slice includes:

- a standalone Next.js runtime on port `3001`;
- fail-closed environment validation in `lib/config.ts`;
- a public `GET /api/health` route with `Cache-Control: no-store`;
- the shared Connector HMAC signing contract through `@partneropen/connector-protocol`.

The approved design for Postgres migrations, passwordless authentication, tenant authorization, encrypted credentials, Connector gateway operations and append-only audit events is in [`../../docs/superpowers/specs/2026-08-30-partneropen-control-plane-foundation-design.md`](../../docs/superpowers/specs/2026-08-30-partneropen-control-plane-foundation-design.md). Those modules are not represented as fake or in-memory implementations here.

## Local commands

From the repository root:

```bash
npm install
npm run dev:control-plane
curl -fsS http://localhost:3001/api/health
npm run test:control-plane
npm run build:control-plane
```

Local Postgres is reserved for the next implementation slice:

```bash
docker compose -f docker-compose.control-plane.yml up -d postgres
```

No production code uses this database until `DATABASE_URL` is configured. Do not put real credentials in the repository; copy the names from [`.env.example`](./.env.example) into a secret manager or an untracked local environment file.

## Deployment boundary

Create a separate Vercel project with root directory `apps/control-plane`. The public landing remains `apps/cloud` in the existing `mdrss/partneropen` project. Control-plane deployment requires managed Postgres, encryption secrets, auth pepper and transactional email credentials; there is no no-op provider fallback.
