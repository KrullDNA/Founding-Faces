=== Founding Faces, Campaign Monitor ===
Requires PHP: 7.4
Stable tag: 1.1.3
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

Only one connector is active at a time. This add-on does nothing on its own ,
it needs the Founding Faces core plugin to be active.

== Changelog ==

= 1.1.3 =
* Five engagement fields: PollsVoted, LastVoted, FeedbackCount, LastFeedback
  and NotesRead. Counts and dates never grow, so segmenting on how much
  somebody takes part no longer costs tag space.
* When tags still will not fit, poll tags are dropped first and oldest first,
  and anything typed by hand is kept until there is no other choice. It used to
  drop purely by age, which could lose a label somebody had set deliberately.
* The settings page says when tags have had to be dropped, rather than leaving
  it to be discovered by a segment that quietly matches nobody.

= 1.1.2 =
* The tag string is kept inside Campaign Monitor's 250-character text field
  limit by dropping the oldest tags rather than letting the platform truncate
  mid-tag, which would leave a half-written label matching nothing and quietly
  break a segment.

= 1.1.1 =
* Reports unsubscribes back to the site. Both the unsubscribed list and the
  spam complaints are read, because they are different acts with the same
  meaning for us, and the results are paged so a long first run is not
  truncated into leaving people quietly still subscribed.

= 1.1.0 =
* Seven custom fields on the list instead of two: Group, Number, Status,
  DisplayPreference, ApplicationDate, Postcode and Tags, with Number as a
  number and ApplicationDate as a date so both can be segmented properly.
* Every one is a plain field rather than a multi-select. A multi-select carries
  a fixed option list defined on the field, so a new option means another API
  call that can fail separately from the one that matters.
* Tags are written as one delimited string with each value wrapped in pipes,
  |poll-01|feedback-r2|, and segmented with contains |poll-01|. Without the
  pipes, "founding" would also match "founding-circle" and the segment would be
  quietly wrong.
* Existing installs create the new fields automatically on the next sync.

= 1.0.0 =
* Campaign Monitor connector, split out of the core plugin as a separate add-on.
