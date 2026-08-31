# PartnerOpen Production Landing Redesign

**Date:** 2026-08-30  
**Status:** Approved for implementation  
**Scope:** Full visual and narrative redesign of the public PartnerOpen landing page, based on the Network Admin Control Plane design and the supplied product copy.

## Goal

Replace the current text-first landing presentation with a production-grade visual narrative that explains PartnerOpen as a safe WordPress Connector and partner-network framework. The page must make the Host/Creator boundary, current product value, admin audit logs, aggregate usage data, MCP, and PartnerOpen GUI legible within the first screen and the numbered sections.

## Audience and promise

The primary audience is a WordPress Host who wants to create a partner-facing Space without surrendering control of the website or replacing an existing PRM/CRM. The secondary audience is a Creator who needs a clear publication path and predictable access boundary.

The landing promises:

- simple GPL WordPress installation;
- no external tooling or services required for the Host-side setup;
- PartnerOpen is free for the user;
- signed API, MCP connector, and PartnerOpen GUI are available publication paths;
- Hosts and Creators can work under their own commercial and legal terms;
- explicit consent, scoped access, pause/disconnect controls, and full administrative audit logs;
- aggregate placement metrics instead of visitor-level tracking.

The page must not describe aggregate metrics as visitor tracking. It must use `MCP`, never `MPC`.

## Availability decision reconciliation

The earlier product decision for this landing release takes precedence over the baseline availability wording in `docs/superpowers/specs/2026-08-30-partneropen-network-admin-design.md`. For public product copy, PartnerOpen GUI, the MCP Connector, Site-admin email invitations and passwordless magic-link Creator access are treated as available paths now. The network-admin design remains the architecture for the stateful control plane and its multi-Host administration/distribution package remains `MILESTONE 2 / BY REQUEST`; its historical statement that these access/publication surfaces were not yet provisioned is superseded for this landing release.

## Product boundary shown on the page

WordPress remains the Host-controlled authority for durable connection state, consent, Spaces, published snapshots, Global Pause, disconnect, public rendering, and aggregate placement counters. PartnerOpen provides the partner-facing framework, Creator access experience, signed publication paths, GUI, and administrative event timeline.

The Host can install and configure the Connector, set the prefix and Creator contact, grant or withdraw consent scopes, pause or resume public routes, withdraw consent, disconnect, and explicitly delete local snapshots. The Host cannot edit Creator Space content, impersonate the Creator, operate Creator publishing controls, or receive visitor profiles or visitor-level event streams.

The Creator can work on assigned Spaces and publish through the approved signed API, MCP, or PartnerOpen GUI. Creator access begins through a Site-admin email invitation and a passwordless magic link. The page should describe this as a controlled invitation flow, not open registration or OAuth.

## Narrative and section order

The page uses the supplied numbered information architecture while keeping the existing technical evidence links and download path.

1. **Hero / How to connect**
   - Eyebrow: `SET PARTNER SAFE SPACE VIA GPL WORDPRESS CONNECTOR`.
   - Headline: `Partner directory. WordPress control.`.
   - Explain a host-controlled partner Space, Creator collaboration, existing PRM/CRM compatibility, and aggregate placement metrics / verified usage data.
   - Primary CTA downloads Connector v0.1.0.
   - Secondary CTA opens the Partner Network offering section.
   - Add a compact USP strip: `SAFE`, `EASY TO SET`, `FULL ADMIN LOGS`.
   - Phone visual shows a WordPress Connector setup/connection screen with consent scope status and a recent administrative event timeline. It is a product interface preview, not visitor analytics.

2. **00 / TRUST YOU CAN VERIFY. FOR BOTH PARTIES**
   - Explain transparent collaboration, durable WordPress state, independent Creator interface, pause/disconnect, and no visitor-level tracking.
   - Retain links to manifest, health, consent scopes, and privacy boundary.

3. **00 / SAFE COLLABORATION**
   - Present Host, Creator, and their shared boundary.
   - State that PartnerOpen provides the framework, consented publication records, and verified aggregate data; payments, settlement, and legal terms stay between participants.

4. **01 / PARTNER SPACE + PRM ENDPOINT**
   - Position PartnerOpen as a partner-facing Space and lightweight endpoint layer that works beside an existing PRM/CRM.
   - Keep comparison context with Partnero, Introw, PartnerStack, and the existing public comparison table.
   - Add the value that it does not replace operational systems.

5. **02 / EASY WORK WITH PARTNEROPEN**
   - Show: install Connector; set Space and invite Creator by email; agree consent boundary; publish through signed API, MCP, or GUI; retain control and logs.
   - Explicitly say no open registration, no Host-side external tooling/services requirement, and no OAuth integration required on the Host site.

6. **03 / HOST CONTROL / CREATOR FREEDOM**
   - Visually separate controls owned by Host from Creator freedom.
   - Explain Global Pause, consent withdrawal, disconnect, and the limits on Host moderation.
   - Explain that administrative proofs/audit logs are not silently deleted by normal content operations.

7. **04 / SAFE, TRANSPARENT, LOCAL**
   - Replace the current dense scope presentation with a clearer recipient/retention matrix. Keep actual Connector consent scope data as the source for the rendered rows.

8. **05 / LINK TRANSPARENCY**
   - Explain same-origin resolver links, disclosure, sponsored/nofollow behavior, and why raw external destinations are not exposed in rendered public blocks.

9. **06 / AGENT-CONTEXT PACK**
   - Show the canonical public files: `AGENTS.md`, `llms.txt`, `ai-context.json`, `manifest.json`, `sitemap.xml`.
   - Explain positive-field allowlisting and removal of destinations, emails, identifiers, payout data, and unknown future keys.

10. **07 / NEVER COLLECTED BY PARTNEROPEN**
    - Use an aggregate placement metric visual with date, placement identifier, and integer count.
    - State no cookies, IP addresses, fingerprints, visitor IDs, or visitor-level click events.
    - Explain that aggregate placement data can support an agreed share calculation; PartnerOpen does not process payments.

11. **08 / TWO USE CASES**
    - Use Case 01: available now — Host collaborators for free and take a share using the Connector, signed API, MCP, or GUI.
    - Use Case 02: `MILESTONE 2 / BY REQUEST` — publishing network for multi-Host Creator distribution and network administration, with a `Connect with us to discuss` CTA.

12. **09 / SIGNED API / MCP**
    - Show the Connector install path, package metadata, signed API, MCP, and PartnerOpen GUI as available ways to publish.
    - Keep download, manifest, and SHA-256 links.

13. **Questions / Answers and footer**
    - Update FAQ for Creator invitation magic links, GUI/MCP availability, Host controls, audit logs, link transparency, consent withdrawal, payments, and data boundary.

## Visual system

The redesign uses a dark editorial/technical visual language with stronger contrast and fewer repeated card treatments than the current page:

- dense black/navy base with warm paper panels for evidence sections;
- large typographic section numbers and thin rules to preserve the technical-document feel;
- one accent color for safe/connected states and one warning color for pause/disconnect;
- hero split layout with a larger phone/interface composition and a visible USP label cluster;
- CSS/DOM phone mockup so the visual is responsive, accessible, and does not require an unverified screenshot asset;
- mobile layout collapses all grids to one column, keeps CTAs inside the viewport, and preserves readable table overflow handling;
- motion remains pointer/parallax-only and honors `prefers-reduced-motion`.

The phone preview contains only synthetic interface state such as `CONNECTED`, `CONSENT GRANTED`, `CREATOR INVITED`, and `PUBLISH VERIFIED`. It contains no visitor-level numbers or claims that require a live account.

## Implementation boundaries

- Modify the existing Cloud landing page and its existing visual components/styles; do not add authentication, database, or publication functionality as part of this landing task.
- Keep existing public API links, Connector download artifact, legal links, and manifest-derived version/package metadata working.
- Keep the public landing stateless.
- Use copy and visual labels to describe GUI/MCP as currently available, per the product decision in this task.
- Preserve technical truth around WordPress authority and no visitor tracking, while adding the planned network-admin positioning from the approved control-plane design.

## Verification

Before production deployment:

- run the Cloud lint/type/build checks;
- launch the actual Cloud app and inspect desktop and mobile layouts in a browser;
- verify no horizontal overflow on mobile;
- verify hero CTAs, download artifact, manifest, health, consent, privacy, terms, and resolver links;
- verify the phone preview exposes its purpose through accessible text;
- verify the landing contains no `MPC` typo, no claim of visitor tracking, and no stale FAQ saying GUI/MCP are unavailable;
- deploy the production target through the existing Vercel workflow and smoke-test the deployed URL.
