# PartnerOpen PRM Endpoint Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add accurate PRM/CRM endpoint positioning and a direct PartnerOpen vs Kiflo vs PartnerPage comparison to the existing landing.

**Architecture:** Keep the current single-page Next.js landing and its existing `comparison-screen`. Store comparison and integration copy in typed constants in `apps/cloud/app/page.tsx`; render the new cards and four-column table from those constants. Reuse existing integration/table styles and add only the CSS required for the additional column and linked product headers.

**Tech Stack:** Next.js App Router, React, TypeScript/TSX, static CSS, Node built-in test runner.

## Global Constraints

- Use **PRM** (Partner Relationship Management), never “RPM”, in the new landing copy.
- Do not claim ready-made two-way sync or native named connectors unless the code proves those capabilities.
- Keep existing host-first, consent, local-first, no-payment, M1/M2, legal, and CTA copy unchanged.
- Use official product URLs for Partnero, Introw, PartnerStack, Kiflo, and PartnerPage.
- External product links must use `target="_blank"` and `rel="noopener noreferrer"`.
- Do not modify the unrelated `simple-store` project.
- Run only focused checks during implementation; skip formatters, linters, and project-wide suites.

---

### Task 1: Add PRM and CRM integration copy

**Files:**
- Modify: `apps/cloud/app/page.tsx:40-47` (comparison data) and `apps/cloud/app/page.tsx:120-130` (comparison section)
- Test: `apps/cloud/tests/landing-copy.test.mjs`

**Interfaces:**
- Consumes: existing `comparisonRows` and existing `.integration-*` CSS classes.
- Produces: three rendered integration cards and official links for Partnero, Introw, and PartnerStack.

- [ ] **Step 1: Extend the page data with endpoint-safe copy**

Replace the three-column comparison row tuples with four-column tuples shaped as:

```ts
const comparisonRows = [
  ['Primary job', 'Host-controlled partner Space and lightweight PRM endpoint layer.', 'Public directory backed by partner data and PRM workflows.', 'Public partner directory builder.'],
  ['Public profiles and directory', 'Public Space, profiles and approved partner content.', 'Public partner profiles, search and filters.', 'Partner listings and profile pages.'],
  ['Partner self-service and approval', 'Creator publishes through the signed API; Host controls the boundary.', 'Partner profile self-edit with approval.', 'Partner-owned listings.'],
  ['PRM / CRM role', 'Connects to the existing PRM and CRM instead of replacing them.', 'Directory and partner-management workflow.', 'Directory-first; referral capture is central.'],
  ['Host control', 'Scopes, visibility, Global Pause and disconnect.', 'Directory and PRM configuration with approvals.', 'Directory configuration and listing management.'],
  ['Deployment / ownership model', 'WordPress Connector plus a host-controlled public surface.', 'Managed directory and PRM product.', 'Branded directory product on the customer domain.'],
  ['Best fit', 'Own the partner Space and connect the existing stack.', 'Directory plus partner operations.', 'Launch a public partner directory quickly.'],
] as const;
```

Add a typed constant directly before `comparisonRows`:

```ts
const integrationCards = [
  ['01', 'Partner-facing Space', 'Publish a branded directory, partner profiles and approved content without changing the systems that run your business.', 'PUBLIC SURFACE'],
  ['02', 'PRM endpoint', 'Use PartnerOpen as a lightweight endpoint layer for the partner-facing surface while your existing PRM keeps its operational workflows.', 'ENDPOINT-FIRST'],
  ['03', 'CRM connection', 'Keep pipeline, ownership and revenue operations in the CRM you already use; PartnerOpen stays focused on the Space.', 'STACK-ALIGNED'],
] as const;
```

- [ ] **Step 2: Render the integration cards and named product links**

Inside the existing `comparison-screen`, immediately after the section lede and before the comparison table, render:

```tsx
<div className="integration-grid" aria-label="PRM and CRM connection paths">
  {integrationCards.map(([number, title, copy, status]) => (
    <article className="integration-card" key={number}>
      <div className="integration-mark">{number}</div>
      <h3>{title}</h3>
      <p>{copy}</p>
      <span className="integration-status">{status}</span>
    </article>
  ))}
</div>
<p className="integration-tools">
  Designed to work alongside <a href="https://www.partnero.com/features/partner-portal" target="_blank" rel="noopener noreferrer">Partnero</a>, <a href="https://www.introw.io/product" target="_blank" rel="noopener noreferrer">Introw</a> and <a href="https://partnerstack.com/" target="_blank" rel="noopener noreferrer">PartnerStack</a>.
</p>
```

Update the section heading and lede to:

```tsx
<p className="eyebrow">01 / PARTNER SPACE + PRM ENDPOINT</p>
<h2 id="comparison-title">Connect your PRM and CRM — without replacing them.</h2>
<p className="section-lede">Use PartnerOpen as the partner-facing layer for your ecosystem. Publish the right partner Space, control access and visibility, and connect it to the PRM and CRM tools you already use.</p>
```

- [ ] **Step 3: Render the direct comparison table**

Replace the existing three-column table header/body with:

```tsx
<thead>
  <tr>
    <th scope="col">Dimension</th>
    <th scope="col">PartnerOpen</th>
    <th scope="col"><a href="https://www.kiflo.com/product/partner-directory" target="_blank" rel="noopener noreferrer">Kiflo Partner Directory ↗</a></th>
    <th scope="col"><a href="https://partnerpage.io/" target="_blank" rel="noopener noreferrer">PartnerPage.io ↗</a></th>
  </tr>
</thead>
<tbody>
  {comparisonRows.map(([dimension, partnerOpen, kiflo, partnerPage]) => (
    <tr key={dimension}>
      <th scope="row">{dimension}</th>
      <td><span className="comparison-mark">✓</span>{partnerOpen}</td>
      <td>{kiflo}</td>
      <td>{partnerPage}</td>
    </tr>
  ))}
</tbody>
```

Set the comparison note to:

```tsx
<p className="comparison-note">A positioning comparison based on public product material, not a claim that one product replaces the others. Confirm current features and pricing with each provider.</p>
```

- [ ] **Step 4: Add failing/contract assertions for the new landing copy**

In `apps/cloud/tests/landing-copy.test.mjs`, add assertions that the source contains:

```js
assert.match(source, /Connect your PRM and CRM — without replacing them/);
assert.match(source, /lightweight PRM endpoint/);
assert.match(source, /Partnero/);
assert.match(source, /Introw/);
assert.match(source, /PartnerStack/);
assert.match(source, /Kiflo Partner Directory/);
assert.match(source, /PartnerPage\.io/);
assert.match(source, /https:\\/\\/www\.kiflo\.com\\/product\\/partner-directory/);
assert.match(source, /https:\\/\\/partnerpage\.io/);
assert.doesNotMatch(source, /RPM endpoint/);
assert.doesNotMatch(source, /Typical external partner page/);
```

- [ ] **Step 5: Run the focused landing test**

Run: `npm run test:cloud -- --test-name-pattern="landing copy"`

Expected: the landing copy test passes and no stale generic-comparison assertion fails.

---

### Task 2: Style the four-column comparison without breaking responsive layout

**Files:**
- Modify: `apps/cloud/app/globals.css:267-272,410-420`

**Interfaces:**
- Consumes: `.integration-tools` and linked comparison-table headers rendered by Task 1.
- Produces: readable three-card integration grid, four-column scrollable comparison table, and visible external-link affordance.

- [ ] **Step 1: Add styles for the tool-link line and linked table headings**

Add focused rules next to the existing integration/comparison rules:

```css
.integration-tools {
  margin: 22px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}
.integration-tools a,
.comparison-table thead a {
  color: var(--blue);
  text-decoration: none;
}
.integration-tools a:hover,
.comparison-table thead a:hover {
  color: var(--text);
  text-decoration: underline;
}
```

- [ ] **Step 2: Widen the comparison table for its fourth column**

Change the existing comparison table rules to:

```css
.comparison-table { width: 100%; min-width: 1080px; border-collapse: collapse; text-align: left; }
.comparison-table tbody td:nth-child(2),
.comparison-table tbody td:nth-child(3),
.comparison-table tbody td:nth-child(4) { width: 27%; color: var(--text); }
```

Retain the existing `comparison-table-wrap { overflow-x: auto; }` behavior so mobile users can scroll the table rather than receive clipped content. Do not alter the existing breakpoint structure beyond these width rules.

- [ ] **Step 3: Check the CSS diff for formatting errors**

Run: `git diff --check -- apps/cloud/app/page.tsx apps/cloud/app/globals.css apps/cloud/tests/landing-copy.test.mjs`

Expected: no whitespace errors.

---

### Task 3: Verify type safety and actual page behavior

**Files:**
- Test: `apps/cloud/tests/landing-copy.test.mjs`
- Test: browser smoke test against the running Cloud app

**Interfaces:**
- Consumes: completed page, styles, and copy assertions from Tasks 1–2.
- Produces: verified landing copy, valid TSX, reachable external links, and responsive comparison markup.

- [ ] **Step 1: Run the complete focused Cloud test directory**

Run: `npm run test:cloud`

Expected: all existing tests pass, including the new landing assertions.

- [ ] **Step 2: Run the Cloud TypeScript check**

Run: `npx tsc --noEmit -p apps/cloud/tsconfig.json`

Expected: exit code 0 and no TypeScript diagnostics.

- [ ] **Step 3: Launch the actual Cloud landing locally**

Run: `npm run dev:cloud -- --hostname 127.0.0.1 --port 3100`

Use the browser tool against `http://127.0.0.1:3100/` and verify:

- the `Connect your PRM and CRM` heading is visible;
- three integration cards render before the comparison table;
- the table headers name PartnerOpen, Kiflo Partner Directory, and PartnerPage.io;
- clicking/inspecting each named external link shows the exact official URL and opens a new tab target;
- at a narrow viewport the table remains horizontally scrollable and the cards stack according to existing breakpoints.

- [ ] **Step 4: Stop the development server and record the result**

Stop the named development process through the process tool after the browser smoke test. Record the verified URL/viewport results in the final response.

- [ ] **Step 5: Commit the completed feature**

```bash
git add apps/cloud/app/page.tsx apps/cloud/app/globals.css apps/cloud/tests/landing-copy.test.mjs docs/superpowers/specs/2026-08-28-partneropen-prm-endpoint-landing-design.md docs/superpowers/plans/2026-08-28-partneropen-prm-endpoint-landing.md
git commit -m "feat: position PartnerOpen as PRM endpoint"
```
