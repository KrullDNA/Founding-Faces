=== Founding Faces ===
Contributors: KDNA
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.92
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
* Notes (ff_note) are structured records: linked product, date, version number,
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
  [ff_home] (a hybrid home: latest-notes feed above a products list), and
  [ff_note_gallery] (the note's images as a slider).
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

Stage 10 — The members map:
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

Stage 11 — Account settings:
* [ff_account] page: change email (with a confirmation link sent to the new
  address), change password (secure token reset, the same mechanism as the
  welcome link), and edit name.
* Email-consent toggle that writes back through the connector — turning it off
  unsubscribes at Campaign Monitor, not just locally; turning it on re-syncs.
* Self-service data export (CSV) and delete buttons, calling the shared privacy
  core (FF_Privacy): export gathers the whole record; delete removes the
  application and personal meta, unsubscribes, retires (never reuses) the
  number, and anonymises/deactivates the account.
* Number, group and standing are shown read-only — Nick's to control, never the
  member's to edit.

Stage 12 — Privacy & admin tools:
* Privacy & Tools admin page: per-member CSV export, delete-with-number-
  retention (personal data removed, number retired never reused), and a consent
  audit (who consented and when) in one members table.
* Test mode: create test members (they take real numbers to exercise the whole
  flow; consent off so they never sync to the email platform).
* Guarded reset: deletes ALL test accounts, zeroes the numbering sequence and
  clears the retired list — together — so the next real The 35 member is 01.
  Refuses to run if any real numbered member exists, and requires typing the
  word RESET, not a single click.

Stage 13 — Klaviyo add-on:
* Second connector implementing the same FF_Connector contract, for when
  Klaviyo is purchased; group sent as BOTH a tag and a profile property.

Stage 14 (this release) — Members map Elementor widget & add-on split:
* The members map is now a native Elementor widget (Founding Faces Map, Atomic
  architecture) wrapping the same renderer. The [ff_members_map] shortcode
  still works as a fallback — the widget doesn't replace it.
* Widget controls: centre point (defaults to the centre of Australia), default
  zoom, min/max zoom, scroll-wheel zoom (default off), pan/drag with an option
  to lock panning to Australia's bounds, zoom buttons on/off, map height; dot
  colour/size per tier, dot opacity, optional dot border; base tile source
  (defaults to the plugin Positron setting), container background/border/radius,
  and an optional legend with position. All map behaviour options are standard
  Leaflet options. Still reads postcode only, nothing clickable.
* Leaflet and the widget assets load only where a map is present.
* The connectors are now SEPARATE add-on plugins ("Founding Faces — Campaign
  Monitor" and "Founding Faces — Klaviyo"), each in its own zip. The core
  exposes an 'ff_register_connectors' hook they register through, so the core
  no longer depends on any connector being installed. Install only the platform
  you use; only one is active at a time.

== Changelog ==

= 1.0.92 =
* Fixed: password fields kept the browser's own black border and ignored every
  Field control in the Style tab, so the password box on the Login widget looked
  nothing like the email box directly above it. Only text, email and text areas
  were ever listed; password, date, telephone, URL and number are now listed too,
  in the stylesheet and in the Elementor controls alike.

= 1.0.91 =
* The Login widget's signed-in message takes an alignment and a margin, which it
  had neither of — only a colour and a typeface, which is not enough to place
  something on a page.
* The signed-in panel it sits in gained its own background, border, corner
  radius, shadow, padding and margin, so it can be a card in its own right
  rather than two lines of text where a form used to be.

= 1.0.90 =
* The Login widget takes a "Show in the editor as" choice: both states one above
  the other, a logged-out visitor (the form), or a signed-in member (the
  message). The canvas only — the front end always shows the right one for
  whoever is looking.
* Fixed at the same time: the editor's two-state preview never ran. The flag that
  asked for it was not among the shortcode's declared attributes, so it was
  dropped before it was read, and the canvas only ever showed the signed-in
  panel — which is exactly why the form could not be styled.
* The signed-in panel is now built in one place rather than twice, with the
  log-out link going nowhere in the editor.

= 1.0.89 =
* The note gallery now moves one way. Forward always slides left and back always
  slides right, at every point in the gallery — reaching the last image and
  pressing forward again carries on into the first instead of rewinding the whole
  strip to get there.
* Done by rotating the track rather than scrolling it, so the strip never has an
  end to rewind from. Nothing is cloned: the lightbox still holds the images the
  note actually has, and the browser is asked for each of them once.
* A dot still jumps straight to its image — rotating a step at a time would be a
  long walk across a gallery of any size.

= 1.0.88 =
* Fixed: the meta row's stylesheet fallback said one line while the widget's
  control said two, so the two-line layout only appeared once Elementor had
  regenerated its CSS. The fallback now matches the control's default.

= 1.0.87 =
* The meta row is now built as two groups — the pills, then the plain text — and
  a "Meta row layout" control puts them on one line or two. Two lines is the new
  default: the stage badge and vault chip together, the version and date beneath.
* Because they are groups rather than loose chips, a narrow card no longer wraps
  at whatever point it runs out of room; the pills stay with the pills.
* "Gap between chips" now spaces the chips within each line, and a new "Gap
  between the two lines" spaces the lines. Alignment applies to both.
* Set per device, so a card can be one line on desktop and two on a phone, or
  the other way round.

= 1.0.86 =
* The mark between the chips now goes between two pieces of plain text and
  nowhere else — so between the version and the date, but never beside the stage
  badge or the vault chip. A pill has an edge of its own, and a mark next to one
  is a second boundary drawn over the first.

= 1.0.85 =
* Every chip on a note now has its own Style section — Stage badge, Version
  number, Date, and The 35 vault chip — each with typography, text colour,
  background, border, corner radius, padding, box shadow and a vertical nudge.
  One typography setting across all four was the wrong shape: a pill, two pieces
  of quiet text and a small-caps marker are three jobs that only looked like one.
* "Badges & chips" now holds what belongs to the row rather than the chips: the
  gap, the alignment, the row's margin, and the separator's controls.
* Two settings change hands. Any typography set under the old shared control now
  applies to the stage badge alone, and the combined "Version & date colour" is
  gone — set the colour in the Version number and Date sections instead.

= 1.0.84 =
* A note's stage, version and date ran into one another. There is now a mark
  between them — "Between the chips" in the Content tab, a "|" by default, any
  wording or none.
* It has its own controls under Badges & chips: colour, typography, the space
  either side (on top of the chip gap, so it can sit tight to one and breathe
  from the other), and a nudge up or down for marks that don't sit on the same
  optical line as the text.
* The mark is hidden from screen readers — it is punctuation for the eye, and
  read out between every chip it would be noise.

= 1.0.83 =
* Cards in a multi-column layout are now equal height by default: every card in
  a row matches the tallest one, so a short note beside a long one no longer
  leaves the row looking half-finished.
* It is a control, not a decision: "Card heights" (or "Note heights" / "Poll
  heights") sits under the Columns control on every widget that has one — Notes,
  Notes Archive, Home, Member Archive and both poll layouts — with "As tall as
  their content" if that is wanted, set per device.

= 1.0.82 =
* Note cards can be shortened: "Body length" takes a number of words or of
  characters, so a long note no longer runs the length of the column. A
  shortened body is plain text — formatting is dropped rather than cut in half —
  and it can carry a link through to the whole note, with your own wording and
  its own style controls.
* Every part of a note card now has its own switch: title, stage badge, version
  number, date, vault chip, body copy and images. Turn one off and nothing is
  left behind — a card with no title and no chips has no header element holding
  space where they used to be.
* On the Notes, Notes Archive, Single Note and Home widgets, all four. The
  editor samples honour the same settings, so the shortened length can be judged
  where it is set rather than guessed at.
* Shortcode equivalents: body_trim, body_unit ("words" or "characters"),
  body_more, body_more_text, and hide="date,vault" for the parts.

= 1.0.81 =
* Fixed: the Edit Product and Edit Note screens showed no permalink row, so the
  URL was invisible and the slug could not be edited. The editor screen reads
  the post type's "public" flag directly to decide whether to offer it, and both
  types were registered non-public. Both now say what has been true since
  1.0.79: they have a front end.
* Nothing is loosened by that. Both are still excluded from site search, have no
  archive, are removed from the sitemap, are marked noindex, are kept out of the
  REST API, and redirect anyone who isn't a member before rendering.

= 1.0.80 =
* "The product on this page (automatic)" now resolves on a note's page too, to
  the product that note belongs to. On a Single Note template that makes a "more
  from this formulation" block possible without naming a product anywhere. On a
  Single Product template nothing changes.

= 1.0.79 =
* Products have two more stages: Formula finalised and Complete. They are on the
  product only — a note never reaches either, so they don't appear on the note
  editor or as a filter chip on a notes page.
* Products now have their own single URL (/formulation/…), so Elementor Theme
  Builder can preview and target them exactly as it can notes: Products appears
  in Preview Settings and in "Where do you want to display your Template?".
  Build the product page once and every product uses it.
* That page is gated like a note's: a logged-out visitor is sent to log in, a
  non-member is sent away, and products are marked noindex and kept out of the
  sitemap. The slug is "formulation", not "product", so nothing collides with
  WooCommerce.
* The Notes widget and the Product Header widget can now be set to "The product
  on this page (automatic)", which is what makes one template serve them all.
  Asked to follow the page's product where there isn't one, the notes list shows
  its empty state rather than quietly listing every note there is.
* Fixed: a note stayed marked unread after being read. The view was only
  recorded by the Single Note widget, so a Single Note template built from
  dynamic tags never recorded one. Opening the note's page now marks it read
  however that page is built. Designing the template in Elementor does not.
* Permalinks are flushed once on update, so the new product URL works without
  re-saving them by hand.

= 1.0.78 =
* A note's "Trial number" is now "Version number", everywhere it is written or
  read: the field on the note editor, the chip on every note card, the Elementor
  dynamic tag, and the widget's colour control for it.
* The stored meta key stays ff_note_trial. Renaming it would have detached the
  number from every note already published, and nothing outside the plugin reads
  it by name. Nothing needs re-entering.

= 1.0.77 =
* New widget: Founding Faces Note Gallery. The images already stored against a
  note, shown as a slider. Left on automatic it follows whichever note is being
  read, so it can go straight into a Single Note template once.
* One image is not a slider: no arrows, no dots, nothing to interact with. From
  two upwards it loops in both directions and never dead-ends, and the arrows
  take themselves away again on any screen where every image already fits.
* Full control of the arrows: any icon from the library (or the plain chevron),
  icon size, button size, padding, colour, background, border, corner radius and
  shadow, each with a hover state, plus how far in or out from the edge they sit
  and how far down. Nothing about how they look is decided by the plugin.
* Images on show and the gap between them are per device, so three across on
  desktop can be one on a phone. Also: image size, height and how it fills that
  height, corner radius, border, shadow, captions from the media library, dots,
  autoplay with a pause on hover, and slide speed.
* Clicking an image can open Elementor's lightbox, open the full file in a new
  tab, or do nothing.
* Autoplay never runs for a member whose device asks for reduced motion, and
  the slide transition is dropped for them too.
* The gallery obeys the 35-only gate wherever it is placed, not just on the
  note's own page.
* "Which note is this page about?" is now answered in one place, shared by the
  gallery and the feedback form.

= 1.0.76 =
* Notes are now flagged show_in_nav_menus, which is the flag Elementor reads
  directly to decide what its Theme Builder can preview and target. 1.0.75
  filtered Elementor's list, which was not enough — the conditions dialog reads
  the flag itself. Notes now appear both in Preview Settings and in "Where do
  you want to display your Template?" under Singular.
* The cost of that flag is its own meaning: a note is now an addable item in
  Appearance -> Menus. Linking one is a deliberate act, and menu items carry a
  Founding Faces visibility rule, so a note linked by mistake is still gated.

= 1.0.75 =
* Notes now appear in Elementor's Theme Builder preview picker and display
  conditions. Elementor builds that list from post types flagged
  show_in_nav_menus, and notes are deliberately not flagged that way — they are
  not pages anyone links to from a menu. The plugin now tells Elementor about
  them directly instead, which changes nothing about how WordPress treats the
  post type anywhere else.
* Set Preview Settings to Single -> Notes and pick a note; the Single template
  then previews against a real note.

= 1.0.74 =
* Every text field an administrator writes into now accepts HTML, decided in
  one place (class-ff-text.php) instead of field by field. Headings, labels,
  hints, buttons, badges and the map legend take inline markup — bold, italics,
  a link, a line break. Messages and intros take what a post takes.
* Covered: the member archive headings and header subheading, the note filter
  labels, every poll phrase (closed capsule, hint, "Your choice", "Where we
  landed", the vote count), every application-form label, hint, consent line
  and button, the status-lookup heading, intro, label, button and "send it
  again" link, the home widget headings, the "view all notes" link, the login
  form's labels, button and links, the member bar labels, the welcome widget's
  three phrases, and the map legend labels.
* Placeholders stay plain text: they live inside an HTML attribute, where a tag
  can only ever arrive as visible angle brackets.
* No field accepts scripts, styles or iframes. Text a MEMBER writes — feedback,
  messages, their own name — is untouched by this and stays escaped.
* Fixed: the Feedback widget did not attach the note it was placed on unless a
  note ID was typed in by hand, while the [ff_feedback] shortcode did. Both now
  resolve the note the same way, so a feedback form in a Single Note template
  tags every submission with its own note.
* The admin thread view now names and links the note a piece of feedback is
  about.

= 1.0.73 =
* Unread notes can now differ from read ones by more than a background: border,
  corner radius, padding, title colour, product-label colour and date colour,
  alongside the row background and bold title that were already there.
* The section is renamed "Unread notes (badge & row)" — the row controls were
  sitting inside a section called '"Unread" badge', which is not where anyone
  would look for them.
* Read rows keep whatever the "Item box" section gives them; every control here
  applies to unread rows only, so setting none leaves the two identical.

= 1.0.72 =
* Fixed: the map's zoom buttons, legend and attribution painted over a sticky
  header. Leaflet numbers its own furniture for a page where the map IS the
  page — panes at 400, controls at 800, corners at 1000 — and those numbers
  beat any normal header. The map now forms its own stacking context, so those
  numbers only mean anything inside it and nothing can climb out.
* The widget's own z-index (Elementor → Advanced) still decides where the map
  sits against the rest of the page, which is where that decision belongs.

= 1.0.71 =
* The no-poll message now accepts HTML — links, bold, italics, lists — and a
  blank line starts a new paragraph. It was being escaped, so tags showed as
  text on the page.
* The heading accepts inline HTML (bold, italics, a link, a line break) but not
  block tags, since a paragraph or a list inside a heading is not markup anyone
  means to write.
* Neither field accepts scripts or styles: this is copy, not a place to run
  code.

= 1.0.70 =
* New: a "When there is no poll" section on both poll widgets — a heading and a
  message shown between polls, where the widget used to render nothing at all.
  Both fields are pre-filled and both can be cleared to go back to showing
  nothing.
* New "No-poll message" style section: alignment, background, border, corner
  radius, shadow, padding and margin for the block, plus colour, typography and
  margin for the heading and the message separately.
* The editor preview can be switched between the poll and this message, so the
  copy can be styled without waiting for every poll to close.
* The polls archive widget uses the same copy in place of its built-in "No open
  poll right now" line.

= 1.0.69 =
* The winning-bar colour now takes priority when the member voted for the
  winning option, instead of the your-choice colour. The "Your choice" label
  still marks the row as theirs, so nothing is lost by it.
* Fixed: the stylesheet carried a rule for the member's own bar that painted
  the same colour the base fill already paints — visually nothing, but its
  specificity outranked the widget's winning-bar colour, so a member who voted
  for the winner never saw that colour whatever it was set to. Both states are
  now the widget's to colour.

= 1.0.68 =
* New "Show" control on the poll widget: the latest poll, all current polls, or
  one specific poll.
* "All current polls" lists open polls first, newest first, then closed polls
  while they are still inside their hide time — a decision is worth reading
  after the voting has finished, but only for as long as it was meant to be on
  the site. "The latest poll" is the first of that same order, so it means the
  newest poll still taking votes, falling back to the most recent decision.
* New Columns section for the "all" view: up to four columns set separately for
  desktop, tablet and mobile, with column and row gaps.
* The editor shows three sample polls in that view — one open, two closed — so
  the columns have something to lay out.
* Poll widgets placed before this update keep rendering exactly the poll they
  were set to.

= 1.0.67 =
* Fixed: per-item menu visibility was skipped entirely for administrators, so
  an item set to "Logged-out visitors only" still showed in the header of a
  logged-in administrator. The rules now apply to everyone on the front end,
  which is the only way the person setting them can see them working.
* The admin area still shows the unfiltered menu, and so does the Elementor
  editor, so a header can be designed with every item on the canvas.
* This matches the Elementor "Show to" control, which never had the exemption.

= 1.0.66 =
* "Notes per page" is now a responsive control: a different page size for
  desktop, tablet and mobile. The page is built at the desktop size and the
  script cuts it to the screen's own size before anything is painted, because
  one document is served to every screen — and behind LiteSpeed's page cache,
  the same document is served to every visitor, so the size cannot be decided
  on the server.
* A smaller screen drops the surplus rows with no extra request. The page
  numbers, the "Load more" button and its starting point all follow.
* Elementor's own breakpoints are read from the site's settings, so moving them
  moves these too.
* New: "Open notes in a new tab" on the notes list. It applies to rows loaded
  later as well as the first page, and carries rel="noopener".

= 1.0.65 =
* New "Notes layout" style section: the notes list can run in up to four
  columns, set separately for desktop, tablet and mobile, with its own column
  gap and row gap on each device.
* Only the list becomes a grid. The section heading, the filter bar, the "Load
  more" button and the page numbers are its siblings, so they keep the full
  width underneath however many columns the notes are in.
* New switch to stack each note's date under its title, for when the columns
  are too narrow to share a line.

= 1.0.64 =
* New: numbered pages on the member's notes list, as an alternative to the
  "Load more" button or alongside it. A new Paging control offers "Load more"
  button, Page numbers, or both — with both, a member can jump to roughly the
  right page and then keep loading from there.
* Long lists show a window of numbers rather than all of them: the first and
  last pages, the two either side of the current one, and a "…" where pages
  have been left out.
* The numbers are built on the server and returned with every request, so a
  filter that changes how many pages there are comes back with a pager that
  already matches. Jumping to a page loads only that page's rows.
* New "Page numbers" style section: alignment, typography, Normal/Hover/Current
  colours and borders, corner radius, padding, the gap between numbers, margin,
  and the colour of the "…".

= 1.0.63 =
* The Elementor editor now shows sample content by default, on the Member
  Archive widget, all five display widgets and the message centre. Samples used
  to appear only when the real render came back empty, so an account holding
  two real notes rendered two real notes — no full page, no "Load more" button,
  nothing to design against. The editor is where the design is made, so it
  shows the full case.
* New "Editor preview" control on each of those widgets, should the real
  content be wanted on the canvas instead.
* The front end is untouched by the setting: every member always sees their own
  real record.

= 1.0.62 =
* "You chose: …" in the votes list is now its own box, the way the Unread
  badge and the poll's closed capsule are. New controls under Item text:
  background, border, corner radius, box shadow, padding and margin, on top of
  the typography and colour already there.
* It hugs its own text rather than stretching the row, and carries no
  background until one is set, so nothing changes until it is styled.

= 1.0.61 =
* New: a "Field wording" section in the application form widget's Content tab.
  Every field gets three fields of its own — its label, its placeholder and the
  hint below it — covering all six questions and the consent checkbox. The
  required-field marker is editable too, and clearing it drops it everywhere.
* Every field is pre-filled with the wording it replaces, so the panel shows
  what is actually on the page. Clearing a hint or a placeholder removes it.
* Clearing a label hides it on screen but keeps it in the markup for screen
  readers, so a field styled down to a placeholder is still one a screen-reader
  user can identify.
* Name, email, skin concerns and "about your skin" gained placeholders, and
  every field gained an optional hint line; previously only postcode and
  Instagram had either.
* The existing Button text and Thank-you message controls keep their saved
  values, and the shortcode's button_label and success_message attributes still
  work as before.

= 1.0.60 =
* Removed the decorative left border from the poll outcome block. It was drawn
  in the stylesheet where nothing in the editor could reach it; the block now
  ships with no border and the widget carries Border, corner-radius and shadow
  controls so any edge treatment is a decision made in Elementor.
* Removed two other hardcoded treatments for the same reason: the white inner
  ring on the member's own result bar, which sat on top of the bar colour set
  in the editor, and the hover ring and accent border on the voting buttons,
  which overrode the button's own border and shadow. The Hover tab now carries
  border colour and box shadow, so hover is styled rather than assumed.
* New: a "Poll card" style section — background, border, corner radius, box
  shadow, padding and margin for the poll as a whole.
* New: full box controls for the "Your choice" label — background, border,
  radius, shadow, padding, margin and a baseline nudge — and for the result row
  the member voted for: background, border, radius and padding.
* Added the missing controls elsewhere in the poll: question alignment, margin
  and padding; border and shadow on the "Poll closed" capsule; border, shadow
  and alignment on the outcome block; box shadow on the voting buttons.
* New: a "Wording" section in the poll widget's Content tab. Every fixed phrase
  — "Poll closed", the hint under the options, "Your choice", "Where we landed"
  and the vote-count line (singular and plural) — is now a field, pre-filled
  with the wording it replaces. Clearing a field leaves that line off entirely.
* The wording travels with the poll, so the results shown straight after a
  member votes say the same things as the rest of the poll.

= 1.0.59 =
* The votes list now gives the poll question the full width of the row, and
  drops the date onto the "You chose" line beside the answer. Poll questions
  are sentences, and holding a column open for the date wrapped them early.
* The editor's sample votes use full questions rather than short labels, so
  the row can be styled against the length it will really carry.

= 1.0.58 =
* The editor now draws a full page of sample notes at the widget's own page
  size, instead of four token rows, so the batch size and the "Load more"
  button can be judged on the canvas before a single real note exists. The
  sample titles and products vary rather than repeating one row, and the
  unread ones sit at the top where the real ordering puts them.
* Sample pages are capped at 30 rows, so setting the page size to 100 doesn't
  bury the editor.
* The sample list's button and filters are inert on the canvas: clicking them
  no longer returns a loading error where there is nothing to load.

= 1.0.57 =
* New: each note in the member's notes list can name its product on a small
  line above the title, so a member can see what a note is about before they
  read what happened. Notes with no product attached skip the line rather than
  showing an empty one.
* The line is switchable on the widget and has its own style section — colour,
  typography, background, border, corner radius, padding and margin — and it
  shows in the editor sample so it can be styled before there is any real data.
* The products named in a batch of rows are loaded in one query, so the label
  costs nothing per row as the list grows.

= 1.0.56 =
* New: filters on the member's notes list, each switchable on the widget —
  read/unread, product, type (development stage), date period (last 30 days,
  3 months, 12 months) and sort (unread first, newest, oldest). Changing one
  refreshes the list in place over AJAX, without a page reload and without
  losing the filter bar.
* Product and stage filtering happens in the database rather than by fetching
  everything and sifting in PHP, and the date filter measures the note's own
  date, not when it happened to be read.
* Sorting by newest or oldest treats the list as one run ordered by the note's
  date; "unread first" keeps the two groups apart. Read rows show when the
  member opened them, unread rows show the note's own date.
* New: full style controls for the filter bar — label colour and typography,
  select background, text, border, radius and padding, plus gap and margin.
* Every filter value is validated server-side against the offered options, so a
  hand-edited request can't reach for anything the bar doesn't offer, and the
  group gate still runs on every note.

= 1.0.55 =
* New: the Member Archive notes list is paged, with a "Load more" button that
  fetches the next batch over AJAX without reloading the page. Set the batch
  size on the widget ("Notes per page", 10 by default; 0 shows everything at
  once). Each request returns only that next batch, so a member with hundreds of
  notes never waits on one long list, and the unread-first order holds across
  pages.
* New: full style controls for the "Load more" button — typography, normal and
  hover colours, border, radius, padding, alignment and margin — and it shows in
  the Elementor editor whatever the page size, so it can be styled without first
  creating enough notes to trigger it.
* The AJAX endpoint takes the member id from the session, never the request, and
  every note still passes the group gate, so it can only ever return the
  caller's own list.

= 1.0.54 =
* Change: the Member Archive's "Notes you've read" section is now simply
  "Notes". It lists every note the member is allowed to see — unread ones first,
  each with an "Unread" badge and a bolder title, then the read ones ordered by
  when they were opened. Previously it only listed notes already read, which is
  the one thing a member doesn't need help finding.
* Change: a note counts as read only when the member opens its own page, not
  when a card appears in a list. Marking on list render (added in 1.0.47)
  emptied the unread list the moment a member landed on the hub, since the feed
  there silently marked everything read before they had chosen to read anything.
  This also keeps the menu count bubble honest.
* New: style controls for the "Unread" badge on the Member Archive widget —
  background, text colour, typography, padding, radius and gap — plus an unread
  row background and a bold-the-unread-titles switch.

= 1.0.53 =
* New: a Founding Faces Nav Menu widget. It renders a WordPress menu from
  Appearance → Menus, with every per-item setting intact (group visibility, the
  login/logout swap, the count bubble), but as an Elementor widget — so the
  links and the count circle are both styled in the editor, live, instead of
  through a settings page. Full controls for the links (typography, normal /
  hover / active colours and backgrounds, underline, padding, radius), the
  layout (direction, alignment, gap), hover sub-menus, and the circle
  (background, number colour and typography, size, inner padding, radius,
  border, gap, vertical alignment and the raise-above-the-text nudge).
* Change: the global "Unread count bubble" settings are gone, replaced by the
  widget's own controls. The bubble keeps sensible built-in defaults wherever it
  appears, so a theme-rendered menu still looks right; style it by using the Nav
  Menu widget.

= 1.0.52 =
* New: baseline controls for the unread count bubble, so it can sit above the
  menu text rather than beside it. A "Raise above the text" nudge lifts it
  superscript style without moving the line it sits on, and a vertical-alignment
  choice (middle, top, superscript, top/bottom of the text, baseline) sets the
  starting point.
* New: inner padding for the bubble, so the number sits comfortably in the
  centre of the circle. Clearing the circle size lets the padding decide how big
  the circle is instead.
* Both are on the Settings page for nav-menu items, and as Elementor controls on
  the Member Bar widget.

= 1.0.51 =
* New: a message thread now shows which product and note it is about, under the
  subject. Feedback is always left on a note, and a note carries its product, so
  the chain note to product tells a member (and Nick) what the conversation
  concerns. A general question has no reference, so it shows no context line
  rather than a misleading one.
* New: the unread count bubble can be styled on the Settings page — background,
  number colour, circle size, number size, corner radius and the gap from the
  label. Nav menu items are rendered by the theme (or Elementor's Nav Menu
  widget), which has no Founding Faces controls of its own, so the bubble is
  styled globally instead. The Member Bar widget's own Elementor controls still
  override these wherever it is used.

= 1.0.50 =
* Fix: editor dummy content now covers every widget, and no longer depends on
  who is looking. The remaining widgets keyed off "is the viewer a member?",
  which is true for Nick — so a member with no messages, no history or no
  account activity yet saw a real empty state instead of the samples. They now
  fall back to samples whenever the real render is empty:
  * Messages — a sample conversation list with the "New message" badge.
  * Member Archive — sample votes, notes and feedback.
  * Account — the sample profile panel.
* New: editor samples for the widgets that previously had none at all.
  * Login — the form and the signed-in panel are both rendered, with a sample
    error notice. Previously a signed-in administrator only ever saw the
    "You're signed in" panel, so the form itself could never be styled.
  * Members Map — sample dots across the Australian capitals, so dot colour,
    size and opacity can be previewed before there are members.
  * Application form — the success notice shown above the form as a sample (it
    normally replaces the form, so it was impossible to style).
  * Feedback and Ask — the "message sent" notice shown as a sample.

= 1.0.49 =
* Fix: the editor dummy content added in 1.0.45 never appeared for an
  administrator. It was shown only when the viewer failed the members-area
  check, and an administrator always passes it — so the person designing the
  page was the one person who could never see the samples. The samples now key
  off the render itself: if the real output is empty, a gate notice or an
  empty state, the sample stands in.
* New: the Status Lookup widget now previews its result in the editor — the
  status notice, the "Didn't receive it?" prompt and the "Send it again" button
  all appear as samples, since they otherwise only exist after a real
  submission. The form stays visible alongside them, so everything can be
  styled in one pass.

= 1.0.48 =
* Fix: declining an application sent no email at all, while the status lookup
  told the applicant to check an inbox that had nothing in it. A declined
  applicant is now emailed, from a template editable on the Settings page
  alongside the two welcome emails. Clearing the body field declines silently.
* New: a "Send it again" button under the status lookup result. It re-sends to
  the address already on file — a fresh welcome email with a brand-new
  set-password link for an approved member (which also fixes an expired
  seven-day token), the decline email for a declined applicant, or the received
  confirmation for one still pending. The reply is identical in every case,
  including for an unknown address, so the lookup still never reveals the
  decision and can't be used to test whether someone applied. Rate-limited to
  one send per address every fifteen minutes.
* New: the Status Lookup widget gained a heading and body copy, each with full
  style controls (colour, typography, alignment, margin, padding), plus style
  controls for the "send it again" prompt and button.
* New: an optional "Hide the form after a successful lookup" switch on the
  Status Lookup widget, with a "check another email" link back. An unrecognised
  email always keeps the form up so it can be corrected.

= 1.0.47 =
* Change: the notes bubble now counts genuinely unread notes rather than notes
  published since the member's last login — a session where they read two of five
  leaves three on the bubble instead of clearing it. A note counts as read once
  it has been rendered to that member, and the gate still applies, so a Circle
  member only ever counts notes their group may see.
* Fix: reading notes in a list (the hub feed, a product's notes, the archive) now
  records the view. Previously only a single-note page did, so notes read in a
  list would never clear the count.
* New: Founding Faces Member Bar widget — a header strip of Messages / Notes /
  Polls links, each with its own count circle, plus an optional Log in / Log out
  link. Full style controls for the circle (background, number colour and
  typography, size, radius, border) and its position, either beside the label or
  floating on the top-right corner like a mini-cart.
* New: the count bubble shows a sample number while designing in the Elementor
  editor or the Customizer, so it is always visible to style even when nothing is
  actually unread.

= 1.0.46 =
* New: a dynamic Log in / Log out menu item. Set it per item in Appearance →
  Menus: it shows "Log in" (linking to your login page) when logged out and
  "Log out" when logged in, swapping automatically. "Log in only" and "Log out
  only" modes hide the item when it doesn't apply.
* New: an unread count bubble on any menu item — mini-cart style. Choose the
  source per item: unread private messages, new notes since the member's last
  visit, open polls they haven't voted in, or everything combined. The bubble is
  hidden entirely at zero and for logged-out visitors, and only ever counts the
  viewer's own messages and content their group may see.
* New: a Founding Faces Login widget (and [ff_login] shortcode) — a skin over
  WordPress's own login handler, with the shared form Style tab plus link and
  signed-in-panel controls. A member who is already signed in sees a short
  message and a log-out link instead of the form.
* New: Settings → login page URL, after-login and after-logout destinations, and
  the two menu labels. Leaving the after-login field empty uses the members hub
  page, which follows the site between staging and live.
* Fix: a failed login from a custom login page returns to that page with a clear
  message, rather than dumping the member on wp-login.php.

= 1.0.45 =
* Change: decorative underlines removed from the built-in design (the accent rule
  under the Home and Member Archive section headings, and the line under the
  product header). Every heading now has an "Underline" switch instead, with its
  own colour, thickness and gap, so it's there only when wanted.
* New: full Style tabs on the display widgets — Notes, Notes Archive, Single Note,
  Product Header and Home now control the card (background, border, radius,
  shadow, padding, margin), the title, the meta row, badges and chips, the body,
  the gallery, the filter bar/chips and the "View all" link, each with typography,
  colour, alignment, margin and padding.
* New: the Poll and Polls Archive widgets gained voting-button, outcome-block and
  section-heading style controls, plus a gap control for the archive grid.
* New: representative dummy content in the Elementor editor. Notes, note cards,
  product headers, the products list, filter bars and polls all preview with
  sample data in the real markup, so every element can be styled before any real
  content exists (or when the designer isn't a member).
* New: the Single Note widget and each section of the Home widget (latest notes,
  products) can now render through a JetEngine listing template instead of the
  default layout, falling back to the default if the listing is missing.
* Fix: every Founding Faces widget now fills its container's width. The
  components no longer impose their own max-width, so the Elementor container
  governs the width as it does for any other widget.

= 1.0.44 =
* Change: the poll "your choice" label is now stored as "Your choice" (capital
  Y), so "Normal" in the typography control shows a naturally capitalised label
  rather than all-lowercase.

= 1.0.43 =
* New: vote-count styling on both poll widgets — the "X votes" line under the
  results now has its own colour, typography, and top-spacing controls, in the
  "Question & labels" Style section.

= 1.0.42 =
* Fix: the poll "your choice" label now respects the typography control's
  text-transform. The label no longer forces uppercase by default (so "Default"
  shows natural case), and "Capitalize" now renders "Your Choice" correctly — the
  label is an inline-block, so it's read as its own word rather than running into
  the option name (which was why the "y" in "your" stayed lowercase).

= 1.0.41 =
* New: "Your choice" styling on both poll widgets — the your-choice bar colour
  (which takes priority when an option is both winning and the member's choice),
  plus the "your choice" label colour, typography, and a gap control between the
  answer and the label.

= 1.0.40 =
* New: full result-bar styling on both poll widgets (Poll and Polls Archive).
  Set the bar colour, the WINNING bar colour (the option with the most votes),
  the your-choice bar colour, the empty-track colour, plus bar height and corner
  radius. Question, option-label and percentage colours too.
* Change: the "poll closed" message is now a styleable capsule ("Poll closed")
  shown above the question, with its own alignment, colours, typography, padding
  and radius controls. The old line beneath the results is removed; your
  "Where we landed" outcome text still shows when set.

= 1.0.39 =
* New: schedule a poll's close and hide. Each poll now has optional "Auto-close
  at" and "Auto-hide at" date/times. At the close time the poll stops taking
  votes and shows the final results and your reasoning; at the hide time it
  disappears from the site entirely. Set the hide time a little after the close
  time to give members a window to see the final votes. If neither time is set,
  choosing "Closed" in the poll's Status makes it disappear straight away. Times
  are entered and shown in your site's timezone. (Note: with full-page caching
  such as LiteSpeed, a scheduled change may not appear until the cache refreshes
  for that page.)

= 1.0.38 =
* Fix: admin "view as a member" preview now works for page access. is_member()
  resolves the current user's id before checking their group, which meant the
  previewed group was skipped and an "All members" page wrongly redirected the
  previewing admin. group_of() now applies the preview whenever it resolves the
  current viewer, so The 35 / The Circle previews reach their allowed pages.

= 1.0.37 =
* New: admin "view as a member" preview. From your own profile (or the toolbar
  switcher), pick The 35 or The Circle and browse the site exactly as that group
  does — gated notes, polls, pages and menu items included, and blocked from the
  other tier's content just like a real member. It only affects you; switch back
  to "Administrator" any time. A "Viewing as…" indicator shows in the toolbar.
* New: the Polls Archive widget has a "Show" option — open poll then past polls
  (default), open only, or past only — so you can place two widgets (one open,
  one past) and style each area differently.

= 1.0.36 =
* New: "Founding Faces Polls Archive" widget for a polls page — shows any open
  poll first (votable, or results if the member has voted), then every past poll
  with its results and outcome. All gated. A Columns control lays them out in a
  grid.
* New: a Columns control on the Notes and Notes Archive widgets, so notes can be
  shown in a 1–4 column grid (responsive) alongside the product / type / date
  filters.

= 1.0.35 =
* New: members are redirected to your hub page when they log in. Set it under
  Settings -> Members access -> "Members hub / portal page" (the same page used
  for the message-reply link). Members headed to a specific gated page still land
  there; admins keep the dashboard.
* New: the Notes widget can now show a "View all" link (with your text and a
  chosen page) beneath the list — ideal for a "latest 5 notes" block on the hub
  that links to the full notes page. (It already had a "Maximum notes" number.)
* New: "Founding Faces Notes Archive" widget for the dedicated notes page — a
  filter bar (Product, Type/stage, Newest/Oldest) over every note the member may
  see. Filters live in the URL so a view can be bookmarked. Each filter can be
  toggled off.
* Note: the Poll widget already hides itself when there's no active poll, so it
  can sit on the hub and only appear when a poll is live.

= 1.0.34 =
* New: "Founding Faces Welcome" Elementor widget — a personalised greeting built
  from editable before / middle / after text around the member's own first name
  and Founding number (e.g. "Hi Sarah, Founding Member 4, welcome back."). Toggle
  the name and number on/off independently, choose the HTML tag, and style the
  whole line plus the name and number separately (full Style tab). Shows the real
  first name because the member only ever sees their own greeting; The Circle has
  no number, so that part simply doesn't show for them.

= 1.0.33 =
* New: the members table under Privacy & Tools now shows a "Last login" column
  with the date and time of each member's most recent sign-in (or "Never").
  Recording starts from this update, so existing members show "Never" until
  their next login.

= 1.0.32 =
* New: members-only content is now explicitly kept out of search engines. Every
  note and every access-restricted page/post is marked "noindex, nofollow" (both
  a robots meta tag and an X-Robots-Tag header), and restricted pages are dropped
  from the sitemap (notes already were). Combined with the existing redirects,
  member URLs won't be indexed or listed by Google. Tip: set your members portal
  pages' "Founding Faces Access" to All members / The 35 / The Circle so they get
  this treatment too.

= 1.0.31 =
* Fix (important): the "Founding Faces Visibility / Show to" control was calling
  Elementor's get_settings_for_display() inside the should_render filter, which
  fires for EVERY container, section and widget on the page. That prematurely
  finalised each element's display settings, so containers lost their applied
  width/flex values on the front end and collapsed to content width — a page-
  wide layout bug that only appeared with the plugin active (and not in the
  editor). It now reads the raw setting with get_settings(), so element widths
  render exactly as designed. This fixes forms/columns rendering too narrow.

= 1.0.30 =
* Privacy: deleting a member now also removes their private messages and every
  attachment file (from the protected directory, and any legacy public upload) —
  so no personal words or files are left behind. This runs only on a data
  delete (self-service "Delete my data" or the admin privacy tool); withdrawal
  still just deactivates and keeps the record.
* Privacy: a member's data export (CSV) now includes a "Private messages" block
  with their full conversation history, so an export is genuinely complete.

= 1.0.29 =
* New: a "Members portal page" setting (Founding Faces -> Settings -> Members
  access). Choose the page that hosts the Messages widget; the "Open my portal"
  button in reply emails points there. It's stored as a page (not a URL), so it
  resolves to the correct address on staging and live automatically.

= 1.0.28 =
* Security: message attachments are now private. New uploads are stored in a
  protected directory (uploads/founding-faces-private, with a deny .htaccess and
  no directory listing) instead of the public uploads folder, under a random
  name. Every attachment is served only through a gated endpoint that checks the
  viewer is the thread's own member or an administrator — direct URL access is
  denied. Notification-email attachment links require signing in.
  Note for NGINX hosts: .htaccess is ignored by NGINX, so also deny web access
  to /wp-content/uploads/founding-faces-private/ in your server config; the
  gated endpoint still enforces access either way, and file paths are random and
  never exposed.
* Database: adds attachment_path to ff_messages (schema 1.1.2), applied on
  update. Any attachment from 1.0.27 keeps working via its stored link.

= 1.0.27 =
* New: messages can carry an attachment — an image or PDF (JPG, PNG, GIF or PDF,
  up to 8 MB). The type is validated by real content, not just the file name.
  Attachments show inline in the conversation (image thumbnail or a file link),
  both in the member's portal and in Nick's admin reply view, and are linked in
  the notification emails. Nick can attach files to his replies too.
* New: the Member Archive widget gains a "Private messages (conversations)"
  section, so a member's own conversations with Nick can be shown on any page
  built from that widget, with its own style controls (badge, links, bubbles).
* Database: adds attachment columns to ff_messages (schema 1.1.1), applied
  automatically on update.

= 1.0.26 =
* New: a private member <-> admin concierge channel. Members can give feedback
  on a note or ask a private question; Nick reads and replies from a new
  "Messages" admin screen; the member is emailed the reply (branded) and sees a
  "New message" flag in their portal, where they can read and reply. Strictly
  private and one relationship deep -- always a member and Nick, never
  member-to-member, never public -- so it keeps the "publication, not
  conversation" principle. Adds three widgets/shortcodes: "Founding Faces
  Feedback" [ff_feedback], "Founding Faces Ask a Question" [ff_ask] and
  "Founding Faces Messages" [ff_messages] (place on the portal homepage).
  Feedback also fills the personal-history "Feedback you've shared" section.
* Database: adds the ff_messages table (schema 1.1.0). Created automatically on
  update -- no reactivation needed.

= 1.0.25 =
* New: display-name preference for The 35, on the account-settings page. A
  member can choose how they appear in the members portal across three tiers,
  defaulting to the most private: number only ("Founding Face 4"), first name
  and number ("Sarah, Founding Face 4"), or full name and number ("Sarah Chen,
  Founding Face 4"). It reuses the private real name already on file -- no new
  name is collected. The Circle does not get this control (their login is
  access-only, with no public display). Identity is resolved centrally through
  FF_Members::portal_display_name(), so one change updates the whole portal at
  once. It never affects the members map, which always stays anonymous.

= 1.0.24 =
* Fix: the plugin's Elementor widgets now fill their container on the FRONT END,
  not just in the editor. In optimized-markup (Atomic) mode the widget's inner
  wrapper is stripped, so inside a horizontal (row) flex container the widget
  shrank to its content width and looked half-width even at "Max width: 100%".
  The widget wrappers are now forced to width:100%, so the container governs the
  width as intended. Affects the Application Form, Status Lookup, Account and
  Member Archive widgets.

= 1.0.23 =
* Fix: the Application Form and Status Lookup widgets now fill their Elementor
  container instead of being capped at 560px, so the front end matches the
  editor. The widget's "Form box -> Max width" control still lets you cap it.
* New: a reassurance note under the Instagram field ("Optional, and only used
  privately to review your application -- it's never shown to other members or
  on the map"), so the optional handle never appears to conflict with the
  anonymity promise. The handle remains private: admin-only, never public.

= 1.0.22 =
* New: promote a Circle member into The 35. Approved-into-Circle rows now have
  a "Promote -> The 35" button that assigns their Founding number, moves them to
  The 35, and sends a "Congratulations, you're one of The 35" email (editable in
  Settings). You still choose 35 or Circle at approval as before.
* New: "New applications" setting. Turn on "Automatically accept new applicants
  into The Circle" once The 35 is chosen, and valid applications become Circle
  members instantly (welcome email sent) with no clicks. Leave it off during the
  selection window to review every application by hand.
* New: spam protection on the application form -- an invisible honeypot field
  plus a too-fast-submit timing trap. Bot submissions are silently dropped, so
  they never become members (important with auto-accept on). No configuration
  and no third-party service needed.
* New: branded HTML email design applied to every programme email (welcome,
  promotion, password reset, application received) -- logo, heading, brand
  colours and a real button. All editable under Settings -> Email design. The
  secure "Set your password" button is now added automatically.
* New: an "application received" email sent the moment someone applies (when
  applications are held for manual review), editable in Settings.

= 1.0.21 =
* New: every shortcode now has a matching Elementor widget. Added three new
  widgets with full, scoped Style tabs -- "Founding Faces Application Form" and
  "Founding Faces Status Lookup" (form box, labels, fields, hints, submit
  button with hover, and success/error messages), and "Founding Faces Account"
  (page, title, standing box, section blocks, secondary buttons, plus the shared
  form controls). The Account widget shows a styling sample in the Elementor
  editor. Shared form controls live in a single trait so the two form widgets
  stay consistent. The shortcodes still work unchanged.

= 1.0.20 =
* New: the Member Archive header's group pill ("The 35" / "The Circle") now has
  full styling controls -- border, corner radius, padding and margin -- on top
  of the existing typography, colour and background.

= 1.0.19 =
* Fix: removed the empty gap that appeared under the Member Archive header's
  background colour. The subheading paragraph's default bottom margin was
  collapsing through the header's bottom edge; it is now zeroed so the
  background ends flush with the content.

= 1.0.18 =
* New: per-menu-item visibility. Appearance -> Menus now has a "Founding Faces
  visibility" dropdown on each item (Everyone / Logged-out / All members / The
  35 / The Circle); items the viewer can't see are removed from the menu on the
  front end (a hidden parent hides its children). Admins see the full menu.

= 1.0.17 =
* Change: header has no bottom gap by default (removed the residual margin).
  A Content toggle "Line under header" (default Remove) turns the divider on;
  its thickness, colour and spacing controls appear only when it is on.

= 1.0.16 =
* Change: Style-tab controls now match the selected Section — pick "Header" and
  you only see header styles; pick a list section and you see its styles; "Full
  record" shows everything. Votes-only, feedback-only and link controls appear
  only when relevant.

= 1.0.15 =
* Change: the header subheading is now an editable text field (blank hides it),
  replacing the show/hide toggle; the matching style control is labelled
  "Subheading".

= 1.0.14 =
* New: "Line under header" controls (thickness, colour, spacing) so a divider
  can be added and styled in the widget. Off by default.

= 1.0.13 =
* Change: the date now sits as a small right-hand column at the bottom of each
  item, instead of on its own row (no more big gap under the left column).
* Change: removed the default line under the number & group header; added a
  "Show intro line under header" toggle (off by default).
* Change: feedback items redesigned — the product/where-it's-for is a linked
  heading with the date on the right, and the member's feedback text runs full
  width beneath (supports long text). New "Feedback text" style controls.
  (Feedback text is supplied via the ff_feedback_text filter by the feedback-
  capture feature, added later.)

= 1.0.12 =
* Change: the four activity widgets are replaced by ONE "Founding Faces Member
  Archive" widget with a Section selector (Full record / Header / Votes / Notes
  / Feedback) — drop it multiple times to build any layout.
* New: a full Style tab — typography, colour, background, padding, margin,
  border and radius for the header, section headings, section box, items, and
  item text (title / sub text / date), plus note-link colours.
* New: notes in "Notes you've read" now link to the note's own page (toggle).
* If you placed the old My Activity / My Votes / My Notes / My Feedback widgets,
  re-add them as the Member Archive widget.

= 1.0.11 =
* New: the member-activity widgets (My Activity / My Votes / My Notes / My
  Feedback) show on-brand sample data in the Elementor editor, so they can be
  styled before there are any members. The live front end still shows each
  member their own real data (or the login prompt).

= 1.0.10 =
* New: "Note — First image" dynamic tag (single image) for the Elementor Image
  widget. The existing "Note — Image gallery" tag is for gallery/carousel
  widgets; a single Image widget needs this single-image tag.

= 1.0.9 =
* New: member-activity Elementor widgets — "My Activity" (with section toggles
  and editable headings), plus "My Votes", "My Notes Read" and "My Feedback".
  Each reads only the signed-in member's own data; admins see a placeholder
  when building.
* New: "Member — My number" and "Member — My group" dynamic tags, for designing
  the activity header freely.
* New: notes now have a single URL (slug /note/), so Elementor Theme Builder
  can target them with a Single template and display conditions. The single
  view is gated server-side (wrong viewers are redirected before render), notes
  stay out of REST and the sitemap, and loop gating is unchanged. Rewrite rules
  are flushed once automatically.

= 1.0.8 =
* New: Elementor dynamic tags in a "Founding Faces" group — Note Stage, Trial
  number, Date, Audience, Product name, and Image gallery. Use them in an
  Elementor (Pro) Loop Item to design the note card visually: click a widget's
  dynamic (database) icon and pick the field. Reads the note being looped.

= 1.0.7 =
* New: JetEngine integration. Note meta is available to JetEngine Dynamic Field
  widgets via the "Post Meta" source (keys: ff_note_product, ff_note_date,
  ff_note_trial, ff_note_stage, ff_note_gallery, ff_note_audience), plus
  Founding Faces callbacks in the Dynamic Field Callback dropdown to render a
  stage label/badge, the audience label, the product name, and the image
  gallery. 35-only notes stay gated in JetEngine listings (see 1.0.4).

= 1.0.6 =
* Change (map): removed the "Leaflet" attribution prefix (not legally required).
  The OpenStreetMap / CARTO data attribution remains, as it is required.

= 1.0.5 =
* Fix (map): test members now appear on the map (they exist to exercise the
  whole flow, map included, and are cleared by the reset before launch). Only
  the external email sync still excludes test accounts.
* New: the Privacy & Tools members table shows each member's postcode, so it's
  clear where a member sits on the map.

= 1.0.4 =
* Fix (map): the tile URL setting no longer strips Leaflet's {z}/{x}/{y}
  placeholders when saved. esc_url_raw was removing the curly braces, which
  broke tile loading and left the map grey after any settings save. The map
  also falls back to the working default if a stored tile URL looks broken.
* New: query-level gating for notes. 35-only notes are now excluded at the
  query source for anyone who isn't in The 35, so a JetEngine Listing Grid,
  an Elementor Pro Loop, or any WP_Query over ff_note stays gated with no
  extra work. Admins and The 35 still see everything.

= 1.0.3 =
* New: native Elementor widgets for the note display — Founding Faces Notes
  (notes by product, stage filter), Single Note, Product Header, and Home. The
  [ff_note]/[ff_notes]/[ff_product_header]/[ff_home] shortcodes still work.
* Change: administrators can now preview members-area content (notes, home) on
  the front end and in the Elementor editor, so pages can be built.

= 1.0.2 =
* Fix: when there are no members with postcodes yet, the map now frames the
  whole of Australia instead of sitting zoomed in on the empty centre.

= 1.0.1 =
* Fix: the Polls admin menu now registers reliably (where polls are created).
* Fix: the members map initialises correctly inside the Elementor editor and
  when the widget is added live.
* Change: the anonymous members map now renders for everyone (it exposes no
  personal data); restrict it with the Elementor "Show to" control or page
  access if desired.
* New: page-level access control — a "Founding Faces Access" box on every Page
  and Post (Public / All members / The 35 / The Circle) that redirects
  unauthorised visitors, with a restricted-page redirect setting.

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
* Stage 11: account settings, consent write-back, self-service export/delete.
* Stage 12: privacy admin tools, consent audit, guarded test-mode reset.
* Stage 13: Klaviyo add-on (group as tag + property), consent-gated.
* Stage 14: members-map Elementor widget; connectors split into add-on plugins.
