# PartnerOpen — Product Handoff

**Snapshot:** `feat/prm-endpoint-landing` (source tree at archive creation)
**Repository:** https://github.com/NikNovo/partneropen
**Prepared for:** engineering handoff
**Product:** PartnerOpen

This document is the index for the source archive. The archive contains the tracked product source, WordPress Connector, public Vercel landing, technical specifications, tests, deployment notes and the first control-plane runtime slice. It intentionally excludes local credentials, dependency directories and build output.

## Product in one page

PartnerOpen is a neutral delegated-Space publishing system:

- A Host installs the GPL WordPress Connector and keeps local authority over consent, Spaces, snapshots, public rendering, Global Pause and disconnect.
- A Creator publishes approved Space content through the signed API, MCP Connector or PartnerOpen GUI path described by the current landing decision.
- PartnerOpen exposes aggregate placement counters, not visitor-level tracking.
- Commercial terms, payments, settlement and legal agreements remain between the Host and Creator; PartnerOpen does not process payments.
- The existing WordPress Connector is the M1 durable authority. The Cloud landing is stateless.

## Repository map

| Area | Path | Status |
| --- | --- | --- |
| WordPress Connector | `plugins/partneropen-connector/` | M1 implementation: pairing, scoped consent, Spaces, snapshots, rendering, resolver, aggregate counters, pause, disconnect and admin UI |
| Public Vercel landing | `apps/cloud/` | Implemented stateless Next.js site, legal pages, public manifest/consent/health APIs and signed reference client |
| Control-plane runtime | `apps/control-plane/` | First foundation slice: standalone Next runtime, fail-closed config, health route and shared signing import |
| Shared Connector protocol | `packages/connector-protocol/` | Canonical HMAC-SHA256 signing implementation shared by Cloud and control plane |
| Connector API contract | `docs/connector-api.md` | REST routes, HMAC headers, scopes and M1 boundary |
| Snapshot contract | `docs/snapshot-schema.json` | Typed Space snapshot schema |
| Product/brand/privacy | `docs/brand.md`, `distribution/privacy-notice.md`, `distribution/terms-of-use.md` | Copy, disclosure, data boundary and legal source material |
| Approved control-plane design | `docs/superpowers/specs/2026-08-30-partneropen-control-plane-foundation-design.md` | Identity, tenancy, auth, encryption, gateway, audit and rollout design |
| Network administration design | `docs/superpowers/specs/2026-08-30-partneropen-network-admin-design.md` | Host/Creator roles, site/space hierarchy and M2 distribution boundary |
| Landing design | `docs/superpowers/specs/2026-08-30-partneropen-landing-redesign-design.md` | Current landing narrative and availability reconciliation |

All tracked `docs/superpowers/specs/` and `docs/superpowers/plans/` files are included in the archive for design history and implementation context.

## Public landing / Vercel

- Vercel project: `mdrss/partneropen`
- Vercel project URL: https://vercel.com/mdrss/partneropen
- Vercel root directory: `apps/cloud`
- Build command: `npm run build`
- Install command: `npm install`
- Intended canonical host: https://partneropen.com
- Temporary deployment fallback: https://partnerpage-swart.vercel.app
- Last verified deployment URL for this snapshot: https://partneropen-mw0n32m17-mdrss.vercel.app/

The canonical DNS/HTTPS cutover is not considered complete until Vercel verifies `partneropen.com`. Do not put WordPress secrets, Connector secrets, partner emails or tenant data into the stateless landing deployment. Full deployment and DNS verification commands are in [`distribution/vercel-deploy.md`](distribution/vercel-deploy.md).

The landing download path includes the Connector package and SHA-256 file in `apps/cloud/public/`.

## Control-plane status

The approved architecture is a separate `apps/control-plane` service backed by Neon-compatible Postgres through `DATABASE_URL`, with a server-only Connector gateway and Resend as the first production email adapter.

Implemented in this snapshot:

- standalone Next.js package and Vercel configuration;
- local health endpoint;
- typed environment validation;
- local Postgres Docker compose definition for the next slice;
- shared Connector canonical-string/HMAC implementation.

Not yet implemented in this snapshot:

- Postgres migrations and repositories;
- passwordless auth routes and production email delivery;
- tenant authorization and role checks;
- encrypted site credentials;
- server-side Connector gateway operations;
- append-only audit persistence and retention worker.

These are specified, not faked. Do not describe the control plane as production-ready until those slices and their verification are complete. The implementation plan is [`docs/superpowers/plans/2026-08-30-partneropen-control-plane-foundation.md`](docs/superpowers/plans/2026-08-30-partneropen-control-plane-foundation.md).

## Local setup

Requirements:

- Node.js compatible with the current Next.js version;
- npm;
- Docker for WordPress and local Postgres smoke environments;
- PHP for the plain Connector unit tests, or the repository's WordPress smoke harness.

From the repository root:

```bash
npm install
npm run test:cloud
npm run test:control-plane
npm run build:cloud
npm run build:control-plane
```

Run the public landing locally:

```bash
npm run dev:cloud
```

Run the control-plane foundation locally:

```bash
npm run dev:control-plane
curl -fsS http://localhost:3001/api/health
```

Run the stateless reference client help:

```bash
node apps/cloud/scripts/sync-demo.mjs --help
```

The Connector PHP test loop and disposable WordPress smoke command are documented in the root [`README.md`](README.md):

```bash
for test in plugins/partneropen-connector/tests/test-*.php; do php "$test" || exit 1; done
./scripts/smoke-wordpress.sh
```

Local Postgres is defined for the next control-plane slice:

```bash
docker compose -f docker-compose.control-plane.yml up -d postgres
```

No production persistence fallback is permitted. The control plane must fail closed when required environment variables are absent. Use [`apps/control-plane/.env.example`](apps/control-plane/.env.example) only as a variable-name reference; never put real values into Git.

## Required services and accounts for the next phase

Create/provision these outside the archive:

1. Existing GitHub repository access for `NikNovo/partneropen`.
2. Existing Vercel team `mdrss` for the public `partneropen` project.
3. A separate Vercel project for `apps/control-plane`.
4. Neon or another managed Postgres provider and a server-only `DATABASE_URL`.
5. Resend account, verified sender domain and server-only `RESEND_API_KEY`/`EMAIL_FROM`.
6. DNS access for `partneropen.com` and the future application host.
7. HTTPS staging WordPress site with PartnerOpen Connector installed for gateway verification.
8. Test internal-admin, site-admin and creator identities.
9. A managed secret store for `AUTH_TOKEN_PEPPER` and the 32-byte `ENCRYPTION_KEY`.

Never send these credentials in chat or commit them to the repository.

## Handoff sequence

1. Clone or unpack the archive.
2. Run `npm install`.
3. Run the Cloud and control-plane tests/builds above.
4. Review [`docs/connector-api.md`](docs/connector-api.md) and the control-plane design before adding any backend route.
5. Complete the implementation plan in order; preserve the WordPress authority and secret boundary.
6. Provision external services only after the local tests and migrations are ready.
7. Deploy `apps/cloud` and `apps/control-plane` as separate Vercel projects.
8. Verify DNS, HTTPS, legal routes, health routes and staging Connector calls before production cutover.

## Archive exclusions

The handoff archive excludes:

- `.env.local`, `.env` files with local values and all credentials;
- `.vercel/` project-local state;
- `node_modules/`;
- `.next/`, `out/`, `dist/`, coverage and TypeScript build caches;
- untracked local artifacts and editor/OS files.

Dependencies are reproducible from `package-lock.json`; generated output is reproducible with the documented build commands.
