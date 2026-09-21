# CLAUDE.md — ناس / Nas

**Read this first, every session.** It is the project's memory: what this is, what is decided, what
is built, and what is next. Update it at the end of every working turn — it is the only thing that
survives a new session.

---

## What this is

An **offline-payment** event ticketing platform, born in As-Suwayda and no longer
bound to it. Visitors reserve a seat
online and **pay in person at the venue**; the owner verifies them at the door. There is no payment
gateway and there is not meant to be one.

Bilingual **Arabic (default) / English**, mobile-first. The product bet is that the digital ticket
feels good enough to be worth showing off — hence per-event colour extraction, motion, and a
realtime status flip.

---

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.4), PostgreSQL |
| Frontend | React 19 + Inertia v3, Tailwind v4, shadcn/ui (vendored, added via CLI) |
| Realtime | Laravel Reverb (Pusher protocol) + `@laravel/echo-react` |
| Motion | `motion` v13 (the package formerly called framer-motion) |
| Auth | Fortify — **registration deliberately disabled** |
| Tests | PHPUnit 12 against **real PostgreSQL**, not SQLite |
| Quality gate | `composer ci:check` = Pint + PHPStan + ESLint + Prettier + tsc + PHPUnit |

---

## Decisions (locked — do not relitigate without asking)

1. **The QR encodes an auth-gated verify URL, never a signed one-click URL.** The attendee's phone
   displays the same URL the owner scans. A signed link would let any holder mark themselves paid.
   Verification requires being signed in **and** owning the event, and every state change is a POST.
2. **Administering and owning are independent.** `users.is_super_admin` is a
   flag, not a role: an account can administer the platform, run a venue, both
   or neither. The flag and `requires_approval` are deliberately **absent from
   the model's fillable list** — nothing should be able to grant itself
   administrator access by smuggling a field into an unrelated request. Two
   guards exist because there is no way back in: nobody may demote themselves,
   and the last administrator may not be demoted at all.
3. **Publishing is what approval gates, not editing.** An owner with
   `requires_approval` drafts and edits freely; asking to publish parks the
   event in `EventStatus::PendingReview`, which the public scope does not
   match, so it is invisible by construction rather than by remembering to
   filter it. Editing an already-live event leaves it live — an owner fixing a
   typo must not pull their own event offline.
4. **An administrator never sees a venue's income.** `PlatformStats` carries
   scale, not money. The figures live on the owner dashboard, which an
   administrator reaches by impersonating, and that leaves a record of who
   looked.
5. **Door staff work a door and nothing else.** `users.door_staff_for` names
   the one venue an account may scan, verify, search and print a door sheet
   for. Everything that shapes a venue or shows what it took sits behind
   `EnsureManagesVenue`; the door does not. A GET redirects them to the
   scanner rather than 403ing, because somebody handed this account lands on
   the dashboard by habit.
6. **Public registration is closed, and an invitation is the only door.** There
   is no open sign-up. An admin mints a one-use, expiring invitation; only its
   SHA-256 hash is stored, so the link is knowable exactly once and a leaked
   database yields nothing. The account is created with the *invited* address,
   so a forwarded link cannot become a stranger's account, and the whole
   thing — user, venue, first location — happens in one transaction under a
   row lock, so a double submission cannot make two accounts.
7. **Pending reservations hold seats**, with auto-expiry after `hold_hours` (default 24).
8. **Seat safety is a row lock.** `AppointTicket` does `lockForUpdate()` on the *event* row. Removing
   it makes `OverbookingTest` fail — verified by deleting it and watching all 12 contenders win 5 seats.
9. **Arrivals are separate from bookings.** Someone who paid for 5 and brought 3 still paid for 5;
   revenue follows `seats_paid`, never `seats_arrived`.
10. **No-show ≠ cancelled ≠ expired.** Three distinct facts: not used, called off, timed out.
11. **Ticket pages are `noindex`** and excluded from the sitemap. Lighthouse scores that as an SEO
   failure; it is correct — the URL is a bearer token beside a name and phone number.
12. **SVG is never an accepted upload.** Script-carrying markup on our own origin.
13. **Impersonation is full-access**, so the audit log is the only record of who acted. Start/stop are
   both logged, nesting is refused, super admins cannot be impersonated, banner is always visible.
14. **One face, committed.** Cairo (variable, OFL) carries every word of UI in
    both scripts, body and headings; IBM Plex Mono carries booking references.
    The woff2 files live in `resources/fonts/` under version control with
    their OFL licences, are declared with `@font-face` in `app.css`, and are
    hashed into `/build` by Vite. Nothing is fetched from a font host at build
    or at runtime, ever. Cairo has Latin glyphs, so there is no second Latin
    face and a mixed line never switches font mid-sentence.
15. **Latin digits everywhere, Arabic month names kept.** Arabic has two
    numeral scripts and the app was using both — dates came out Arabic-Indic
    through `ar-SY` while prices, seat counts, phone numbers and reference
    codes came out Latin, so one line could carry both. Half the figures here
    cannot be anything else: a price sits beside a Latin currency code, a
    phone number is dialled as typed, a booking reference is read out
    character by character. `lib/format.ts` pins the rule with the
    `-u-nu-latn` extension; PHP already behaved this way, so this aligned the
    client to the server rather than the other way round.
16. **Notification wording rotates; the subject never does.** A paid ticket
    always says paid, but not in the same sentence twice running. Every kind
    is a list of variants in `lang/{ar,en}/push.php`, `NotificationCopy` picks
    one minus whatever that recipient heard last. The exclusion is cached, not
    stored: forgetting it costs nothing and it must not become a column that
    needs migrating for every new message.
17. **A sold-out event is not an ending.** Holds lapse and people cancel, so
    seats come back constantly. `event_watchers` holds the queue, and it is
    worked in join order and capped at the number of seats actually free —
    telling forty people about one returned seat is a race thirty-nine lose.
    People are stamped as told whether or not a device was reachable, because
    the phone number is the real deliverable: with no mailer configured, the
    owner reaching them on WhatsApp from the event report is the fallback.
18. **A holder may release their own seats, quietly.** The control is behind an
    overflow menu on the ticket, never a button: the loudest thing on a ticket
    must not be what destroys it. It is a POST, and the status log carries
    `released by holder` with a null actor, which is what distinguishes it from
    a venue cancelling somebody.

19. **An event is listed until it is over, not until booking closes.**
    Booking closes at or before the start, so "only bookable events" hid every
    event on the day it happened and the home page read "nothing is on" at
    the busiest moment. `Event::listable()` keeps published events until
    `ends_at`, or `LINGER_HOURS` after the start when there is no end time;
    the card and the event page say **أُغلق الحجز** instead of pretending.
    Past events live only on the venue's own page — with one exception:
    when nothing at all is on and no filter is set, the home page shows the
    last six ended events as a dimmed record marked **انتهت** (`recent`
    prop), because a bare page with real events behind it reads as a dead
    platform.
20. **The app runs in `Asia/Damascus`.** Owners type event times as they read
    them on a poster, into a `datetime-local` field, and whatever timezone
    the app runs in is what those values mean. On UTC a 19:00 event was shown
    to everyone in Syria as 22:00. `APP_TIMEZONE` defaults to Damascus and
    `TimezoneTest` asserts a typed time comes back as the same wall-clock
    time.
21. **A null capacity is "as many as turn up".** `events.total_quantity`
    is nullable; null means no seat count, no sold-out state, no waiting
    list and no fill rate. `seatsRemaining()` returns null and every
    consumer decides what that means for it, rather than a huge number
    that would render. Zero was never a valid capacity, so null is
    unambiguous.
22. **Unlisted is a listing rule, not a status.** `events.is_unlisted`
    keeps an event published and bookable at its own URL and off every
    listing: `Event::listed()` is folded into `listable()` and `ended()`,
    the venue page adds it, the sitemap goes through `listable()`, and the
    page sends `noindex`. A new listing that forgets the scope is the only
    way to leak one.
23. **A free event may confirm on booking.** `events.auto_confirm` only
    takes effect while the price is zero (`autoConfirms()`); the ticket is
    created Paid with no hold and `verified_at` null, and the door's first
    scan is a check-in rather than a no-op — `VerifyTicket` treats
    Paid-with-no-verification as unseen.
24. **Deleting an event people hold tickets for archives it instead.** A
    ticket is a record on somebody's phone and a line in a report.
    `EventController::destroy` counts paid and still-held bookings; with
    any, the event goes to Archived, otherwise it is deleted with its cover
    and media files. The edit page words the control from the same count,
    so the owner knows which will happen before confirming.
25. **The Partner Terms are versioned, immutable once published, and
    accepted per venue.** `agreement_versions` holds drafts an admin edits
    and publishes; from the moment of publishing the model itself throws on
    any change to the text (`AgreementVersion::IMMUTABLE`), and publishing
    a newer version retires the old one. `agreement_acceptances` is one row
    per venue per version: a snapshot of the legal identity, the signer,
    IP, user agent, locale, a SHA-256 of the exact text shown, and how the
    phone was verified. Rows are never updated and the foreign keys
    restrict rather than cascade, because the row is the evidence. **No
    acceptance is ever backfilled**: the migration inserts version 1.0 as a
    *draft placeholder* only, so nothing is enforced until an administrator
    writes the real text and publishes it — at which point every existing
    owner is asked, as the owner decided.
26. **The gate blocks shaping a venue, not working its door.**
    `EnsureAgreementAccepted` sits beside `EnsureManagesVenue` on the
    dashboard and every venue-shaping route and redirects to
    `/owner/agreement` (remembering where they were going). Scanning,
    search and the door sheet stay open — paperwork must not stop somebody
    scanning tickets on the night. Door staff, administrators without a
    venue, buyers and an impersonating administrator pass through; an admin
    cannot accept on somebody else's behalf, and blocking them would only
    hide what they came to see. The checkbox is a real unticked form
    control and the server rule is `accepted`, so absent, 0 and "false"
    all fail; the form carries the version id it displayed and a stale one
    is refused.
27. **The one-time code is built and switched off.** `OtpChallenge` issues
    a six-digit code, stores only its hash in the cache for ten minutes,
    allows five wrong guesses, and consumes it on success. The sender is an
    interface with one method; `OTP_DRIVER` is empty until Syriatel or MTN
    credentials exist (`log` writes codes to the log for testing). While
    there is no driver the step is skipped and the acceptance says
    `otp_channel = none` — the record tells the truth rather than pretending
    a phone was verified.
28. **A commercial offer is a document the venue acknowledges, not a
    calculation.** `commercial_offers` is one venue's arrangement — fee
    type and value, who pays it, settlement days, subscription, what is
    included, validity — written by an admin, sent, and accepted or
    declined by the venue. Nothing invoices or deducts: there is no payment
    gateway and decision 4 stands. The terms freeze when sent (model guard
    on `TERMS`), the acceptance lives on the row (signer snapshot, method,
    IP, hash), and accepting a newer offer supersedes the old one. Sending
    a new offer withdraws one still waiting, so a venue never has two open.
29. **A paid event freezes its terms when it leaves draft.**
    `event_commercial_snapshots` copies the accepted offer's figures onto
    the event the moment the owner publishes (or submits for review), and
    the model refuses every update afterwards. The owner ticks
    `commercial_ack` on the form — checked *before* the event is saved, so
    a refused submission leaves nothing behind — and the submit button
    reads "confirm and publish". Free events, drafts and venues without an
    offer are asked nothing. A later offer never touches an earlier event.
30. **Service orders are the same shape, one level down.** `service_orders`
    is one paid service (door staff, promotion, photography…) for a venue,
    optionally tied to one of its events; drafted and sent by an admin,
    confirmed by the venue with the same acceptance snapshot, then worked
    through `in_progress` → `completed` or `cancelled` by the admin. Terms
    freeze on send; only the status moves after that.
31. **An organiser is a place with no room; a venue may lend its rooms.**
    `places.kind` is `venue` or `organiser`; `places.shares_locations` is a
    venue owner's blanket permission for other accounts to pick its
    locations. The event stays the organiser's — their terms, their offer,
    their door — only `location_id` points into another venue.
    `Place::usableLocations()` is the one list (own rooms first, then
    shared), `EventRequest` validates against the same rule, and only an
    organiser is forced to choose (a venue that has not added its first
    room yet may still draft). Public pages name the host venue first and
    the organiser after it; the venue owner sees what is booked into each
    room on the locations page, read only. The invitation form has "I
    organise events and do not own a venue", which skips the first
    location and creates an organiser.
32. **An administrator without a venue sees the platform only.** The owner
    section of the sidebar is hidden for them (`auth.has_place` is shared
    for this); one who also runs a venue keeps both. Approvals and the
    agreement gate never applied to administrators.
33. **Saving saves; the dialog publishes.** The event form carries no
    status control any more — owners did not understand "draft / published
    / archived" as a select. A new event is a draft; `saved_event` opens a
    dialog on the list with a summary (when, where, price, seats,
    visibility, the commercial terms) and the choices for that state:
    publish now (or send for review), keep editing, discard; for a live
    event, view, keep editing, unpublish. `POST events/{event}/publish` and
    `unpublish` are the two transitions; the commercial acknowledgement
    moved into the dialog, where the terms are. `status` is still accepted
    on store/update when sent (tests and any API client rely on it) but the
    form never sends it. Repeating may publish the copies at once, behind
    an off-by-default switch with a red warning; approval-tier copies land
    in review, and paid copies under an offer need the acknowledgement
    before a single one is made.
---

## Repository map

| Path | What |
|---|---|
| `app/Actions/` | `AppointTicket` (seat locking), `VerifyTicket` (check-in, no-show, cancel, holder release), `RepeatEvent`, `NotifyWatchers` |
| `app/Services/` | `CoverProcessor`, `MediaLibrary`, `EventReport`, `Settings`, `PlatformStats`, `PushSender`, `Agreements` (terms in force, accept, publish), `Commercial` (offers, orders, event snapshot), `Otp/` (challenge + senders) |
| `app/Support/` | `Color` (WCAG maths), `QrCode`, `NotificationCopy` (variant rotation), `PosterPrompt`, presenters |
| `resources/js/pages/public/` | place, event, ticket, my-tickets, invitation, for-venues — no app chrome |
| `resources/js/pages/owner/` | dashboard, events, place, scan, search, verify, door-sheet, report, agreement (bare page, in front of the app), documents, commercial-offer, service-order |
| `resources/js/components/map/` | `map-canvas` (shared Leaflet), `map-picker` (owner) |
| `resources/js/pages/admin/` | owners, settings, agreements (versions, publish, acceptances, who is outstanding), commercial-offers, service-orders, acceptances (the audit log) |
| `resources/js/components/commercial/` | `offer-summary` (terms table, status badge, signature record), `order-summary`, `offer-fields`, `order-fields` |
| `lang/{ar,en}/ui.php` | the client string catalogue, shared via Inertia as dot-notation |

---

## Conventions that are load-bearing

- **Every figure goes through `lib/format.ts`.** `dateTag(locale)` for dates,
  `formatNumber`/`formatMoney` for figures. A bare `toLocaleString()` follows
  the *device* locale, so the same page renders Arabic-Indic digits on an
  Arabic phone and Latin ones on the laptop it was built on.
- **Logical CSS properties only** (`ms-`, `pe-`, `start-`, `text-start`). Physical ones do not mirror
  in Arabic. There is a grep in the verification sweep for this.
- **Inputs holding a fixed language declare their own `dir`.** An English field inside the Arabic
  dashboard renders punctuation on the wrong side otherwise.
- **Breadcrumb and nav titles are translation keys**, resolved at render, because they are declared
  in static page config outside any component where hooks cannot run.
- **Motion animates transform and opacity only**, and never starts at `opacity: 0` for content that
  must be readable — if motion does not run, the content must still be visible. `StaggerItem`
  therefore uses `rise`, not `fadeRise`.
- **Every control is ≥44px on coarse pointers** (`@media (pointer: coarse)` in `app.css`).
- **Flash messages are `{ type, message }`** under `flash.toast`. A bare string renders nothing.
  The shared prop is **rebuilt** by `HandleInertiaRequests::flash()` from the `success`, `error`,
  `warning` and `info` session keys — so `->with('flash', [...])` is silently discarded, and
  anything else a controller wants to pass through has to be named there explicitly.
- **Error pages are styled inline**, not through Vite. They must render when the asset manifest is
  missing or a deploy is half-finished — which is exactly when they are needed.
- **`data-[side=*]` animation classes stay physical.** They follow Radix's computed placement, not
  the document direction; only layout classes get logical equivalents.

---

## Status — all phases complete

| Phase | Contents |
|---|---|
| 1 | Schema, roles/policies, event CRUD + cover pipeline, appoint flow, ticket page, verification, expiry |
| 2 | Reverb realtime, paid-stamp motion, event palette → Tailwind tokens, edge branding, QR scanner, i18n, ticket retrieval |
| 3 | Super admin, PWA + service worker, FCM scaffolding (inert), performance pass |
| 4 | Partial check-in, no-show status, printable door sheet, cross-event search |
| 5 | Media library (photos + 100 MB video), promo video, ticket inclusions, owner reports + CSV |
| 6 | Rebrand + logo/icons, platform settings, audited impersonation, unified indigo/orange theme |
| 7 | Sitemap, `DEPLOYMENT.md`, this file |
| 8 | Redesign pass: branded error pages, legal pages, public footer, skip link, social meta, press feedback |
| 9 | Artboard match: owner dashboard, events, admin owners, auth — composition block-by-block at 1280 / 1100 / 375 |
| 10 | Venue location (Leaflet/OSM pin + address + landmark), app-wide back navigation, i18n and a11y sweep |
| 11 | Rotating notification copy, hold reminders, waiting list, holder self-release, repeatable events, home listing filters, collapsible event form, first-run checklist, one numeral rule |
| 12 | Whole-app accessibility and i18n sweep: an `h1` on every screen, tap-target floors that actually apply, Arabic-Indic digits out of the catalogue, the last untranslated strings |
| 13 | Rebrand to ناس / Nas: wordmark + disc, cream/ink/orange tokens with a derived dark theme, Cairo committed and self-hosted, region-neutral copy, opt-in heritage mood in the poster prompt |
| 14 | Owner feedback: cover removal, unlimited capacity, confirm-on-booking for free events, unlisted events, repeat from the form, saved-event dialog, required-field marks, delete-or-archive |
| 15 | Partner Terms: versioned immutable agreement, per-venue acceptance records with legal identity and text hash, blocking re-acceptance gate, admin drafting/publishing, OTP layer with a null driver |
| 16 | Commercial layer: per-venue offers, event terms snapshot with publish acknowledgement, service orders, the owner "Agreements & documents" page, the admin acceptance log |
| 17 | `/for-venues`: the page for a venue that is not a partner yet — an admin-editable pitch and a WhatsApp button to the support number; linked from the footer and the login page; registration stays closed |
| 18 | Organisers and shared rooms: place kind, a venue's "let others hold events here" switch, shared locations in the event form, host venue on public pages, hosted list for the venue owner; admins without a venue see the platform only; قاعة → مساحة |
| 19 | The save dialog: no status select, summary plus publish / keep editing / discard / unpublish, acknowledgement in the dialog, repeat-and-publish with a warning |
| 20 | Motion from React Bits: blur headings, spotlight cards, magnetic primary buttons, sparks on decisive buttons, hold-to-confirm on destructive ones |

**487 tests**, PHPStan clean, Lighthouse mobile 100 on accessibility / SEO /
agentic browsing. Best practices scores 96 **against the dev server only** —
the sole deduction is a cookie warning on a `localhost:5173` request for
Leaflet's stylesheet, which does not exist once Vite has built. Audit a
production build before treating that number as a regression.

---

## Design system — ناس

From the designer's handoff (`nas_dev_handoff`). Cream paper `#F6F1EA`, ink
text `#0D0E0F`, and orange `#F66002` for the one thing on a screen that asks
to be pressed. Teal `#02AE9F` is a rare accent and never text. Borders
`#D0CCC4`, muted text `#6F6A64`. Three radii — 10px on anything pressed or
typed into, 14px on anything holding content, full round only on status
pills — one permitted shadow (`shadow-raised`), and **no gradients**. Cards
are white on cream with a 1px border. Buttons are weight 800.

- **Ink on orange, not white.** The handoff's primary button is white on
  orange, which measures 3.19:1 and fails AA for text; ink on orange is
  6.07:1. `--primary-foreground` is ink in both themes and the designer has
  been told why. Do not "fix" it back.
- **Two oranges.** `#F66002` is a fill: buttons, active states, the accent
  rule, icons, the focus ring. As *text* on cream it is 2.83:1 and fails even
  the 3:1 floor, so links and key figures use `--brand-orange-text`
  (`#B84600`, 4.77:1 on cream) via the `text-primary-text` utility. In dark
  mode orange on ink is 6.07:1 and the two tokens are the same colour.
- **Dark mode is derived, not designed.** The handoff specifies light only.
  Ink `#0D0E0F` page, `#161718` cards, cream text, muted `#A39D95`, orange
  unchanged. Every pair was measured; the five status pills sit between
  7.3:1 and 8.4:1. If the designer supplies a dark spec, replace the `.dark`
  block in `app.css` and nothing else.
- **Status colours are tokens** (`--status-{draft,published,pending,paid,danger}-{bg,fg}`),
  taken from the handoff's `nas-theme.css` for light and derived for dark.
  `StatusBadge`, `StatusBanner` and the report tiles all read them; a pill on
  a ticket and a pill in a report are the same pill.
- **Status is a dot plus text, never colour alone.** The door is badly lit and
  some readers are colourblind.
- **The brand ramp lives on `:root`, not `@theme`.** Tailwind prunes unused
  `@theme` values, so `var()` lookups from inline styles would resolve to
  nothing. Token names are colour-neutral (`--brand-orange`, `--brand-ink`),
  not brand-word-bound, and `BrandTest` fails on any surviving `--brand-jade`.
- **The only gradient left is a photo scrim** (`public/event.tsx`, dark over
  the cover so white text reads). It is allow-listed by name in `BrandTest`.
- **Migrations must not reference app enums.** Deleting `ThemeMode` broke every
  test at the migration step; column defaults are literals now.
- Primary controls are 52px on coarse pointers, focus is a 2px orange ring.

## The mark — the wordmark

The logo is the word **ناس** set in Lifta Black and outlined to paths by the
designer. The font is neither shipped nor licensed here and is not needed:
the paths render everywhere. **Never type ناس in a font and call it the
logo.** The orange disc — the same paths on an orange circle — is secondary,
for the places a wordmark cannot go.

- **`resources/brand/nas-wordmark.svg` and `nas-disc.svg` are the source of
  truth.** The wordmark was normalised once from the handoff's ElementTree
  export (namespace-prefixed tags, hardcoded fill, offset viewBox) into plain
  SVG with `fill="currentColor"`; the path data is byte-identical to the
  handoff. `components/brand/wordmark-path.ts`, `disc-path.ts` and the inline
  copy in `errors/layout.blade.php` are generated from it, and `BrandTest`
  asserts all four carry the same `d`.
- **The wordmark never mirrors.** It is Arabic text. The old mark flipped in
  RTL via an inline transform on the `<svg>` root; `Wordmark` has no direction
  logic at all, and its entrance is a single `brand-rise` beat.
- **Where each mark goes.** Wordmark: sidebar header, public header, auth
  pages, footer, error pages, the ticket's seal corner as a disc. Disc only:
  favicon, PWA icons, apple-touch, the collapsed sidebar rail, the push badge.
- **`npm run icons`** rasterises the disc into `favicon.svg`/`.ico`,
  `icons/icon-{192,512}.png`, and composes the maskable icon and
  `apple-touch-icon.png` (wordmark on full-bleed orange in the safe zone), the
  monochrome `icons/badge-96.png` (Android draws badges from alpha alone) and
  the share card `og-default.png` (1200×630). Never edit `public/icons/` by
  hand. Anything under `/icons/` is cached forever by the service worker, so
  **bump `VERSION` in `public/sw.js` in the same commit** — it is `v3` now.

## The poster workshop

An owner describes the event, gets a prompt for whatever image tool they use,
brings the artwork back, and the real details go on here.

- **The prompt asks for artwork with no lettering at all.** Image models cannot
  draw a scannable QR code, and render text poorly — Arabic worst, where they
  produce disconnected letterforms that read as nonsense to anyone literate.
  The event's details still go in the prompt, as *context* for the imagery, with
  an explicit instruction not to write any of it.
- **The lower third is reserved by the prompt** and scrimmed by the compositor
  anyway, because a model does not always obey and the code has to read against
  whatever turns up.
- **Compositing happens in the browser, on a canvas.** Not a preference: this
  machine's ImageMagick lists a PANGO delegate that does not work, RSVG is
  absent, and `caption:`/`label:` both fail — every server-side text path would
  render Arabic broken. A canvas shapes it natively, needs nothing installed,
  and shows the owner the poster before they commit. The artwork never leaves
  their machine.
- **Verified by decoding.** The finished canvas is read back with
  `BarcodeDetector` and must yield the event URL — looking right is not the
  same as scanning.
- **Models invent QR codes unless told not to.** A real owner test came back
  with two drawn ones sitting in the reserved band, so the prompt now forbids
  QR codes, barcodes, data matrices and small checkerboard grids by name.
- **The reserved band comes back light as often as dark.** The compositor
  samples its luminance and flips the scrim, the text and the code plate
  accordingly; assuming a dark band puts dark text on a cream screenprint.

## Motion from React Bits

Five components vendored from React Bits (MIT) through the shadcn CLI,
under `resources/js/components/bits/` in kebab case, ignored by ESLint like
`components/ui`. Each is wrapped once in `components/motion/` so the house
rules are applied in one place and every page uses the wrapper, never the
vendored file:

- **`BlurHeading`** (BlurText): the home tagline and the partners page
  heading. Words settle in from a blur, starting at half opacity so the
  text is readable if motion never runs. Splits by words, never letters —
  letters would break Arabic joins. Plain text under reduced motion. The
  vendored root was changed from `<p>` to `<span>` so it can sit inside an
  `h1`.
- **`SpotlightCard`**: the public event card. An orange light at 0.14
  alpha follows the pointer. It is a `radial-gradient`, allowed because
  it is a pointer effect, not brand decoration; `BrandTest` only forbids
  `linear-gradient` and the Tailwind gradient utilities. The vendored
  default classes (dark card, `rounded-3xl`) were removed so ours apply.
- **`Magnet`**: the book-now and WhatsApp buttons lean toward the cursor.
  Disabled on coarse pointers (`useFinePointer`, a `matchMedia` hook that
  is false on the server) and under reduced motion. The wrapper's inline
  `display` is overridden by passing `style`, which the component spreads
  last.
- **`Spark`** (ClickSpark): an orange burst on the decisive buttons —
  accept the terms, publish, accept an offer. Children alone under
  reduced motion.
- **`HoldSubmit`** (HoldButton): hold to confirm on destructive actions —
  delete or archive an event, discard a draft, decline an offer. A
  completed hold submits the closest `<form>`, so the server sees an
  ordinary request; coloured from the tokens, no glow, radius 10.
  `type="button"` was added to the vendored button so it cannot submit
  by itself.

Dropped after inspection: TearTicket (renders its own ticket layout, and
ours already has a tear-off and a stamp), SpringCheck (no native input,
strikes the label through — wrong for a legal checkbox), AnimatedList
(strings only, fixed height).

## Appearance

Light, dark, or the device's choice, with **system as the default**: nobody who
has not picked a side is handed one. `HandleAppearance` shares `system` when
there is no cookie, `use-appearance.tsx` stores `system` when there is no
localStorage entry, and the inline script in `app.blade.php` resolves it from
`prefers-color-scheme` before first paint.

- **`ThemeToggle` sits beside `LanguageToggle` everywhere** — every public
  page, the auth layout, and the dashboard header. `AppearanceTest` asserts
  the pair travel together. The trigger shows the *resolved* mode; the menu
  offers all three, because a visitor with no account has no settings page to
  get back to "system" from.
- **The switch is animated by `react-theme-switch-animation`** (View
  Transitions API, circular reveal from the button; instant under reduced
  motion or where the API is missing). It runs in **controlled mode** —
  `isDarkMode` + `onDarkModeChange` — so our store stays the source of truth:
  it writes the cookie the server render reads and it knows about "system",
  which the package does not. A `pending` ref carries the menu choice through
  the package's toggle-only API so picking "system" is persisted as "system".
- The package also writes `localStorage.theme`. It is inert — nothing reads
  it — but do not mistake it for ours, which is `localStorage.appearance`.
- Installing a new dependency while `npm run dev` is up makes Vite
  re-optimise and briefly serve two module graphs; React logs "Invalid hook
  call" once and the page recovers on the next full load. Not a bug in the
  code that was just added.

## Notifications

Every message the app sends is a push, because there is no mailer and no SMS
gateway. `PushSender` composes; `NotificationCopy` chooses the words.

- **Wording never lives in the sender.** Each kind is a list in
  `lang/{ar,en}/push.php` and both languages must offer the same number of
  variants — `NotificationCopyTest` asserts it, so an English-only addition
  fails rather than silently making Arabic repetitive.
- **The title is always the event, the body is what rotates.** The tray has to
  say what this is about at a glance.
- **`tickets:remind` runs hourly, `tickets:expire` every minute.** The reminder
  is a courtesy with a wide window; releasing a seat is not. A hold that has
  already lapsed is never reminded — "pay by 4pm" arriving at 5pm is worse
  than silence.
- **One nudge per hold, stamped whether or not a device accepted it.** The
  point is one reminder, not one successful delivery; retrying every hour
  against a dead phone is spam.

## Locations

A venue is not one address. `places` holds who and what; `locations` holds
where, and an event picks one. Location moved off `places` entirely so there is
a single source of truth.

- **An event without a location falls back to the venue's primary one**, so an
  event drafted before locations existed still shows an address.
- **Exactly one primary per venue, always.** Creating the first location makes
  it primary; deleting the primary promotes the next. A venue with locations
  but no primary would leave every event resolving to nothing.
- **Deleting a location nulls its events' `location_id`** rather than cascading.
  Losing a room must not lose the event booked into it.
- **`location_id` is validated against the owner's own venue.** Without the
  scoped `exists` rule an owner could attach their event to someone else's
  address.
- **Photos are re-encoded on upload**, which strips EXIF — a phone photo of a
  venue carries the photographer's GPS — and caps what every visitor downloads.

## Maps

Leaflet 1.9 over OpenStreetMap raster tiles. No API key, no per-view cost, and
no vendor to migrate off. Both the owner's picker and the public sheet share
`MapCanvas`, which imports Leaflet and its stylesheet dynamically.

- **A venue with no pin exposes `location: null`**, and the public name renders
  as plain text. A control that opens an empty map is worse than no control.
- **Latitude and longitude are validated as a pair.** Half a coordinate is a
  point in the Gulf of Guinea, not a missing value.
- **Geocoding is on explicit submit only.** Nominatim asks for at most one
  request a second, and an owner sets this once. The pin, not the search
  result, is authoritative.
- **The marker is a `divIcon` with inline SVG.** Leaflet's default icon
  resolves PNGs relative to its stylesheet, which a bundler rewrites and
  breaks. It also means Leaflet will not name the marker for us — a draggable
  marker is a focusable `role="button"`, so `aria-label` is set by hand.
- **Only `.leaflet-tile-pane` is inverted in dark mode.** OSM has light tiles
  only; inverting the whole container would invert our own pin and controls.
- **Landmark is a first-class field**, not part of the address line. Street
  addressing in As-Suwayda is not what people navigate by.
- **Every new fixed public path goes into the reserved list on `/{place}`.**
  `for-venues` was added there in the same commit as its route, and
  `ForVenuesTest` creates a venue with that slug to prove the page wins.
- **`/{place}` is registered dead last and excludes reserved segments.** One
  free segment at the end of the table otherwise answers every fixed path, and
  a clean 404 on `/register` turns into a 405 that tells a prober something
  lives there.

## Gotchas learned the hard way

- **`null` and `log` broadcasters no-op their `auth()`.** Channel authorisation tests pass against a
  wide-open channel. The auth test swaps in a real broadcaster and re-registers channels.
- **Reverb 1.x cannot run with guzzle 8** (no release supports psr7 3.x). Guzzle is pinned to `^7`.
- **Vite does not expand `${VAR}`** in `.env`. `VITE_*` values must be literal.
- **`X-Frame-Options: DENY` blocks iframe-based UI auditing.** Audit by resizing the real viewport.
- **Tests that build URLs from models cannot catch routing bugs** — assert the literal path.
- **Prettier reformats between scripted edits.** Re-grep after every one; multi-line replacements
  silently miss.
- **Enum-cast columns do not match raw strings** in in-memory collection filters. `unattended()`
  returns cases for exactly this reason.
- **`DatabaseTruncation` commits; `RefreshDatabase` does not.** `OverbookingTest` needs committed
  rows so its forked processes can see them, so it truncates in `tearDown` as well. Without that it
  leaves rows behind and every later test's row counts depend on execution order.
- **Validate before you persist.** The commercial acknowledgement was first
  checked after `$event->save()`, so a refused submission still created the
  event. `assertTermsAcknowledged()` now runs before the save and the test
  asserts `Event::count()` is still zero.
- **Inertia's `where()` uses strict comparison, and JSON has no `5.0`.** A
  float that happens to be whole comes back as an int; assert `5`, not
  `5.0`.
- **The migration's placeholder owns version `1.0`.** A test that creates
  a version `1.0` hits the unique index; the factory numbers from `1.1`
  and tests that pick their own numbers use `5.x`.
- **`Log::shouldReceive()` is how the log OTP driver is read in tests**:
  the mock's `withArgs` closure captures the last six characters of the
  message, which is the code. Set `config(['otp.driver' => 'log'])` and
  `forgetInstance(OtpChallenge::class)` first, or the singleton keeps the
  null sender it was built with.
- **`null <= 0` is `true` in JavaScript.** When `seats_remaining` became
  nullable, every `soldOut = seats_remaining <= 0` on the client silently
  read an unlimited event as sold out. Each check is now `!== null &&`.
- **`assertSessionHas('key', null)` cannot pass.** It goes through `has()`,
  which treats a null value as absent. Assert with a closure over the
  parent key instead.
- **A Radix `Checkbox` submits only when it has a `name`**, through a
  hidden input it renders itself, and only when ticked. The server reads
  those with `boolean()`; filling them from the validated set would leave
  the old value in place when the box is unticked.
- **The saved-event dialog is derived state, not an effect.** The flash
  lives one request; the dialog is open while the payload exists and is
  not the one dismissed. `react-hooks/set-state-in-effect` refuses the
  `useEffect` + `setState` version.
- **`disabled` does nothing on a shadcn `Button asChild`** that renders an anchor — the control still
  navigates. Do not render the action at all when it is not permitted.
- **The sidebar pins with physical `left-0`/`right-0`** from its `side` prop while its spacer follows
  document flow, so `side` must track direction or the two disagree in Arabic.
- **A backgrounded Chrome tab throttles `requestAnimationFrame`**, so Motion animations
  *start* and never tick. Stagger children sit frozen at their `hidden` variant, which looks
  exactly like a broken animation. Check `document.visibilityState` before diagnosing motion.
- **Error pages are outside the design system by construction.** Their CSS is inline so it
  survives a missing asset manifest, which also means a rebrand does not reach them. Grep
  `resources/views/errors/` for hex literals whenever the palette changes.
- **`AppContent` renders `#main-content` only in its non-sidebar variant.** The
  skip link is emitted by `app.blade.php` for every page, so for a long time it
  pointed at nothing on the entire authenticated side.
- **Breadcrumb and nav `title:` values are translation keys**, and nothing
  resolves a literal — it just renders as typed, in both locales.
  `TranslationCatalogueTest` now asserts every `title:` is a resolvable
  dot-path, and separately that every literal `t('a.b')` call site resolves.
- **Writing PHP string literals from a script needs the quotes escaped.** An
  apostrophe in a single-quoted English string has now broken `lang/en/ui.php`
  three times ("What's on", "venue's", "Children's"). `TranslationCatalogueTest`
  lints each file in a **subprocess** for exactly this: a parse error in a
  `require`d file is fatal and takes the suite down, so the one thing that test
  exists to catch would report as a crash rather than a named file and line.
- **`php -l file && echo ok` is not a check if you do not look for the `ok`.**
  An apostrophe in a single-quoted English string broke `lang/en/ui.php` again;
  the lint ran, failed silently into `/dev/null`, and the missing "ok" went
  unnoticed. Arabic-locale smoke tests pass right through a broken English file.
- **`/icons/` is cached by the service worker and is not content-hashed.**
  Changing an icon without bumping `VERSION` in `public/sw.js` leaves every
  installed PWA on the old one forever. `/build/` is hashed and looks after
  itself; `/icons/` does not.
- **`document.featurePolicy` is deprecated and answers the wrong question.**
  It reported `camera: false` on a page whose header said `camera=(self)`,
  because it conflates a user's refusal with a site policy — so the scanner
  told owners the site was blocking a camera they had merely declined.
  `navigator.permissions.query()` is the authority; the header check is only
  worth consulting when nothing has been decided yet.
- **A device feature can fail for three reasons that look identical.** Not
  HTTPS, denied by our own `Permissions-Policy`, or refused by the user — only
  the last is theirs to fix. `lib/capabilities.ts` tells them apart, and every
  camera/geolocation/notification call site reports which one it hit. Saying
  "camera denied" when the page is not on HTTPS is the classic "works on my
  laptop, not on my phone" report.
- **`Permissions-Policy` denies by default, and a denial cannot be prompted
  past.** A feature missing from the header is *allowed*; one set to `()`
  cannot be re-enabled by asking the user. Grant per route in
  `SecurityHeaders`, as the scanner does for the camera and the venue page
  does for geolocation.
- **`getOriginal()` applies the cast; `getRawOriginal()` does not.** Comparing
  `getOriginal('status')` to `EventStatus::Published->value` compares an enum
  to a string and is always false, which silently knocked every edited live
  event back into review.
- **A bare `void el.offsetWidth` is deleted by the minifier.** It is the
  standard trick for restarting a CSS animation, and it works in dev and
  silently stops working in production — the reflow read has no observable
  effect, so it is dead code. Use the value: `if (el.offsetWidth >= 0) {…}`.
- **Laravel discovers listeners in `app/Listeners` on its own.** Registering
  one *again* with `Event::listen` in a service provider does not replace the
  discovered binding, it adds a second — so every ticket status push went out
  twice, to every device, for as long as both were in place. `php artisan
  event:list` shows the duplicate; `PushSenderTest` now asserts one send per
  device per status change.
- **Postgres will not accept a `HAVING` clause against a subquery alias.**
  `withCount()` compiles to a correlated subquery, so `having('events_count',
  '>', 0)` is a hard error rather than a slow query. Filter the collection in
  PHP when the result set is small enough to warrant it.
- **A collapsed section must keep its fields mounted.** `CollapsibleContent`
  unmounts by default; on a form that silently discards whatever was typed
  into a section before it was folded away. `FormSection` passes `forceMount`
  and hides with CSS instead.
- **Carbon's `->locale()` is a getter/setter overload**, so PHPStan types it
  `static|string` and refuses the next chained call. `->settings(['locale' =>
  ...])` is the same thing with an honest return type.
- **An unencoded UTF-8 query string is mangled by the test client.**
  `$this->get('/?q=شعرية')` corrupts a byte before the request ever reaches
  the app, which looks exactly like a broken search. Browsers percent-encode;
  tests must too.
- **A Radix `asChild` trigger overwrites `data-slot="button"`** with its own
  slot (`dropdown-menu-trigger`, `dialog-trigger`, and so on). The
  coarse-pointer rule that widens icon-only controls was keyed on the button
  slot, so it silently missed every icon button that opens a menu or a sheet —
  which is most of them. Match structurally on the element, not on the slot.
- **A `className` override can defeat a component's own tap-target floor.**
  `LanguageToggle` carries `min-h-11`; two call sites passed `min-h-9` and
  dropped it to 36px on the venue page and every auth page. A utility that
  *lowers* a floor is almost always a mistake.
- **Arabic-Indic digits were hardcoded in `lang/ar/ui.php`.** One of them told
  the owner to type `٠` to make an event free, next to an
  `input[type=number]` that silently discards that character — so following
  the instruction literally produced an empty field.
  `TranslationCatalogueTest` now lints the Arabic catalogue for them.
- **The hardcoded-English sweep had three blind spots**, and each hid a real
  string: it exempted `components/ui/` (where every dialog's screen-reader
  label lived), capped matches at sixty characters (so only the long
  sentences escaped), and required the first word to be capitalised (so
  `>log in<` and `>or you can<` read as markup). The `=>` of an arrow function
  also ends in `>`, so the pattern needs a lookbehind or every inline callback
  body reads as prose.
- **`<Heading>` renders an `h2`**, so the whole authenticated side had no `h1`
  at all. It is emitted once by `app-sidebar-layout` from the last breadcrumb,
  which is already a translation key — do not add per-page ones.
- **The handoff's wordmark SVGs are ElementTree exports.** `<ns0:svg
  xmlns:ns0=…>` renders as an `<img>` but cannot be inlined into JSX, and the
  fill is hardcoded so it cannot be recoloured. Normalise once, commit the
  result, and let a test pin the path data — do not reach into the handoff
  folder at build time.
- **`VITE_APP_NAME` must be a literal.** Vite does not expand `${APP_NAME}`.
- **Changing `APP_NAME` signs everyone out once.** The cache prefix and the
  session cookie name are slugged from it (`config/cache.php`,
  `config/session.php`). Run `php artisan cache:clear` in the same deploy.
- **A `@theme` variable cannot alias itself.** `--shadow-raised:
  var(--shadow-raised)` is circular; the raw value lives on `:root` as
  `--elevation-raised` and `@theme` maps `--shadow-raised` to that.
- **`button.tsx` never emitted `data-size`**, so the coarse-pointer rule that
  raises `lg` buttons to 52px matched nothing for as long as it existed. It
  emits it now.
- **The pre-paint background in `app.blade.php` had stale starter values**
  (`oklch(1 0 0)` / `oklch(0.145 0 0)`): pure white on first paint, on a
  phone, in a dark venue. It carries the real `--background` tokens now, and
  they must be changed by hand whenever those move.
- **`LanguageToggle` built its href from `window.location`**, which the server
  render does not have, so SSR emitted `/?lang=en` on every page and React
  logged a hydration mismatch on every load. It reads `usePage().url` now.
- **`Vite::asset()` resolves files referenced from CSS `url()`.** That is how
  the font preloads find their hashed paths; `@vite` only preloads its own
  chunks and CSS.
- **The shadcn CLI rewrites pinned dependency versions.** Adding the React
  Bits items changed `motion` from `^13.1.0` to `^12.43.0` in
  `package.json` without asking. Check `package.json` after every
  `shadcn add` and restore the pin.
- **The npm cache on this machine has root-owned entries**, which silently skips platform-specific
  optional deps. Fix: `sudo chown -R $(id -u):$(id -g) ~/.npm`.

---

## Working agreement

- Run `composer ci:check` before claiming anything is done.
- Verify in a browser, not by reading the diff. Screenshots of this app's dev server can be stale —
  measure the DOM when a layout looks wrong.
- When a test would have caught a bug you just fixed by hand, write the test.

---

## Open — needs the owner, not code

- **FCM credentials.** The client config is filled in, but the two halves that
  matter are not, and they fail independently: without `VITE_FCM_VAPID_KEY` the
  browser is never asked for permission (the opt-in does not render at all), and
  without `FCM_CREDENTIALS` devices register but nothing can be sent. The VAPID
  key is public; the service-account JSON is not and belongs on the server.
- **Real-device testing.** The camera scanner and PWA install flow have only ever run in a desktop
  browser. This is the largest remaining risk.
- **Dark mode sign-off.** The dark palette is derived from the light handoff
  (see Design system). It is measured, not designed; the designer has not
  seen it.
- **Button text colour.** Ink on orange, against the handoff's white on
  orange, for contrast. The designer has been told the numbers.
- **`Settings::DEFAULTS['icon_path']`** is declared, never written, never
  read. Dead key; remove or wire up.
