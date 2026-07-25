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

Stage 3 (this release) — Moderation & member creation:
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

== Changelog ==

= 1.0.0 =
* Stage 1: foundation and data layer.
* Stage 2: front-end application form and logged-out status lookup.
* Stage 3: admin moderation queue, member creation, numbering, resend.
