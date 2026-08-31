=== PartnerOpen Connector ===
Contributors: partneropenteam
Tags: wordpress, content, publishing, privacy
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first delegated WordPress Spaces without affiliate link blocks.

== Description ==

This directory-safe PartnerOpen Connector package stores delegated Space content locally in WordPress and accepts strictly validated signed snapshots. This artifact does not publish affiliate/link blocks and does not issue resolver redirects. It includes typed content rendering, consent controls, Global Pause, aggregate-only counters, public context files and explicit local cleanup.

The plugin does not make outbound requests in this milestone. The optional PartnerOpen Cloud connection is used by an external signed client to pair and publish to this site only after the owner grants consent. See the PartnerOpen Terms of Use at https://partneropen.com/terms and Privacy Notice at https://partneropen.com/privacy.

== Privacy ==

The plugin does not collect cookies, IP addresses, user agents, device fingerprints, unique visitor identifiers or visitor-level click events. Aggregate counters contain only date, placement identifier and count and are retained locally for 90 days.

== Installation ==

1. Upload the ZIP through Plugins > Add New > Upload Plugin.
2. Activate PartnerOpen Connector.
3. Choose a URL prefix, review the consent scopes and accept the Terms and Privacy Notice.
4. Use the signed reference client to publish a validated content snapshot.

== Uninstall ==

Uninstall deletes the plugin's connection, consent, Space, snapshot, counter and transient data. Deactivation clears the daily counter-pruning event.

== Support ==

Use the repository issue tracker and the WordPress.org support forum after publication. Hosted partner editing, tenant management, email delivery and Cloud-side storage are outside this package.

== Changelog ==

= 0.1.0 =
* Initial directory-safe release.

== Upgrade Notice ==

= 0.1.0 =
* Initial directory-safe release.
