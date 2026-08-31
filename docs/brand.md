# PartnerOpen brand

## Locked names

| Surface | Required name |
|---|---|
| Product | **PartnerOpen** |
| WordPress plugin | **PartnerOpen Connector** |
| Plugin slug and directory | `partneropen-connector` |
| Public web site | **PartnerOpen Cloud** |
| Repository | `partneropen` |
| Vercel project | `partneropen` |
| Cloud application directory | `apps/cloud` |
| Canonical public site | `https://partneropen.com` |
| Legal pages | `https://partneropen.com/terms`, `https://partneropen.com/privacy` |

Use these names exactly in headings, package metadata, support replies, screenshots, and distribution material. Do not shorten the plugin name to “Connector” in user-facing copy when the full name is unambiguous. The namespace is `PartnerOpen\Connector\` and the REST namespace is `partneropen/v1`.

## Package and payment boundary

The full `partneropen-connector` package is for direct self-hosted installation and retains the resolver and optional affiliate-link capability. The separate directory-safe artifact is the WordPress.org candidate; it rejects link and affiliate blocks and hard-disables resolver redirects. Do not describe the full package as directory-safe or the directory-safe candidate as the full feature set.

M1 is local-first with a stateless PartnerOpen Cloud; hosted tenant, billing, and service/network capabilities are deferred to M2 and are not built. PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties.

## Voice

PartnerOpen copy is factual, neutral, direct, and specific about what is stored, sent, and enforced. Prefer short sentences and observable behavior:

- say “published snapshot” rather than implying editorial endorsement;
- say “allowlisted HTTPS destination” rather than promising safety beyond the check performed;
- say “aggregate daily click counter” rather than “audience intelligence”;
- say “deferred” when a Cloud tenant capability is not built; and
- explain consent as a choice for a named data scope, recipient, and retention period.

Do not promise ranking, discoverability, approval, compliance outcomes, conversion rates, or moderation results. Do not imply that PartnerOpen replaces WordPress, Cloud infrastructure, or an external service. Do not describe disconnect as removing or revoking a partner: it is withdrawal of consent and unpairing, while local snapshots remain until explicit deletion.

## Mandatory link disclosure

Every external destination rendered by the Connector MUST show this visible disclosure immediately with the link:

> **Disclosure: This is an affiliate link.**

The existing `partneropen-space__disclosure` element also includes visible destination-host text exactly in the form `Goes to <host>`. A site owner may add factual relationship detail after that sentence when required by its policy. The snapshot `links.*.disclosure` value must never be empty. The link itself MUST be same-origin (`/partneropen/go/{link_id}/{placement_id}`), and its final rendered attribute MUST be exactly:

```html
rel="sponsored nofollow noopener"
```

The destination is checked against the snapshot HTTPS `allowed_hosts` list before a resolver redirect. Never expose a raw external `href` in a rendered Space. If `affiliate_service` consent is withdrawn, the label remains plain text without an anchor or resolver href and the resolver returns `404` with reason `consent`.

## Forbidden terms and claims

Do not use:

- sector-specific wagering vocabulary in public product copy;
- language that presents a link as hidden, cloaked, disguised, or guaranteed to bypass review;
- “guaranteed ranking”, “guaranteed placement”, “guaranteed approval”, or equivalent outcomes;
- claims that PartnerOpen moderates or certifies a partner, destination, or publication;
- “track every visitor”, “visitor profile”, or any promise of individual-level measurement;
- “instant payout”, “conversion guarantee”, or other financial performance promises; and
- language that suggests the owner approves each publication, can pause one Space, or can manage a partner through a revoke/remove control.

Do not invent a brand, service, network, tenant, or product name for a capability that belongs to the deferred Cloud tenant backend. The M1 Cloud site is a stateless public site, not a hosted editor.

## OMP naming limitation

The external OMP chat session title is not controlled by this repository. All project-facing references in the repository use **PartnerOpen**; no repository or Vercel setting is represented as changing the external chat title.
