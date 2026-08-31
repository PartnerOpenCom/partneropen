# PartnerOpen Cloud

PartnerOpen Cloud is the public, stateless Next.js site for PartnerOpen Connector. It explains the product and exposes the static health, consent-scope and Connector-manifest endpoints. It also includes `scripts/sync-demo.mjs`, a framework-free reference client that pairs with a WordPress Connector and publishes a signed snapshot.

The WordPress Connector owns durable state in WordPress: pairing, consent, Spaces, snapshots and aggregate counters. This app has no tenant database, pairing store, publish store or metrics-ingest route. It does not provide a browser partner editor in this milestone.

## Local commands

From the repository root:

```bash
npm install
npm run dev:cloud
npm run build:cloud
npm run start:cloud
npm run test:cloud
npm run sync:demo -- --base https://site.example --code PAIRING-CODE --space reviews
```

The reference client also accepts `--cloud-base` (the HTTPS PartnerOpen Cloud URL sent while pairing; the Connector rejects a non-HTTPS `cloud_base`), plus `--snapshot` and `--secret` (for signing a pre-paired run). See `node apps/cloud/scripts/sync-demo.mjs --help` for the complete flag list.

## Roadmap prerequisite

The deferred partner editor milestone includes passwordless login, tenant/Space isolation, a hosted typed page builder, email invitations, Cloud-side metrics, billing and network adapters. It cannot be provisioned until the project has a **managed Postgres or equivalent durable store**, an **auth/session service** and a **transactional email provider**.

## Deployment

Set the Vercel project root to `apps/cloud`. This deployment is only for the public site and static APIs; Connector credentials and WordPress state never belong in this frontend.
