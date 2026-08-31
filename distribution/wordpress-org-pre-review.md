# PartnerOpen Connector — WordPress.org pre-review request

**Subject:** Pre-review request: PartnerOpen Connector

Hello WordPress.org Plugins Team,

We request a pre-review of **PartnerOpen Connector** before any upload. The plugin is GPLv2+-licensed and provides the local WordPress side of PartnerOpen. Its directory-safe candidate readme declares `Tested up to: 7.1` and a short description under 150 characters.

## Pre-submission checklist

- **BLOCKER — real contributor username not supplied:** replace the placeholder `Contributors: partneropenteam` in the directory-safe readme with the submitting WordPress.org username before upload, and ensure that same username owns the WordPress.org SVN commit. The submitter must use an account whose profile and plugin submission rights are valid.
- The submitter must hold the PartnerOpen name rights required by [WordPress.org guideline 17](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#17-plugins-must-respect-trademarks-copyrights-and-project-names) before submission and must be able to demonstrate that authorization if requested.
- Confirm the packaged directory-safe plugin contains only the GPL Connector source, that the package root is `partneropen-connector-directory/`, and that the stable tag matches the submitted release.
- Submit only the directory-safe artifact described below. The full resolver/affiliate artifact is for direct self-hosted installation and is not the WordPress.org candidate.

## Two distribution artifacts

The full self-hosted package is built with `distribution/build-connector-plugin.sh`. It retains the same-origin resolver and optional affiliate-link capability for direct, non-directory installation. The separate WordPress.org directory-safe package is built with `distribution/build-directory-plugin.sh`; it sets `PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD=true`, rejects link and affiliate blocks at snapshot validation, renders any supplied link labels as plain text, and hard-returns `404` from the resolver. Both artifacts use the same local-first Connector, consent model, WordPress storage, and lifecycle cleanup. The directory-safe artifact is a package variant for directory policy compliance, not a second runtime architecture.

The directory-safe artifact is the WordPress.org candidate. The full package must not be uploaded to the directory or described as directory-safe. Direct users who need resolver/affiliate behavior must install the full package themselves.

## Why the full package uses a same-origin resolver

The review checklist's affiliate-link requirement is: **“Affiliate links should not be hidden.”** The [detailed WordPress.org guideline](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#12-public-facing-pages-on-wordpress-org-readmes-must-not-spam) likewise says: **“In all cases, affiliate links must be disclosed and must directly link to the affiliate service, not a redirect or cloaked URL.”** PartnerOpen agrees with the transparency requirement. The full package does not use its resolver to conceal an affiliate destination: every published outbound link is visibly identified as an affiliate link, includes the non-configurable disclosure **“Disclosure: This is an affiliate link.”**, appends visible `Goes to <host>` destination-host text, and uses `rel="sponsored nofollow noopener"`. The directory-safe candidate emits no affiliate links and returns `404` from the resolver.

In the full package, the same-origin path is `/partneropen/go/{link_id}/{placement_id}` because it is the enforcement point for **Global Pause** and operator-controlled Space suspension. Global Pause returns no-store `404` responses for every resolver request. A suspended or unpublished Space returns `404`, and its link id does not resolve from another Space. These controls would not reliably stop traffic if HTML contained raw external affiliate URLs. The resolver therefore provides operational stop controls without hiding the destination; published HTML never contains a raw external destination as an `href`.

## Delegated publishing, not auto-posting

A partner publishes an assigned Space only by sending a signed `partneropen/v1` REST request, normally through `apps/cloud/scripts/sync-demo.mjs`. This is owner-authorized delegated publishing: the owner explicitly pairs the site and grants `content_sync`, and the Connector strictly validates the typed snapshot before storing it locally. The plugin does not auto-post to third-party sites, scrape feeds, spin or rewrite content, install code, or provide an automated content-spinning service. There is no hosted partner editor, passwordless login, tenant backend, or Cloud-side publish store in M1.

## External service, consent, and data boundary

**PartnerOpen Cloud** is the named external service. Its default allowlist is `partneropen.com` and `www.partneropen.com`; the `partneropen_connector_cloud_hosts` filter and comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant can replace that allowlist with configured HTTPS origins. Cloud recipients are limited to the resulting allowlist.

The Connector is local-first and makes **zero outbound HTTP requests of its own in M1**. The signed reference client may connect to the site's Connector after the owner grants `cloud_connection` and pairs the one-time code. Six consent scopes are explicit and separately enforced: Cloud connection, Partner invitation email, Content sync, Agent context files, Aggregate click counters, and Affiliate service links. Withdrawing `affiliate_service` publishes labels only as plain text with no anchor and makes the full-package resolver return `404` with reason `consent`; withdrawing `aggregate_metrics` stops new counter writes; withdrawing `agent_pack` makes generated agent routes return no-store `404` responses. Only full Withdraw consent & disconnect revokes the site secret.

PartnerOpen Cloud has no tenant database, hosted editor, pairing store, publish store, metrics ingest, or snapshot copy in M1. The canonical legal URLs are https://partneropen.com/terms and https://partneropen.com/privacy. Do not treat those URLs as live until the PartnerOpen DNS and HTTPS configuration is verified.

## Payment boundary

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 provides publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is a deferred M2 capability and is not available in this submission.

## Cleanup and maintenance

Aggregate counters contain only date, placement, and count and are retained for 90 days. Activation schedules `partneropen_connector_prune_clicks` daily; deactivation clears the event; its callback deletes counters older than 90 days. `uninstall.php`, guarded by `WP_UNINSTALL_PLUGIN`, deletes the connection, consent, secret, pause, Space registry, every `partneropen_snapshot_*` option, click counters, and plugin transients. Optional-scope withdrawal and disconnect are not substitutes for explicit uninstall cleanup; disconnect intentionally retains local snapshots until the owner deletes them.

The plugin is single-site only and does not implement multisite network handling.

## SVN release procedure

1. Build and inspect the directory-safe GPL plugin package; run the standalone PHP tests and disposable smoke harness before packaging.
2. Commit the release files to the WordPress.org SVN **trunk** directory, including the finalized directory-safe readme and source package. Do not claim approval before review.
3. Set the readme `Stable tag` to the release version and copy the reviewed release into `tags/<version>` (for example, `tags/0.1.0`). Ensure trunk and the tag contain matching source and metadata.
4. Commit the tag, then request/complete the WordPress.org review and use the stable tag for the directory release. Future releases repeat the versioned tag process and update `Tested up to` and the changelog.

Regards,
PartnerOpen
