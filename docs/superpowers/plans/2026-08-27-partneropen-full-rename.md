# PartnerOpen Full Rename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the unreleased PartnerPage identity with PartnerOpen across the product, technical namespaces, WordPress package, Cloud app, repository and deployment metadata, while making `PartnerOpen.com` the intended canonical site.

**Architecture:** This is a clean cutover, not a compatibility migration: no public installations exist, so every product identifier moves together. The WordPress plugin becomes `partneropen-connector`, namespace `PartnerOpen\Connector`, REST namespace `partneropen/v1`, options/transients/query vars use the `partneropen` prefix, and the Cloud package becomes `@partneropen/cloud`. The current Vercel alias remains only as a temporary deployment fallback until `PartnerOpen.com` DNS is registered and verified.

**Tech Stack:** PHP 8.2 test container, WordPress 7.1 smoke site, Next.js 16.3.2, TypeScript, Node test runner, GitHub CLI, Vercel CLI/API, Verisign RDAP.

## Global Constraints

- Product/public name: **PartnerOpen**.
- WordPress plugin display name: **PartnerOpen Connector**.
- Plugin directory/package: `partneropen-connector`.
- PHP namespace: `PartnerOpen\Connector\`.
- REST namespace: `partneropen/v1`.
- Option keys: `partneropen_connection`, `partneropen_consent`, `partneropen_secret`, `partneropen_pause`, `partneropen_spaces`, `partneropen_snapshot_{space_id}`, `partneropen_clicks`.
- Transient keys: `partneropen_connector_pair_code`, `partneropen_connector_nonce_{nonce}`.
- WordPress query vars: `partneropen_space`, `partneropen_asset`, `partneropen_link`, `partneropen_placement`.
- Public resolver path: `/partneropen/go/{link_id}/{placement_id}`.
- Cloud package: `@partneropen/cloud`; app directory remains `apps/cloud`.
- GitHub repository: `NikNovo/partneropen`.
- Vercel project: `partneropen`.
- Intended canonical domain: `https://partneropen.com`.
- Existing Vercel hostname is temporary fallback only; no old PartnerPage public branding may remain.
- No old aliases, re-exports, migration shims or dual technical identifiers are needed because the product has no public installations.
- Preserve all current behavior: consent gates, strict snapshot validation, same-origin resolver, Global Pause, aggregate-only counters, positive agent-pack allowlist, M1/M2 boundary and no payment processing.
- Do not claim a working contact submission endpoint until Resend/backend is provisioned.
- The current OMP chat cannot be renamed through repository/Vercel APIs; refer to this project/session as PartnerOpen in future work and document that limitation rather than fabricating a chat setting.

---

### Task 1: Freeze the PartnerOpen technical contract

**Files:**
- Modify: `docs/brand.md`
- Modify: `docs/superpowers/specs/2026-08-24-partnerpage-delegated-space-spec.md`
- Modify: `docs/connector-api.md`
- Modify: `docs/snapshot-schema.json`
- Create: `docs/superpowers/specs/2026-08-27-partneropen-rename.md`

**Interfaces:**
- Consumes: current PartnerPage M1/M2 contract.
- Produces: one authoritative PartnerOpen naming table used by every later task.

- [ ] **Step 1: Write the rename acceptance checklist**

Document the exact replacement table:

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

State explicitly that no public compatibility path is required, and that all existing local smoke fixtures are re-created from the new identifiers.

- [ ] **Step 2: Update the brand and architecture documents**

Replace product names, package names, route names, legal URLs and examples. Keep the M1/M2 wording factual. Add an explicit OMP note: the external chat session title is not controlled by the repository, but all project-facing references use PartnerOpen.

- [ ] **Step 3: Self-check the contract**

Search the changed docs for old public names and verify no old technical identifier is presented as current. Keep historical migration notes only when they say the rename is a clean cutover and are not user-facing runtime instructions.

- [ ] **Step 4: Commit the contract**

```bash
git add docs/brand.md docs/superpowers/specs/2026-08-24-partnerpage-delegated-space-spec.md docs/connector-api.md docs/snapshot-schema.json docs/superpowers/specs/2026-08-27-partneropen-rename.md
git commit -m "docs: define PartnerOpen rename contract"
```

---

### Task 2: Rename the WordPress Connector technical surface

**Files:**
- Rename: `plugins/partnerpage-connector/` → `plugins/partneropen-connector/`
- Modify: every PHP/CSS/readme/test file under the renamed directory
- Modify: `scripts/smoke-wordpress.sh`
- Modify: `docker-compose.smoke.yml`
- Modify: `distribution/build-connector-plugin.sh`
- Modify: `distribution/build-directory-plugin.sh`
- Modify: `scripts/package_plugin.py`
- Modify: `scripts/verify_directory_package.py`

**Interfaces:**
- Consumes: Task 1 naming table.
- Produces: `PartnerOpen\Connector` plugin with the same public behavior and no old technical identifiers.

- [ ] **Step 1: Rename filesystem and bootstrap identity**

Move the directory and change the plugin header/constants:

```php
Plugin Name: PartnerOpen Connector
Text Domain: partneropen-connector
PARTNEROPEN_CONNECTOR_VERSION
PARTNEROPEN_CONNECTOR_FILE
PARTNEROPEN_CONNECTOR_DIR
PARTNEROPEN_CONNECTOR_URL
```

Update the autoloader prefix to `PartnerOpen\\Connector\\` and every namespace/import/reference accordingly.

- [ ] **Step 2: Rename persistence and request identifiers**

Update every current option, snapshot key, transient, query variable, rewrite rule, REST route and resolver URL to the new `partneropen` names. Remove legacy dual-write/read code and old cleanup aliases; the uninstall routine deletes only the new namespace because no old public installation exists.

- [ ] **Step 3: Preserve behavior while updating tests**

Update every standalone PHP test require path, namespace, option/transient fixture, route and expected error. Keep tests for:

- local-first consent gate;
- site/scope HMAC authorization;
- snapshot format 3 and strict validation;
- same-origin canonical/images;
- affiliate and aggregate scopes;
- suspended Space/resume;
- Global Pause;
- uninstall cleanup;
- public stylesheet and JSON-LD.

- [ ] **Step 4: Update packaging and smoke paths**

Package names become:

```text
partneropen-connector-0.1.0.zip
partneropen-connector-directory-0.1.0.zip
```

The smoke compose mount becomes `/wp-content/plugins/partneropen-connector`; WP-CLI activates `partneropen-connector`; all `wp eval` paths use `PartnerOpen\\Connector` and `partneropen/v1`.

- [ ] **Step 5: Verify the renamed plugin in isolation**

Run only the standalone PHP suite and `php -l` in the parent verification phase. Confirm the ZIP root is `partneropen-connector/`, tests are excluded, and no `PartnerPage`/`partnerpage` runtime string remains.

- [ ] **Step 6: Commit the plugin rename**

```bash
git add plugins scripts distribution docker-compose.smoke.yml
git commit -m "refactor: rename Connector to PartnerOpen"
```

---

### Task 3: Rename and align the Cloud application

**Files:**
- Modify: `apps/cloud/package.json`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `apps/cloud/app/layout.tsx`
- Modify: `apps/cloud/app/page.tsx`
- Modify: `apps/cloud/app/connector-manifest.ts`
- Modify: `apps/cloud/app/consent-scopes.ts`
- Modify: `apps/cloud/app/legal-content.ts`
- Modify: `apps/cloud/app/terms/page.tsx`
- Modify: `apps/cloud/app/privacy/page.tsx`
- Modify: `apps/cloud/app/components/HeroPhonePreview.tsx`
- Modify: `apps/cloud/app/components/M2RequestDialog.tsx`
- Modify: `apps/cloud/lib/signature.ts`
- Modify: `apps/cloud/scripts/sync-demo.mjs`
- Modify: `apps/cloud/scripts/sample-snapshot.json`
- Modify: `apps/cloud/tests/**`
- Modify: `apps/cloud/README.md`

**Interfaces:**
- Consumes: renamed PHP REST/signature contract from Task 2.
- Produces: `@partneropen/cloud`, PartnerOpen copy, stateless APIs and signed client using `partneropen/v1`.

- [ ] **Step 1: Rename package and metadata**

Set root package name to `partneropen`, Cloud package name to `@partneropen/cloud`, API health service to `partneropen-cloud`, and all metadata/manifest/legal titles to PartnerOpen / PartnerOpen Cloud.

- [ ] **Step 2: Update client and API paths**

Change the reference client to sign and call:

```text
/wp-json/partneropen/v1/pair
/wp-json/partneropen/v1/status
/wp-json/partneropen/v1/spaces/{space}/snapshot
/wp-json/partneropen/v1/metrics
```

Change the sample snapshot resolver URLs to `/partneropen/go/...`. Keep HMAC canonicalization byte-identical apart from the route path.

- [ ] **Step 3: Rewrite public copy consistently**

Replace every PartnerPage mention with PartnerOpen. Keep current safe collaborative messaging:

- Host / Creator roles;
- flexible content;
- standard WordPress plugin;
- Global Pause;
- consented publication records;
- verified aggregate data;
- no payment processing;
- M2 by request;
- MCP planned, not live;
- inactive contact form until endpoint/mailbox exists.

Update hero, comparison table, phone preview, M2 dialog, legal pages, FAQ and footer together.

- [ ] **Step 4: Update Cloud tests**

Assert PartnerOpen service names, routes, legal copy and no stale PartnerPage strings. Keep the existing modal, landing, consent and signature tests.

- [ ] **Step 5: Commit the Cloud rename**

```bash
git add package.json package-lock.json apps/cloud
git commit -m "refactor: rename Cloud app to PartnerOpen"
```

---

### Task 4: Rename GitHub and Vercel resources

**Files:**
- Modify: `.git/config` via Git/GitHub CLI, not manual text editing
- Modify: `apps/cloud/.vercel/project.json` through Vercel linking
- Modify: `distribution/vercel-deploy.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: PartnerOpen repository/project names from Task 1.
- Produces: `NikNovo/partneropen`, Vercel project `partneropen`, intended custom domain `partneropen.com`.

- [ ] **Step 1: Rename the GitHub repository**

Use the authenticated GitHub CLI:

```bash
gh repo rename partneropen --repo NikNovo/partnerpage
```

Verify the remote becomes `https://github.com/NikNovo/partneropen.git`, then push `main`.

- [ ] **Step 2: Rename the Vercel project**

Use the authenticated Vercel API/CLI to rename the project from `partnerpage` to `partneropen`, preserve the existing team scope and remove deployment protection if required for the public site. Update `.vercel/project.json` with the renamed project metadata.

- [ ] **Step 3: Attach the custom domain when possible**

Attempt to add `partneropen.com` to the renamed Vercel project. If the domain is not registered/verified yet, leave the current Vercel URL as a temporary alias and report the exact DNS/registrar action required:

```text
A/AAAA or CNAME according to Vercel's domain instructions
TXT verification record if requested
```

Do not claim `PartnerOpen.com` is live until DNS and HTTPS resolve.

- [ ] **Step 4: Update deployment documentation**

Document `partneropen` project, `PartnerOpen.com` intended canonical domain, temporary Vercel fallback, and the exact verification commands.

- [ ] **Step 5: Commit resource metadata**

```bash
git add README.md distribution/vercel-deploy.md apps/cloud/.vercel/project.json
# Do not commit .vercel metadata if .gitignore excludes it; only commit tracked config that the repo intentionally owns.
git commit -m "chore: rename PartnerOpen repository and deployment"
```

---

### Task 5: Complete legal/support and stale-name cleanup

**Files:**
- Modify: `README.md`
- Modify: `plugins/partneropen-connector/readme.txt`
- Modify: `plugins/partneropen-connector/README.md`
- Modify: `distribution/privacy-notice.md`
- Modify: `distribution/terms-of-use.md`
- Modify: `distribution/wordpress-org-pre-review.md`
- Modify: `distribution/network-boundary.md`
- Modify: `docs/brand.md`
- Modify: `docs/connector-api.md`
- Modify: `docs/superpowers/specs/2026-08-24-partnerpage-delegated-space-spec.md`

**Interfaces:**
- Consumes: final names and routes from Tasks 1–3.
- Produces: no stale public PartnerPage claims and a clear pre-submission boundary.

- [ ] **Step 1: Update legal/service references**

Use PartnerOpen names and `/terms`/`/privacy` links. State that M1 Cloud is stateless, Connector is durable, M2 is by request, and payments remain outside PartnerOpen under participant agreements.

- [ ] **Step 2: Update WordPress.org submission boundary**

State that the directory-safe package is the WordPress.org candidate, while the full resolver/affiliate package is for direct self-hosted installation. Replace PartnerPage name-rights instructions with PartnerOpen name-rights instructions. Keep the real contributor username as an explicit pre-submit blocker if it is not supplied.

- [ ] **Step 3: Scan stale names**

Run targeted searches over tracked source/docs for `PartnerPage`, `partnerpage`, `PARTNERPAGE`, `pp_pair_code`, `pp_nonce_`, `partnerpage/v1`, `/partnerpage/go/`, and `@partnerpage`. Any remaining occurrence must be either an intentional historical rename note or removed.

- [ ] **Step 4: Commit legal cleanup**

```bash
git add README.md plugins/partneropen-connector distribution docs
git commit -m "docs: align legal and support material with PartnerOpen"
```

---

### Task 6: Verification and cutover report

**Files:**
- Test: `plugins/partneropen-connector/tests/test-*.php`
- Test: `scripts/smoke-wordpress.sh`
- Test: `apps/cloud/tests/**`
- Inspect: built ZIPs, GitHub remote, Vercel API, DNS/HTTPS

**Interfaces:**
- Consumes: all renamed implementation and documentation tasks.
- Produces: evidence-backed release report and explicit unresolved domain/trademark items.

- [ ] **Step 1: Run PHP verification**

```bash
docker run --rm -v "$PWD/plugins/partneropen-connector:/w" php:8.2-cli sh -c 'find /w -name "*.php" -print0 | xargs -0 -n1 php -l && for t in /w/tests/test-*.php; do php "$t"; done'
```

Expected: all PHP files parse and every standalone test prints `OK`.

- [ ] **Step 2: Run WordPress smoke**

```bash
sh -n scripts/smoke-wordpress.sh
./scripts/smoke-wordpress.sh
```

Expected: `WordPress smoke test: OK`; plugin is `partneropen-connector`; REST calls use `partneropen/v1`; public routes use `/partneropen/...`; uninstall/reinstall leaves no `partneropen_*` state.

- [ ] **Step 3: Run Cloud verification**

```bash
npm run test:cloud
npx tsc --noEmit -p apps/cloud/tsconfig.json
npm run build:cloud
```

Expected: all tests pass, typecheck/build pass, no stale public names.

- [ ] **Step 4: Verify package boundaries**

```bash
sh distribution/build-connector-plugin.sh
sh distribution/build-directory-plugin.sh
```

Inspect both ZIPs. Expected roots:

```text
partneropen-connector/
partneropen-connector-directory/
```

No tests or old names in either archive. Directory build has its flag enabled and truthful readme.

- [ ] **Step 5: Verify deployment**

Query Vercel deployment state and live routes. Expected temporary fallback or custom domain to return 200 for `/`, `/terms`, `/privacy`, `/api/health`, `/api/consent-scopes`, `/api/connector-manifest`. Do not call the custom domain live until DNS/HTTPS is confirmed.

- [ ] **Step 6: Publish final cutover report**

Report:

- exact GitHub URL;
- exact Vercel project and deployment URL;
- whether `PartnerOpen.com` is registered/verified/live;
- test evidence;
- remaining trademark/contributor/domain blockers;
- OMP chat naming limitation.

---

## Self-review checklist

- [ ] No public PartnerPage brand remains in source, Cloud copy, docs, plugin readme or legal pages.
- [ ] No old technical route/namespace/option/transient/query-var remains active.
- [ ] No compatibility alias was accidentally retained.
- [ ] M1/M2 claims remain factual; MCP is planned, not live.
- [ ] Payment processing is not claimed.
- [ ] PartnerOpen.com is not claimed live until registrar/DNS/HTTPS verification succeeds.
- [ ] Full and directory-safe packages have different, truthful capabilities.
- [ ] WordPress.org pre-review text names PartnerOpen and retains the unresolved real contributor/trademark checks.
- [ ] OMP chat limitation is stated without pretending the chat title changed.
