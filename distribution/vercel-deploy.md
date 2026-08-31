# PartnerOpen Cloud Vercel deployment

The Vercel project is named **`partneropen`** in the `mdrss` team scope and deploys the Next.js app in `apps/cloud`. WordPress runs **PartnerOpen Connector** on the site owner's own host; it is packaged separately.

## Release URLs and domain cutover

- GitHub repository: `https://github.com/NikNovo/partneropen`.
- Vercel project: `mdrss/partneropen` at `https://vercel.com/mdrss/partneropen`.
- Intended canonical site: `https://partneropen.com`.
- Temporary Vercel fallback: `https://partnerpage-swart.vercel.app` (the existing production alias; non-canonical and not a new `partneropen-swart.vercel.app` hostname).

The intended canonical site and legal URLs must not be described as live until DNS and HTTPS resolve. `partneropen.com` is attached to the `partneropen` project, but the current external DNS state is `invalid-configuration`. Vercel currently recommends an apex `A` record of `76.76.21.21`; alternatively, delegate the domain to `ns1.vercel-dns.com` and `ns2.vercel-dns.com`. Apply the records shown by Vercel for the domain (including a TXT verification record if Vercel requests one), then rerun the verification command below. The temporary fallback remains the deployment-access URL until that cutover succeeds.

## Project settings

- Repository: the `partneropen` repository (`https://github.com/NikNovo/partneropen`).
- Root Directory: `apps/cloud`.
- Framework preset: Next.js.
- Build command: `npm run build`.
- Install command: `npm install`.
- Node.js: use the current Vercel LTS runtime supported by the selected Next.js version.

## M1 runtime boundary

M1 PartnerOpen Cloud is stateless. It serves the landing page, canonical `/terms` and `/privacy`, and these public routes only:

```text
GET /terms
GET /privacy
GET /api/health
GET /api/consent-scopes
GET /api/connector-manifest
```

The intended canonical legal URLs are `https://partneropen.com/terms` and `https://partneropen.com/privacy`; they are not live until DNS and HTTPS verification succeeds. Until then, use the temporary fallback `https://partnerpage-swart.vercel.app/terms` and `https://partnerpage-swart.vercel.app/privacy` for deployment access. The app does not expose pairing, tenant, publish, or metrics-ingest routes and must not keep state in module scope or in-memory maps. Pairing, publishing, status, and aggregate metrics calls go to a Connector base URL and are made by the stateless reference client `apps/cloud/scripts/sync-demo.mjs`. The Connector itself makes zero outbound HTTP requests in M1.

No database is provisioned for this deployment. A future Cloud tenant backend requires managed Postgres (or an equivalent durable store), a session/token authentication service, and a transactional email provider. Do not add placeholder persistence or pretend those credentials exist.

## Environment

The M1 public site needs only its build/runtime metadata, for example:

```text
PARTNEROPEN_VERSION=0.1.0
```

Do not put WordPress secrets, Connector site secrets, partner email addresses, network credentials, or publisher identifiers into Vercel environment variables for the stateless public site.

## Verification

Inspect and verify the Vercel project and domain state:

```sh
vercel project inspect partneropen --scope mdrss
vercel domains inspect partneropen.com --scope mdrss
vercel domains verify partneropen.com --scope mdrss
```

After the DNS records are applied and verification succeeds, check the intended canonical site:

```sh
curl -fsS https://partneropen.com/terms
curl -fsS https://partneropen.com/privacy
curl -fsS https://partneropen.com/api/health
curl -fsS https://partneropen.com/api/consent-scopes
curl -fsS https://partneropen.com/api/connector-manifest
```

While DNS is pending, check the temporary fallback deployment:

```sh
curl -fsS https://partnerpage-swart.vercel.app/terms
curl -fsS https://partnerpage-swart.vercel.app/privacy
curl -fsS https://partnerpage-swart.vercel.app/api/health
curl -fsS https://partnerpage-swart.vercel.app/api/consent-scopes
curl -fsS https://partnerpage-swart.vercel.app/api/connector-manifest
```

A deployment built from the current PartnerOpen sources should report `service: "partneropen-cloud"`, `status: "ok"`, and version `0.1.0`. The existing temporary fallback may still point to a pre-rename production deployment until a new deployment is promoted; verify its payload after each deployment. The other two responses expose only their documented public metadata and never contain a site secret, partner email, or tenant state.
