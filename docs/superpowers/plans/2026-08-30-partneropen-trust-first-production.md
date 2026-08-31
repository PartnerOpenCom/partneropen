# PartnerOpen Trust-First Production Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the PartnerOpen landing immediately understandable and credible to a skeptical, time-poor WordPress webmaster, then deploy and verify the production surface.

**Architecture:** Keep the existing single-page Next.js landing and expose the built Connector package as a versioned static asset under `apps/cloud/public`. Make the Host path primary, replace misleading disabled actions with honest informational actions, surface concrete trust evidence immediately after the hero, and retain deeper consent/API details as optional sections. Use neutral comparison styling and responsive trust cards while preserving the full desktop tables.

**Tech Stack:** Next.js 16, React 19, TypeScript, plain CSS, Node test runner, Python ZIP packaging script, Vercel CLI.

## Global Constraints

- PartnerOpen remains the product name; no stale standalone `PartnerPage` or `RPM` terminology.
- Host is the primary landing persona; Creator/M2 is secondary and must not appear as a fake active submission flow.
- The primary Host action must download a real ZIP asset, not JSON metadata.
- Technical claims must match the Connector implementation: WordPress-owned durable state, explicit consent scopes, Global Pause/disconnect, allowlisted public output, no visitor dossiers, and no payment processing.
- External links use `target="_blank" rel="noopener noreferrer"`.
- Do not promote production until tests, typecheck, local browser smoke, and deployed browser smoke pass.

---

### Task 1: Make the Host value proposition explicit

**Files:**
- Modify: `apps/cloud/app/page.tsx:95-119`
- Test: `apps/cloud/tests/landing-copy.test.mjs`

**Interfaces:**
- Preserve existing `HeroPhonePreview` and `M2RequestDialog` imports.
- Hero primary CTA points to `/partneropen-connector-0.1.0.zip`.
- Hero secondary action points to `#trust-proof` and is a normal anchor.

- [ ] Replace the hero eyebrow, heading, and lead with copy that identifies PartnerOpen as a GPL WordPress Connector publishing a host-controlled partner Space and explains that it works beside existing PRM/CRM tools.
- [ ] Keep one primary Host button labelled `Download the Connector` and replace the hero Creator button with `See how control works`.
- [ ] Add an explicit micro-proof line: open source/GPL, WordPress control point, no visitor tracking, no payments processed.
- [ ] Keep roadmap/MCP language out of the hero.
- [ ] Add landing assertions for product category, primary/secondary CTA destinations, and the absence of the old hero Creator CTA.

### Task 2: Publish a real Connector download

**Files:**
- Create: `apps/cloud/public/partneropen-connector-0.1.0.zip`
- Create: `apps/cloud/public/partneropen-connector-0.1.0.zip.sha256`
- Modify: `apps/cloud/app/page.tsx:215-218`
- Test: `apps/cloud/tests/landing-copy.test.mjs`

**Interfaces:**
- `/partneropen-connector-0.1.0.zip` is a static file containing the output of `distribution/build-connector-plugin.sh` without tests.
- `/partneropen-connector-0.1.0.zip.sha256` contains the generated checksum.
- `/api/connector-manifest` remains an inspectable secondary technical artifact.

- [ ] Build the package with `sh distribution/build-connector-plugin.sh`.
- [ ] Copy the generated ZIP and checksum into `apps/cloud/public`.
- [ ] Change the install primary button to `Download Connector v0.1.0` with the ZIP href and `download` attribute.
- [ ] Change the manifest button to `Inspect Connector manifest` and add a checksum link.
- [ ] Verify the ZIP with `unzip -t` and verify the public asset is served by Next/Vercel.

### Task 3: Remove misleading M2 interaction

**Files:**
- Modify: `apps/cloud/app/components/M2RequestDialog.tsx:57-123`
- Modify: `apps/cloud/app/page.tsx:114,206`
- Modify: `apps/cloud/app/globals.css` for any affected button/modal styles
- Test: `apps/cloud/tests/m2-dialog.test.mjs`

**Interfaces:**
- `M2RequestDialog` becomes an informational M2 scope dialog; it does not render a disabled form or imply that a request is submitted.
- Default trigger label is `See M2 scope`.

- [ ] Remove the disabled contact form and its inactive-status copy.
- [ ] Rename the dialog action and copy to state clearly that M2 is not provisioned in the current milestone and that the dialog only describes scope.
- [ ] Keep the actual M2 feature list and payment boundary explanation.
- [ ] Use the informational dialog only in the secondary M2 use-case card; do not place it in the hero.
- [ ] Update tests to assert the dialog is informational and contains no disabled submission form.

### Task 4: Surface concrete trust proof and neutralize comparison

**Files:**
- Modify: `apps/cloud/app/page.tsx:120-170`
- Modify: `apps/cloud/app/globals.css` around integration/comparison styles
- Test: `apps/cloud/tests/landing-copy.test.mjs`

**Interfaces:**
- Add a `trustProof` data array with four items: WordPress-owned state, explicit scopes, pause/disconnect, and no visitor dossiers.
- Add section id `trust-proof` immediately after the hero.
- The comparison table remains four columns but no longer renders an automatic green checkmark in every PartnerOpen row.

- [ ] Add the `Proof in 30 seconds` section immediately after hero with concise concrete evidence and links to `#consent`, `#owner-control`, `#never-collected`, and `/api/connector-manifest`.
- [ ] Define PRM on first use as `Partner Relationship Management (PRM)`.
- [ ] Replace first-use M1/M2 shorthand with `Available in the current release` and `By request` where the landing is targeting Hosts.
- [ ] Remove unconditional `comparison-mark` checkmarks; use neutral copy-only cells or a neutral `fit` marker.
- [ ] Preserve factual Kiflo/PartnerPage.io comparison and its public-material disclaimer.

### Task 5: Make trust details scan safely on mobile

**Files:**
- Modify: `apps/cloud/app/globals.css` mobile media queries
- Modify: `apps/cloud/app/page.tsx` only if semantic mobile labels are needed

**Interfaces:**
- Desktop tables remain available for full comparison.
- At widths below 800px, trust proof cards are one column and dense tables expose readable row labels without breaking the page shell.

- [ ] Add responsive styling for `.trust-proof-grid` and `.trust-proof-card`.
- [ ] Add mobile row-label styling or card presentation for comparison and consent data; retain `overflow-x: auto` only as a fallback for complete tables.
- [ ] Verify no horizontal overflow exists outside intentional table wrappers at 390px.

### Task 6: Run contract verification and commit

**Files:**
- Test: `apps/cloud/tests/landing-copy.test.mjs`
- Test: `apps/cloud/tests/m2-dialog.test.mjs`
- Test: `apps/cloud/tests/rename-contract.test.mjs`

- [ ] Run `npm run test:cloud` and require zero failures.
- [ ] Run `npx tsc --noEmit -p apps/cloud/tsconfig.json`.
- [ ] Run `git diff --check`.
- [ ] Commit the implementation and generated public release asset on `feat/prm-endpoint-landing`.

### Task 7: Verify and deploy production

**Files:**
- No source changes.

- [ ] Start the actual Next app and use a browser at desktop and 390px widths to verify hero hierarchy, CTA destinations, trust-proof cards, tables, and intentional overflow only.
- [ ] Run `vercel deploy . --project partneropen --prod --yes` from the repository root so the linked project applies its `apps/cloud` root directory; require a successful `@partneropen/cloud` `next build`.
- [ ] Open `https://partneropen.com` and verify the new hero, real ZIP response, trust-proof section, M2 informational dialog, and comparison layout.
- [ ] Run `vercel ls partneropen` and record the Ready production deployment URL and inspector URL.
