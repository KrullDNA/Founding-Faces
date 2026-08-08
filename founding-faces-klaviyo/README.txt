=== Founding Faces, Klaviyo ===
Requires PHP: 7.4
Stable tag: 1.1.3
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

Only one connector is active at a time. This add-on does nothing on its own ,
it needs the Founding Faces core plugin to be active.

== Changelog ==

= 1.1.3 =
* feedback_count, last_feedback and notes_read are no longer sent. polls_voted
  and last_voted stay.

= 1.1.2 =
* The same engagement figures as Campaign Monitor gets: polls_voted,
  last_voted, feedback_count, last_feedback and notes_read.
* Klaviyo has no equivalent of Campaign Monitor's 250-character text field, so
  the tag list is never trimmed here.

= 1.1.1 =
* Reports unsubscribes back to the site. Klaviyo holds the answer on the
  profile rather than in a list of events, so the profiles touched since the
  last look are read and each one's marketing consent is checked. Anything that
  is not SUBSCRIBED counts: unsubscribed, suppressed and spam complaints all
  mean stop emailing this person.
* A profile Klaviyo has never been told either way about is left alone, since
  no answer is not the same as being told no.

= 1.1.0 =
* The same data as Campaign Monitor gets, in the shape Klaviyo wants: group,
  status, display_preference, postcode, application_date and number as profile
  properties, and tags as a genuine list property rather than a delimited
  string.
* Klaviyo's own tags label campaigns and flows, not people, so profile
  properties are the right tool and nothing is lost by not using them.
* The postcode is also written to Klaviyo's built-in location field, which is
  what its location segments read.
* Because both connectors serialise the same underlying array, moving from
  Campaign Monitor to Klaviyo is a re-sync from WordPress rather than a CSV
  export that would arrive as one lump of pipe-separated text.

= 1.0.0 =
* Klaviyo connector, shipped as a separate add-on plugin.
