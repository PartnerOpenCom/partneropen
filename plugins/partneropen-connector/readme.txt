=== PartnerOpen Connector ===
Contributors: partneropenteam
Tags: partner pages, consent, disclosed links, WordPress connector
Requires at least: 6.5
Requires PHP: 8.1
Tested up to: 7.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first WordPress connector for consent-gated delegated Spaces, disclosed same-origin links, aggregate clicks, and signed publishing.

== Description ==

PartnerOpen Connector keeps the durable connection, consent, delegated Space records, published snapshots, and aggregate click counters in this site's WordPress database. A partner publishes a validated snapshot with a signed REST request, normally through the reference client at `apps/cloud/scripts/sync-demo.mjs`; the plugin does not include a hosted editor, partner login, tenant service, email delivery, or Cloud-side storage in this milestone.

The owner surface keeps exactly two site-wide controls: Global Pause and Withdraw consent & disconnect. The Status screen also provides optional-scope withdrawal and a separate explicit action to delete local published snapshots. It does not provide content approval/rejection, partner administration, per-Space owner pause, a user-facing audit log, or in-product chat.

Every outbound link uses the same-origin resolver `/partneropen/go/{link_id}/{placement_id}`. Published HTML never contains a raw external destination. Each link has a visible disclosure, visible `Goes to <host>` destination text, and `rel="sponsored nofollow noopener"`. Global Pause, an unpublished or suspended Space, and withdrawn Affiliate service links prevent resolver traffic.

The Connector is single-site only. It has no multisite network-management or network-wide consent behavior.

== Package variants ==

This readme describes the full `partneropen-connector` package for direct self-hosted installation. It retains the same-origin resolver and optional affiliate-link capability. The separate directory-safe artifact, built with `distribution/build-directory-plugin.sh`, is the WordPress.org candidate: it rejects link and affiliate blocks and hard-disables resolver redirects. Do not submit the full package to the WordPress.org directory.

== Consent scopes ==

Consent is granted one scope at a time. Cloud connection and Content sync are required for pairing and snapshot publication. Partner invitation email is optional at the option level and is required only if an invitation is sent. The Connector sends no email itself.

=== Cloud connection ===
Purpose: Pair this site with the signed PartnerOpen client so delegated Space requests can be authenticated.

Data fields: site URL; URL prefix; technical site identifier; connector version.

Recipient: An allowlisted PartnerOpen Cloud host, only when a paired client makes the signed request.

Timing: No outbound request is made by the plugin in this milestone. A client may connect after the owner grants this scope and completes pairing.

Retention: Until consent is withdrawn or the site is disconnected.

=== Partner invitation email ===
Purpose: Record the partner address used for an invitation and service notices for this Space.

Data fields: partner email address; site URL; Space name.

Recipient: Stored on this site and shared with the paired client during pairing when this scope is granted.

Timing: Only during setup/pairing; the Connector sends no email.

Retention: Until consent is withdrawn.

=== Content sync ===
Purpose: Receive the published page snapshot that this site renders.

Data fields: typed page blocks; SEO title and description; link metadata; allowed destination hosts; snapshot version.

Recipient: This site, through the signed Connector API.

Timing: Only when the paired client submits a signed snapshot request and this scope is granted. No Cloud copy is kept in this milestone.

Retention: The latest snapshot stays on this site until it is replaced, explicitly deleted, or removed during uninstall.

=== Agent context files ===
Purpose: Publish AGENTS.md, llms.txt, ai-context.json, manifest.json, and sitemap.xml for the delegated Space.

Data fields: public Space title and summary; public page URLs; allowed block types.

Recipient: Public visitors and AI agents.

Timing: Served only while the Space is published and this scope is granted. Withdrawal returns these routes as no-store 404 responses.

Retention: Served while the Space is published; local snapshot data remains until replaced, deleted, or uninstall.

=== Aggregate click counters ===
Purpose: Record daily click totals per placement so the partner can measure placements.

Data fields: date; placement identifier; click count. No cookies, IP addresses, User-Agent values, referrers, fingerprints, visitor identifiers, or visitor-level events are collected.

Recipient: Stored on this site and readable by a paired client through the signed metrics route when this scope is granted. The plugin makes no metrics request to Cloud in this milestone.

Timing: Collection occurs only for resolver traffic while this scope is granted. Withdrawal stops new counter increments immediately.

Retention: A daily `partneropen_connector_prune_clicks` cron deletes data older than 90 days. Deactivation clears the cron event; uninstall deletes the counters.

=== Affiliate service links ===
Purpose: Allow approved links supplied by a connected affiliate service to be published in this Space with disclosure.

Data fields: approved public link identifier; placement identifier; disclosure text; allowlisted destination host.

Recipient: Published on this site with disclosure; no service credentials are stored here.

Timing: Only while this scope is granted. Withdrawal stops link publication: labels are plain text with no anchor, and the resolver returns 404 with reason `consent`.

Retention: Until consent is withdrawn, the snapshot is deleted, or the plugin is uninstalled.

== External services ==

PartnerOpen Cloud is the named external service associated with the Connector. Cloud hosts allowed by default are `partneropen.com` and `www.partneropen.com`. The allowlist can be replaced with the `partneropen_connector_cloud_hosts` filter or the comma-separated `PARTNEROPEN_CONNECTOR_CLOUD_HOSTS` constant; only configured HTTPS origins are accepted.

In this milestone the Connector is local-first and makes zero outbound HTTP requests of its own, including before and after pairing. The signed reference client may connect to the site's Connector using an allowlisted Cloud base URL after the owner grants Cloud connection consent. The purpose is pairing and delegated snapshot/status/metrics operations. Data covered by consent consists only of the site URL, prefix, technical site id, connector version, optional partner email, typed snapshot/public context fields, and aggregate date/placement/count; visitor identifiers and service credentials are never exchanged. PartnerOpen Cloud has no tenant, hosted-editor, pairing-store, publish-store, or metrics-ingest service in this milestone and retains no Connector snapshot or metrics copy.

The canonical Terms of Use and Privacy Notice are https://partneropen.com/terms and https://partneropen.com/privacy. They describe the service, data categories, timing, recipients, retention, consent withdrawal, and the fact that the Connector does not collect visitor identifiers.

== Payment boundary ==

PartnerOpen does not process payments, hold funds, calculate payouts, issue invoices, or settle participant shares. M1 provides publishing and aggregate measurement data only. Payment terms and settlement remain outside PartnerOpen under agreements between the participating parties. Billing is deferred to M2 and is not available in this milestone.

== Installation ==

1. Upload the `partneropen-connector` directory to `/wp-content/plugins/`.
2. Activate PartnerOpen Connector from Plugins.
3. Open PartnerOpen, choose a URL prefix and partner email, review every consent scope, and acknowledge the Terms of Use and Privacy Notice.
4. Share the displayed one-time pairing code with the partner operator. The code expires after 15 minutes.

The plugin is single-site only; it does not implement multisite network handling.

== Frequently Asked Questions ==

= What does Global Pause do? =

Global Pause makes all PartnerOpen URLs return 404 while preserving partner work and the latest local snapshot. Resume restores the last published local snapshot when its Space is active.

= What happens when an optional scope is withdrawn? =

Only that scope's behavior stops. Withdrawing `affiliate_service` stops link publication, renders labels as plain text without an anchor, and makes the resolver return 404 with reason `consent`. Withdrawing `aggregate_metrics` stops new click-counter writes. Withdrawing `agent_pack` returns its generated routes as no-store 404 responses. Optional-scope withdrawal does not revoke the site key; only Withdraw consent & disconnect revokes the key.

= What happens on Withdraw consent & disconnect? =

The full Withdraw consent & disconnect action withdraws all consent, stops data exchange, revokes this site's key, marks the connection disconnected, and keeps local published snapshots until the owner separately deletes them. No Cloud-side snapshot, metrics or agent-file copy is stored in this milestone; future service data is governed by that service's notice. Reconnecting requires a new pairing code and new consent.

= How are click counters cleaned up? =

A daily `partneropen_connector_prune_clicks` cron prunes counters older than 90 days. Deactivation clears the event and uninstall deletes all plugin options, snapshots, counters, and plugin transients.

= Does the plugin call external services? =

The Connector makes zero outbound requests in this milestone. A signed reference client may connect to the site using an allowlisted Cloud base after the relevant consent is granted. Optional data behavior is enforced locally by its corresponding scope.

== Support ==

Support is handled through the repository issue tracker and the WordPress.org support forum after publication. Support does not include custom content, legal or affiliate-compliance advice, hosted partner editing, tenant management, email delivery, Cloud-side storage, multisite network support, or visitor-level analytics.

== Privacy ==

The Connector stores durable records in WordPress options on this site. Daily aggregate counters are pruned after 90 days and all plugin options, per-Space snapshots, counters, and plugin transients are deleted on uninstall. The external-service disclosures above and the canonical notices at https://partneropen.com/terms and https://partneropen.com/privacy describe the consent-gated data exchange. The owner can withdraw consent from PartnerOpen > Status at any time.

== Changelog ==

= 0.1.0 =
* Initial release of PartnerOpen Connector with local-first setup, explicit consent scopes, signed delegated publishing, strict snapshot validation, same-origin disclosed links, Global Pause, aggregate metrics, daily pruning, and consent withdrawal.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
