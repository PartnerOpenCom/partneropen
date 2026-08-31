# PartnerOpen Production Landing Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a full visual and narrative redesign of the PartnerOpen public landing page that presents safe WordPress setup, Host/Creator collaboration, admin audit logs, aggregate usage data, MCP, and PartnerOpen GUI as available now.

**Architecture:** Keep the stateless Next.js public app and its existing API/legal routes. Replace the landing page's copy model and DOM composition, replace the decorative phone with a responsive Connector setup/audit preview, and rewrite landing-only CSS while preserving legal-page styles. Retain manifest-derived package metadata and all existing download/evidence links.

**Tech Stack:** Next.js 16, React 19, TypeScript/TSX, global CSS, Node's built-in test runner, Vercel.

## Global Constraints

- Keep the Cloud landing stateless; do not add auth, database, publication, or visitor telemetry code.
- WordPress remains the Host-controlled authority for durable connection state, consent, Spaces, snapshots, Global Pause, disconnect, public rendering, and aggregate counters.
- Describe MCP and PartnerOpen GUI as available now, per the product decision supplied for this release.
- Use `aggregate placement metrics`, `verified usage data`, or `admin audit logs`; do not describe the product as visitor tracking.
- Use `MCP` everywhere; remove every `MPC` typo.
- Host controls setup, scopes, Global Pause, consent withdrawal, disconnect, and explicit local snapshot deletion; Host does not edit Creator content or receive visitor profiles.
- Creator access is invitation-by-email plus passwordless magic link; do not describe open registration or OAuth as required.
- Preserve `/partneropen-connector-0.1.0.zip`, its checksum, `/api/connector-manifest`, `/api/health`, `/api/consent-scopes`, `/terms`, and `/privacy` links.
- Preserve mobile no-overflow behavior, accessible headings/landmarks, reduced-motion handling, and external-link `noopener noreferrer` attributes.
- Skip formatters, linters, and project-wide suites during individual implementation steps; run the targeted test, build, and browser smoke checks once at the verification checkpoint.

---

### Task 1: Lock the new landing contract with tests

**Files:**
- Modify: `apps/cloud/tests/landing-copy.test.mjs`
- Modify: `apps/cloud/tests/m2-dialog.test.mjs`

**Interfaces:**
 - Produces source-level contracts for the new landing copy, available MCP/GUI claims, and the still-by-request publishing-network scope.
 - The page implementation in Task 2 must satisfy these assertions without adding a second compatibility surface.

- [ ] **Step 1: Replace stale copy assertions with failing assertions**

  Update `landing-copy.test.mjs` to require the new surface, including:

  ```js
  assert.match(source, /SET PARTNER SAFE SPACE VIA GPL WORDPRESS CONNECTOR/);
  assert.match(source, /aggregate placement metrics|verified usage data/);
  assert.match(source, /PartnerOpen GUI/);
  assert.match(source, /MCP/);
  assert.match(source, /FULL ADMIN LOGS/);
  assert.match(source, /Creator.*email|email.*Creator/);
  assert.match(source, /magic link/);
  assert.match(source, /AVAILABLE NOW/);
  assert.doesNotMatch(source, /MPC/);
  assert.doesNotMatch(source, /visitor tracking/);
  assert.doesNotMatch(source, /MCP Connector.*planned|GUI.*unavailable|not available now/);
  assert.match(source, /MILESTONE 2.*BY REQUEST/);
  ```

  Retain assertions for PartnerOpen, GPL Connector, download URL, technical evidence links, PRM/CRM fit, legal links, legacy product-name absence, and the `MILESTONE 2 / BY REQUEST` network path. Replace assertions that require stale MCP/GUI-unavailable or reference-client-only wording.

 - [ ] **Step 2: Update the existing M2 dialog contract without removing it**

  Keep `apps/cloud/tests/m2-dialog.test.mjs` and the accessible dialog component because the publishing-network package remains `MILESTONE 2 / BY REQUEST`. Update its assertions to require that MCP Connector and PartnerOpen GUI are available now, while network administration/distribution remains by request. Keep the no-form/no-fake-submission assertions. Keep the component in `apps/cloud/tests/rename-contract.test.mjs` and `apps/cloud/tests/index.js`.

- [ ] **Step 3: Run the targeted contract tests and verify RED**

  Run:

  ```bash
  npm run test:cloud -- --test-name-pattern "landing|M2|Cloud source|Cloud package"
  ```

  Expected: FAIL on the new hero/GUI/MCP/audit-log assertions and/or stale current page assertions, not a test-runner error. Record the failing assertion before changing production source.

- [ ] **Step 4: Commit the red contract changes**

  ```bash
  git add apps/cloud/tests/landing-copy.test.mjs apps/cloud/tests/m2-dialog.test.mjs
  git commit -m "test: define production landing copy contract"
  ```

### Task 2: Replace the landing page narrative and markup

**Files:**
- Modify: `apps/cloud/app/page.tsx`

**Interfaces:**
- Consumes: `connectorManifest` and `consentScopes` from existing modules; existing API/legal/download URLs.
- Produces: semantic landing sections with stable IDs `trust-proof`, `collaboration`, `comparison`, `how-it-works`, `owner-control`, `consent`, `links`, `agent-pack`, `never-collected`, `where`, `publish`, `install`, and `faq`.

- [ ] **Step 1: Update data arrays and failing page assertions remain red**

  Replace the stale arrays and FAQ copy with explicit content data for:

  - hero USP pills: `SAFE`, `EASY TO SET`, `FULL ADMIN LOGS`;
  - trust proof cards for WordPress durable state, independent Creator interface, pause/disconnect, and no visitor dossiers;
  - Host/Creator role cards and shared-boundary note;
  - five setup steps ending in signed API/MCP/GUI publication;
  - Host-managed and Host-not-managed lists;
  - audit events used by the phone preview labels, including `creator.invited`, `consent.changed`, `snapshot.published`, and `global_pause.changed`;
  - one available-now use case and one `MILESTONE 2 / BY REQUEST` network use case;
  - FAQ answers for email invitation + magic link, GUI/MCP availability, consent withdrawal, audit logs, link transparency, and payment boundaries.

  Use the exact product language from the design spec: no external tooling/services required for Host-side setup, free for the user, any agreed commercial terms, no open registration, and no OAuth integration required on the Host site.

- [ ] **Step 2: Implement the new hero and USP composition**

  Keep the download CTA and add the Partner Network CTA. Use this structure:

  ```tsx
  <p className="eyebrow">SET PARTNER SAFE SPACE VIA GPL WORDPRESS CONNECTOR</p>
  <h1 id="page-title">Partner directory. WordPress control.</h1>
  <p className="hero-lede">...</p>
  <div className="hero-actions">...</div>
  <ul className="hero-usp" aria-label="PartnerOpen advantages">...</ul>
  ```

  The lede must say `aggregate placement metrics` or `verified usage data`, never visitor tracking. Keep the phone component next to the copy and give the section one clear `How to connect` anchor target.

 - [ ] **Step 3: Rewrite sections 00–09 and preserve the by-request network scope**

  Preserve the technical evidence, consent table sourced from `consentScopes`, agent-context allowlist explanation, comparison table, install metadata, legal/footer links, existing stable section IDs, and the `M2RequestDialog` accessibility behavior. Change section copy and labels to the supplied sequence. Keep Use Case 01 available now; keep Use Case 02 explicitly `MILESTONE 2 / BY REQUEST` with a `Connect with us to discuss` CTA. MCP Connector and PartnerOpen GUI are available now in the base publication offering.

  Add visible references to the external network UX: Site admin invites a Creator by email, Creator enters through a magic link, access is scoped to Sites/Spaces, and admin audit events are visible in the PartnerOpen GUI. Do not add functional auth or network API calls to this stateless page.

 - [ ] **Step 4: Update the FAQ and metadata claims**

  Keep the FAQ answer that the publishing network is Milestone 2/by request, but remove stale claims that MCP or PartnerOpen GUI are unavailable. Use answers that distinguish the public landing repository from the product's available GUI/MCP surfaces without claiming that this static page itself is the authenticated editor. Keep payments and settlement outside PartnerOpen.

 - [ ] **Step 5: Run the targeted contract tests and verify GREEN**

  Run:

  ```bash
  npm run test:cloud -- --test-name-pattern "landing|M2|Cloud source|Cloud package"
  ```

  Expected: PASS for the updated landing and M2 dialog contracts plus legacy identifier checks.

 - [ ] **Step 6: Commit the narrative/markup change**

  ```bash
  git add apps/cloud/app/page.tsx apps/cloud/app/components/M2RequestDialog.tsx
  git commit -m "feat: rewrite PartnerOpen landing narrative"
  ```

- [ ] **Step 5: Run the targeted contract tests and verify GREEN**

  Run:

  ```bash
  npm run test:cloud -- --test-name-pattern "landing|Cloud source|Cloud package"
  ```

  Expected: PASS for the updated landing contract and legacy identifier checks.

- [ ] **Step 6: Commit the narrative/markup change**

  ```bash
  git add apps/cloud/app/page.tsx
  git commit -m "feat: rewrite PartnerOpen landing narrative"
  ```

### Task 3: Build the visual redesign and Connector audit phone

**Files:**
- Modify: `apps/cloud/app/components/HeroPhonePreview.tsx`
- Modify: `apps/cloud/app/globals.css`

**Interfaces:**
- `HeroPhonePreview` remains a client component with the existing pointer/parallax interaction and reduced-motion guard.
- CSS selectors consumed by `page.tsx` remain stable for all landing sections; styles from `.legal-page` onward remain intact for legal routes.

 - [ ] **Step 1: Add phone-preview contract assertions before implementation**

  Extend `landing-copy.test.mjs` to read `HeroPhonePreview.tsx` into a separate `phoneSource` value and assert that the component contains accessible interface text for:

  ```js
  assert.match(phoneSource, /WordPress Connector/);
  assert.match(phoneSource, /SAFE/);
  assert.match(phoneSource, /EASY TO SET/);
  assert.match(phoneSource, /FULL ADMIN LOGS/);
  assert.match(phoneSource, /CONSENT GRANTED/);
  assert.match(phoneSource, /snapshot\.published/);
  assert.match(phoneSource, /global_pause\.changed/);
  assert.doesNotMatch(phoneSource, /visitor|per-user|fingerprint/i);
  ```

  Run the focused test and verify it fails against the current system-overview phone before editing the component.

- [ ] **Step 2: Replace the phone content with a Connector setup/audit preview**

  Keep the existing parallax scene wrapper, but replace the internal screen with:

  - `PARTNEROPEN / CONNECTOR` top line and `READY` state;
  - `WordPress Connector` heading;
  - `SAFE`, `EASY TO SET`, and `FULL ADMIN LOGS` labels;
  - connection status `CONNECTED`, `CONSENT GRANTED`, and `CREATOR INVITED`;
  - a compact event list showing `creator.invited`, `snapshot.published`, and `global_pause.changed` with safe synthetic timestamps/statuses;
  - a footer showing `NO VISITOR DATA` and `GLOBAL PAUSE`.

  Keep the `role="img"` label accurate, or expose the interface text through a visually hidden accessible description if the preview remains decorative. Do not use visitor metrics in the illustration.

- [ ] **Step 3: Rewrite landing-only CSS for the new visual system**

  Replace the current landing selectors before `.legal-page` with a coherent visual system: deep ink background, warm evidence panels, blue/green/orange state accents, oversized section numerals, thin rules, asymmetric hero grid, USP chips, feature grids, audit-log rows, and a larger phone composition. Restyle the retained M2 dialog as a clear `MILESTONE 2 / BY REQUEST` network-offering panel; remove only obsolete console/chart rules no longer consumed by the page.

  Keep the legal styles unchanged. Preserve focus-visible states, external link readability, table overflow behavior, and `prefers-reduced-motion` handling. At `max-width: 800px` collapse hero/section grids; at `max-width: 560px` keep phone badges within the viewport, stack CTAs/USP pills, and render comparison/scope tables as readable cards.

- [ ] **Step 4: Run the focused phone and landing tests**

  Run:

  ```bash
  npm run test:cloud -- --test-name-pattern "landing|phone|Cloud source|Cloud package"
  ```

  Expected: PASS with no stale `MPC`, unavailable-GUI, or visitor-tracking copy; the valid `MILESTONE 2 / BY REQUEST` network path remains present.

- [ ] **Step 5: Commit the visual redesign**

  ```bash
  git add apps/cloud/app/components/HeroPhonePreview.tsx apps/cloud/app/globals.css apps/cloud/tests/landing-copy.test.mjs
  git commit -m "feat: redesign PartnerOpen landing visuals"
  ```

### Task 4: Build, browser-smoke, and deploy production

**Files:**
- Modify only if verification finds a concrete failure: `apps/cloud/app/page.tsx`, `apps/cloud/app/components/HeroPhonePreview.tsx`, `apps/cloud/app/globals.css`, or focused tests.

**Interfaces:**
- Production URL continues serving the landing and existing static endpoints.
- Vercel project root remains `apps/cloud`.

- [ ] **Step 1: Run the complete Cloud contract suite**

  ```bash
  npm run test:cloud
  ```

  Expected: all Cloud tests pass, including legal, consent, signature, rename, landing, and the updated phone contract.

- [ ] **Step 2: Build the production bundle**

  ```bash
  npm run build:cloud
  ```

  Expected: Next.js production build completes without TypeScript/route errors.

- [ ] **Step 3: Launch the actual production app locally**

  ```bash
  npm run start:cloud
  ```

  Open the running URL with the browser and verify at 1440×1000 and 390×844:

  - hero copy, phone preview, USP labels, and both CTAs are visible;
  - all numbered sections have readable hierarchy and no clipped content;
  - mobile `document.documentElement.scrollWidth <= window.innerWidth`;
  - comparison and consent data remain readable on mobile;
  - phone labels and audit events are visible and accessible;
  - CTA/download/API/legal links resolve to the existing paths.

- [ ] **Step 4: Deploy the production Vercel target**

  Use the established Vercel project workflow with `apps/cloud` as the project root and deploy the current branch to the production target. Capture the resulting deployment URL and inspector URL.

- [ ] **Step 5: Smoke-test the deployed URL**

  In the browser, repeat the hero/mobile checks against the deployed URL and fetch the following endpoints through the page or direct navigation:

  ```text
  /partneropen-connector-0.1.0.zip
  /partneropen-connector-0.1.0.zip.sha256
  /api/connector-manifest
  /api/health
  /api/consent-scopes
  /terms
  /privacy
  ```

  Confirm deployed source has no `MPC`, no visitor-tracking claim, no stale unavailable-MCP/GUI FAQ, and the `MILESTONE 2 / BY REQUEST` network CTA plus ZIP/checksum still load.

- [ ] **Step 6: Commit any verification-only corrections and record deployment evidence**

  If a concrete browser/build failure requires a correction, add the focused fix and rerun the affected check before committing. Record the final production URL, deployment status, build/test result, and mobile overflow result in the delivery response.
