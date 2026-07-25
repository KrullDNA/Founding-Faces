=== Founding Faces ===
Contributors: KDNA
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Runs the entire private membership programme for Apotheca: applications,
moderation into The 35 or The Circle, member creation, formulation notes,
polls, an anonymous members map, and email-platform sync.

== Description ==

Founding Faces is a single, lean plugin that runs the Apotheca private
membership programme end to end. It is built in stages; each stage installs
and is testable on its own.

Governing principles:

1. Members are a number to the public, never to Nick.
2. Publication, not conversation. No feeds, chat or notifications.
3. Everything is tagged to member and date from the start.
4. Lean, always. Native WordPress over libraries; nothing loads where the
   plugin isn't running.

== Build progress ==

Stage 1 — Foundation & data layer:
* Plugin activates cleanly.
* Three custom tables created: ff_applications, ff_poll_votes, ff_interactions.
* Products (ff_product) and Notes (ff_note) post types registered.
* Group taxonomy (ff_group) registered on users, seeded with the two terms
  "The 35" and "The Circle".

Stage 2 — Application form & status lookup:
* Front-end application form via [ff_application_form] shortcode (works in
  Elementor too), storing submissions to ff_applications as pending with the
  consent flag and a timestamp.
* Four-digit Australian postcode field, validated server-side, for the map.
* Logged-out status lookup via [ff_status_lookup] — enter your email, see
  pending or decided, without exposing group or number.
* All input sanitised, all output escaped, every submission nonce-protected.

Stage 3 — Moderation & member creation:
* Admin "Founding Faces" menu with a moderation queue (pending count bubble),
  tabbed by status, showing each application's details.
* Approve into The 35 or The Circle; The 35 gets the next sequential Founding
  number from a monotonic sequence, so a withdrawn number is retired and never
  reused.
* Automatic WordPress user creation on approval, with the real name stored as
  private user meta and the public identity (number, or first name) as the
  display name. Application and member stay two linked records.
* Withdraw deactivates the account (never deletes) and retires the number;
  deactivated members can't log in.
* Resend-welcome-email button. Every action nonce- and capability-checked.
* Interaction-log spine helper (FF_Interactions) in use from approval onward.

Stage 4 — Welcome emails & account access:
* Group-specific welcome emails on approval, from editable templates on a new
  Settings page (placeholders for name, number, group, links, etc.).
* The 35 email states the assigned Founding number; The Circle email welcomes
  them to the Apotheca community.
* Secure set-password link — a one-time token (SHA-256 hashed, 7-day expiry).
  Members set their own password; a plain-text password is never emailed.
* Set-password and "resend my set-up link" screens live on the WordPress login
  page, so an expired link is never a dead end and no page needs creating.

Stage 5 — Email connector + Campaign Monitor:
* Abstract FF_Connector contract with a manager that holds the one active
  connector (only one at a time), plus the Campaign Monitor add-on.
* On approval a consented member is synced with name, email, group and number;
  group and number travel as custom fields (Campaign Monitor has no tags).
* Consent is enforced: nothing syncs unless the stored consent flag is true.
  Test accounts are never synced to the live list.
* API key and list ID on the Settings page; the Group and Number custom fields
  are created on the list automatically. Uses WordPress's HTTP API, no SDK.

Stage 6 — Products & notes with gating:
* Notes (ff_note) are structured records: linked product, date, trial number,
  development stage (in development / stability testing / passed / failed),
  image gallery and a per-note audience flag (everyone / the-35-only), all
  entered in a clean "Note details" metabox with a media-library gallery
  picker.
* Server-side gating (FF_Gating): can_view_note() plus member / The 35 / The
  Circle checks — the single source of truth for who sees what.
* Elementor "Show to" visibility condition on every element (Everyone /
  Logged-out / All members / The 35 / The Circle), enforced via should_render
  so gated content is never produced or sent to the browser.
* Notes and Products kept out of the REST API to close any path around the gate.

Stage 7 — Frontend display:
* A note is designed once and rendered automatically: every note (single or in
  a list) goes through one render_note_card() template, so publishing note 30
  is just filling in fields — it appears already styled and on-brand.
* Components as shortcodes (Elementor-compatible): [ff_note], [ff_notes]
  (newest first, filterable by stage with filter chips), [ff_product_header],
  and [ff_home] (a hybrid home: latest-notes feed above a products list).
* Product metabox adds a current stage and a "where it's up to" line.
* Apotheca brand tokens baked into the components; the note markup is
  filterable (ff_render_note) so Elementor Pro Theme Builder can override it.
* Every component runs the 35-only gate server-side: gated notes are filtered
  out before any markup exists, so unauthorised members never receive them.
* First views recorded to the interaction spine (note_viewed) for the later
  personal-history page.

Stage 8 — Poll widget:
* Polls as a non-public ff_poll type: question, two-or-more options each with an
  optional image, per-poll audience (everyone / the-35-only), open/closed
  status, an "active poll" flag, and an outcome/reasoning field.
* Elementor Atomic widget (Founding Faces Poll): pick a poll or the active one,
  with alignment, accent colour and spacing controls defaulting to Apotheca
  tokens. has_widget_inner_wrapper() correct, single wrapper div. Also a
  [ff_poll] shortcode.
* Results hidden until the member votes; then the aggregate is revealed. Closed
  polls show the aggregate plus Nick's reasoning.
* Votes stored per member in ff_poll_votes (one per member per poll) and logged
  to ff_interactions (vote_cast). Front end is aggregate-only.
* Admin "who voted for what" view on the poll screen: each option's count and
  the members (real name + number) who chose it. Never exposed on the frontend.

Stage 9 — Personal history page:
* [ff_history] shortcode: a logged-in member sees their number and group, the
  polls they voted in and how they voted, the notes they've read, and any
  feedback they've shared.
* Reads only the current member's own rows from ff_poll_votes and
  ff_interactions — the member id always comes from the session, never the
  request, so no other member's data is ever visible.
* This is the seed of the launch "fingerprint" — the same data, later made
  presentable and optionally public with consent.

Stage 10 (this release) — The members map:
* [ff_members_map] shortcode: an anonymous dot per member, placed from their
  postcode via a bundled Australian postcode-to-coordinates table (3,170
  postcodes) — no external API call.
* Leaflet (bundled locally, no CDN) with the pale grey Positron OpenStreetMap
  style as the base map, from a no-key provider. The tile URL is a single
  setting so it can be repointed later.
* Settings for per-tier dot colour and size (The 35 vs The Circle); dots are
  semi-transparent so dense areas glow, with a tiny stable jitter so shared
  postcodes spread into a soft cluster.
* Reads postcode only (mirrored to user meta on approval), never the postal
  address; no names, no labels, nothing clickable. Deactivated and test
  accounts are excluded. Only coordinates and tier reach the browser.

Bundled data attribution: Australian postcode coordinates derived from the
Matthew Proctor Australian postcodes dataset (matthewproctor.com), deduplicated
to one centroid per postcode.

== Changelog ==

= 1.0.0 =
* Stage 1: foundation and data layer.
* Stage 2: front-end application form and logged-out status lookup.
* Stage 3: admin moderation queue, member creation, numbering, resend.
* Stage 4: templated welcome emails, secure set-password token, login screens.
* Stage 5: email-connector interface and Campaign Monitor add-on (consent-gated).
* Stage 6: structured notes, per-note gating, Elementor visibility condition.
* Stage 7: designed-once note template, display components, hybrid home screen.
* Stage 8: interactive poll widget, aggregate results, admin who-voted view.
* Stage 9: personal history page reading only the member's own spine rows.
* Stage 10: anonymous members map (bundled postcodes, Leaflet, postcode-only).
