=== Founding Faces — Klaviyo ===
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Klaviyo connector add-on for the Founding Faces membership plugin.

== Description ==

A separate, optional add-on for when Klaviyo is purchased. Install and activate
it alongside the Founding Faces core plugin to sync approved, consented members
to Klaviyo. On approval a member's name, email, group and (for The 35) number
are sent; the group is sent as BOTH a tag and a profile property. Nothing is
ever synced without stored consent.

Set the private API key (and optional list ID) under Founding Faces → Settings
→ Email platform, then choose "Klaviyo" as the active platform.

Only one connector is active at a time. This add-on does nothing on its own —
it needs the Founding Faces core plugin to be active.

== Changelog ==

= 1.0.0 =
* Klaviyo connector, shipped as a separate add-on plugin.
