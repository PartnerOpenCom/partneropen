# PartnerOpen

PartnerOpen is a neutral delegated-Space publishing system. A site owner installs **PartnerOpen Connector** in WordPress, chooses a URL prefix, grants explicit consent scopes, and retains local control through Global Pause or Withdraw consent & disconnect. A partner publishes a validated snapshot through signed REST requests, normally using `apps/cloud/scripts/sync-demo.mjs`. Every external destination is rendered through a same-origin resolver with non-configurable disclosure, visible `Goes to <host>` text, and `rel="sponsored nofollow noopener"`; withdrawing `affiliate_service` removes the anchor and returns resolver `404 consent`.

## Milestones

### M1 — Connector MVP (what this repository ships)

The Connector is the durable system of record in M1. WordPress options hold pairing state, consent records, Space metadata, published snapshots, and aggregate daily click counters. M1 includes:

- local-first owner setup and per-scope consent;
- zero outbound HTTP from the Connector in M1; consent gates any signed client exchange;
- one-time pairing and signed `partneropen/v1` REST routes;
- first signed snapshot publish auto-provisions a named Space (up to five), so the owner setup does not create individual Spaces;
- typed-block snapshot validation and local rendering;
- same-origin link resolution, HTTPS host allowlisting, disclosure, and sponsored link attributes;
- same-origin canonical/image validation (scheme, host, and port) with no remote image proxy;
- the redacted `AGENTS.md`, `llms.txt`, `ai-context.json`, `manifest.json`, and `sitemap.xml` pack;
- 90-day aggregate daily click counters;
- Global Pause; and
- Withdraw consent & disconnect, which revokes the secret and unpairs without deleting local snapshots.

M1 partner publishing uses the signed reference client at `apps/cloud/scripts/sync-demo.mjs`. There is no hosted partner editor or passwordless partner login yet.

**PartnerOpen Cloud** is a stateless public site in M1. It serves the landing page, canonical `/terms` and `/privacy`, and `/api/health`, `/api/consent-scopes`, and `/api/connector-manifest`. It has no pairing, tenant, publish, or metrics-ingest server routes and no in-memory persistence.

### M2 — Cloud tenant backend (deferred, not built)

M2 may add passwordless partner login, tenant/site/Space isolation, a hosted typed page builder, email invitations and notifications, Cloud-side metrics storage, billing, and network adapters. These capabilities are not available today and are not stubbed. Named prerequisites are managed Postgres (or an equivalent durable store), a session/token authentication service, and a transactional email provider; credentials for these do not exist in this environment.

PartnerOpen does not process payments, calculate payouts, hold funds, or settle participant shares. M1 only exposes publishing and aggregate measurement data that participants may use under their separate agreements; any future billing capability remains deferred to M2.

## Locked names

- Product: **PartnerOpen**
- WordPress plugin: **PartnerOpen Connector** (`plugins/partneropen-connector`, slug `partneropen-connector`)
- Public site: **PartnerOpen Cloud** (`apps/cloud`)
- Repository: `partneropen`
- Vercel project: `partneropen`
- Plugin namespace: `PartnerOpen\Connector\`
- REST namespace: `partneropen/v1`

See [`docs/brand.md`](docs/brand.md) for the locked voice, mandatory disclosure, and forbidden claims.

## Repository layout

```text
plugins/partneropen-connector/  GPL WordPress Connector, REST API, rendering, admin, tests
apps/cloud/                       Stateless Next.js/Vercel public site and signed reference client
docs/                           API, snapshot schema, brand, privacy and delegated-Space specification
distribution/                   Packaging and deployment/review/terms material
scripts/                        Connector packager and disposable WordPress smoke harness
docker-compose.smoke.yml        WordPress + MySQL smoke environment
```

## Storage and consent boundary

The Connector is the durable system of record for M1. Its options are `partneropen_connection`, `partneropen_consent`, non-autoloaded `partneropen_secret`, `partneropen_pause`, `partneropen_spaces`, `partneropen_snapshot_{space_id}`, and `partneropen_clicks`. The Cloud web app has no provisioned database and stays stateless.

The authoritative Cloud host allowlist is `partneropen.com` and `www.partneropen.com`. **Historical cutover note:** `https://partnerpage-swart.vercel.app` is retained only as a temporary deployment fallback while PartnerOpen DNS and HTTPS are verified. It is not a canonical or branded host, a default allowlist entry, a supported runtime identifier, or a legal link; remove it after verification succeeds. The canonical legal pages are [Terms of Use](https://partneropen.com/terms) and [Privacy Notice](https://partneropen.com/privacy). Do not treat the canonical domain as live until DNS and HTTPS verification succeed.

The six scopes, in canonical order, are:

1. **Cloud connection** — “Pair this site with the signed PartnerOpen Cloud client so delegated Space requests can be authenticated.”
2. **Partner invitation email** — “Record the partner address used for the invitation and service notices for this Space.”
3. **Content sync** — “Receive the published page snapshot that this site renders.”
4. **Agent context files** — “Publish AGENTS.md, llms.txt, ai-context.json, manifest.json and sitemap.xml for the delegated Space.”
5. **Aggregate click counters** — “Record daily click totals per placement so the partner can measure placements.”
6. **Affiliate service links** — “Allow links supplied by connected affiliate services to be published in this Space with disclosure.”

`partner_email` is optional at the option level and is required only when a paired client sends an invitation; the Connector sends no email. The Connector never collects cookies, IP addresses, user agents, referrers, device fingerprints, unique visitor identifiers, or visitor-level click events. See [`distribution/privacy-notice.md`](distribution/privacy-notice.md) for fields, recipients, and retention per scope.

`affiliate_service` is enforced at rendering: when withdrawn, labels are plain text with no anchor and the same-origin resolver returns 404 with reason `consent`. `aggregate_metrics` is enforced at collection: withdrawing it stops new click-counter writes. `agent_pack` is enforced locally at publication time rather than as an outbound-sync toggle: withdrawing it returns no-store `404` responses for the six agent-context routes while the Space page and resolver continue under their own controls.

The plugin is single-site only. It has no multisite network-management or network-wide consent behavior.

Disconnect is consent withdrawal and unpairing, not partner administration and not a second content-management switch. It revokes the secret, stops outbound calls, marks the connection disconnected, and keeps local snapshots until explicit deletion. Optional-scope withdrawal affects only that scope and does **not** revoke the site key. No Cloud-side snapshot, metrics or agent-file copy is stored in this M1; future service data is governed by that service's notice. Reconnection needs fresh consent and a fresh pairing code.

Aggregate counters are retained for 90 days. Activation schedules the daily `partneropen_connector_prune_clicks` cron; deactivation clears it; its callback prunes old counters. Uninstall deletes all plugin options, every `partneropen_snapshot_*` option, counters, and plugin transients.

## Development and verification

### PHP tests

PHP tests are plain scripts with their own minimal WordPress stubs:

```sh
for test in plugins/partneropen-connector/tests/test-*.php; do
    php "$test" || exit 1
done
```

Each script prints `...: OK` on success. The tests do not require Composer.

### WordPress smoke test

The disposable Docker Compose harness exercises activation, local-first HTTP gating, setup and consent, Cloud allowlist/pair-code preservation, required signed headers, strict snapshot and same-origin image validation, exact Space fields, affiliate and aggregate enforcement, typed rendering, disclosure/resolver behavior, agent-pack redaction, suspension sync/resume, public stylesheet enqueue, Global Pause/Resume, disconnect, uninstall cleanup, and reinstall/reactivation:

```sh
./scripts/smoke-wordpress.sh
```

The script prints `WordPress smoke test: OK` only after all assertions pass. It is intentionally disposable and removes its volumes on exit.

### Cloud tests and local development

```sh
npm install
npm run test:cloud
npm run dev:cloud
```

The reference client is a stateless CLI, not a Cloud server. Its help and sync workflow are available with:

```sh
node apps/cloud/scripts/sync-demo.mjs --help
```

It signs real pair, publish, status, and metrics calls against a Connector base URL; pairing and durable state remain in WordPress.

## Distribution

Build the full self-hosted Connector package (tests are excluded):

```sh
./distribution/build-connector-plugin.sh
```

Build the separate WordPress.org directory-safe artifact:

```sh
./distribution/build-directory-plugin.sh
```

The full command writes `artifacts/partneropen-connector-0.1.0.zip`; the directory command writes `artifacts/partneropen-connector-directory-0.1.0.zip`, each with a `.sha256` file. The directory-safe artifact disables affiliate/link blocks and hard-disables resolver redirects; it is the WordPress.org candidate and must replace the placeholder contributor with a real submitting WordPress.org username before submission. Do not submit the full package to WordPress.org. The full package retains resolver/affiliate capability and is for direct self-hosted installation. Review [`distribution/wordpress-org-pre-review.md`](distribution/wordpress-org-pre-review.md) and [`distribution/vercel-deploy.md`](distribution/vercel-deploy.md) before release.

## Explicit non-goals

M1 does not provide owner publication approval/rejection, an owner partner-revoke control, per-Space owner pause, a user-facing audit log, in-product chat, hidden links, cloaking, guaranteed ranking or moderation outcomes, or per-visitor tracking. It does not provide a hosted tenant editor, passwordless Cloud login, Cloud-side durable metrics, billing, or network-adapter backend until M2 prerequisites are available. Cloud does not substitute fake or in-memory persistence for a durable store.
