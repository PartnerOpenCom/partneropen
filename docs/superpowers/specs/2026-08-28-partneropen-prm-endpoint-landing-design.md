# PartnerOpen PRM Endpoint Landing Design

**Status:** Approved by the user.

## Goal

Explain on the PartnerOpen landing that PartnerOpen can act as a lightweight, host-controlled PRM endpoint and partner-facing layer alongside an existing PRM and CRM, then compare that position directly with Kiflo Partner Directory and PartnerPage.io.

## Audience

Hosts and partner-program operators who already use, or may adopt, tools such as Partnero, Introw, PartnerStack, a CRM, or a directory builder.

## Positioning

Use **PRM** (Partner Relationship Management), not RPM. PartnerOpen is not presented as a replacement for a full PRM or CRM. It is the controlled partner-facing Space and endpoint layer that can connect to the systems already responsible for partner operations, pipeline, and revenue.

Approved primary copy:

> **Connect your PRM and CRM — without replacing them**
>
> Use PartnerOpen as the partner-facing layer for your ecosystem. Publish the right partner Space, control access and visibility, and connect it to the PRM and CRM tools you already use.

The copy must avoid claiming a ready-made two-way sync or named native connectors unless the implementation proves those capabilities. Named products are described as tools PartnerOpen is designed to work alongside.

## Landing structure

Keep the existing English host-first landing and its current section order. Extend the existing `comparison-screen` rather than adding a new route or a new page:

1. Add a three-card `integration-grid` before the comparison table:
   - Partner-facing Space.
   - PRM endpoint.
   - CRM connection.
2. Add a neutral line linking to Partnero, Introw, and PartnerStack with `target="_blank"` and `rel="noopener noreferrer"`.
3. Replace the generic “Typical external partner page” table with a four-column direct comparison:
   - Dimension.
   - PartnerOpen.
   - Kiflo Partner Directory.
   - PartnerPage.io.
4. Keep the comparison disclaimer: product positioning is based on public vendor material and feature availability can vary.

## Comparison dimensions

Use factual, positioning-level rows only:

- Primary job.
- Public profiles and directory.
- Partner self-service and approval.
- PRM/CRM role.
- Host control.
- Deployment/ownership model.
- Best fit.

Do not make unsupported claims that a competitor lacks a feature. Describe each product’s documented center of gravity instead.

## Visual and responsive behavior

Reuse existing `.integration-grid`, `.integration-card`, `.integration-mark`, `.integration-status`, `.comparison-table-wrap`, and `.comparison-table` styles. Expand the table minimum width for the fourth column and preserve horizontal scrolling on narrow screens. Add only focused styles for linked product headers and the integration-tool line.

## Acceptance criteria

- The landing explicitly uses “PRM” and contains “Connect your PRM and CRM — without replacing them”.
- Partnero, Introw, and PartnerStack are named and linked to their official product pages.
- The comparison table has explicit PartnerOpen, Kiflo Partner Directory, and PartnerPage.io columns and official links for Kiflo and PartnerPage.
- The old generic “Typical external partner page” comparison is removed.
- No claim of ready-made two-way sync or native named connectors is introduced.
- Existing host-first, consent, local-first, no-payment, M1/M2, legal, and CTA copy remains intact.
- The landing copy test and TypeScript check pass.
