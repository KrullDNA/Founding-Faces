=== Founding Faces — Campaign Monitor ===
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Campaign Monitor connector add-on for the Founding Faces membership plugin.

== Description ==

A separate, optional add-on. Install and activate it alongside the Founding
Faces core plugin to sync approved, consented members to a Campaign Monitor
list. On approval a member's name, email, group and (for The 35) number are
sent; group and number travel as custom fields, since Campaign Monitor has no
tags. Nothing is ever synced without stored consent.

Set the API key and list ID under Founding Faces → Settings → Email platform,
then choose "Campaign Monitor" as the active platform.

Only one connector is active at a time. This add-on does nothing on its own —
it needs the Founding Faces core plugin to be active.

== Changelog ==

= 1.0.0 =
* Campaign Monitor connector, split out of the core plugin as a separate add-on.
