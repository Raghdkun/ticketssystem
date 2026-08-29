# CLAUDE.md — Swaida Tickets Hub / مركز تذاكر السويداء

**Read this first, every session.** It is the project's memory: what this is, what is decided, what
is built, and what is next. Update it at the end of every working turn — it is the only thing that
survives a new session.

---

## What this is

An **offline-payment** event ticketing platform for As-Suwayda, Syria. Visitors reserve a seat
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
5. **Public registration is closed, and an invitation is the only door.** There
   is no open sign-up. An admin mints a one-use, expiring invitation; only its
   SHA-256 hash is stored, so the link is knowable exactly once and a leaked
   database yields nothing. The account is created with the *invited* address,
   so a forwarded link cannot become a stranger's account, and the whole
   thing — user, venue, first location — happens in one transaction under a
   row lock, so a double submission cannot make two accounts.
6. **Pending reservations hold seats**, with auto-expiry after `hold_hours` (default 24).
7. **Seat safety is a row lock.** `AppointTicket` does `lockForUpdate()` on the *event* row. Removing
   it makes `OverbookingTest` fail — verified by deleting it and watching all 12 contenders win 5 seats.
8. **Arrivals are separate from bookings.** Someone who paid for 5 and brought 3 still paid for 5;
   revenue follows `seats_paid`, never `seats_arrived`.
9. **No-show ≠ cancelled ≠ expired.** Three distinct facts: not used, called off, timed out.
10. **Ticket pages are `noindex`** and excluded from the sitemap. Lighthouse scores that as an SEO
   failure; it is correct — the URL is a bearer token beside a name and phone number.
11. **SVG is never an accepted upload.** Script-carrying markup on our own origin.
12. **Impersonation is full-access**, so the audit log is the only record of who acted. Start/stop are
   both logged, nesting is refused, super admins cannot be impersonated, banner is always visible.
13. **Arabic needs its own face.** Instrument Sans has no Arabic glyphs; IBM Plex Sans Arabic ships
    alongside it. Do not remove it — Arabic silently falls back to an OS font.

---

## Repository map

| Path | What |
|---|---|
| `app/Actions/` | `AppointTicket` (seat locking), `VerifyTicket` (check-in, no-show, cancel) |
| `app/Services/` | `CoverProcessor`, `MediaLibrary`, `EventReport`, `Settings`, `PlatformStats`, `PushSender` |
| `app/Support/` | `Color` (WCAG maths), `QrCode`, presenters |
| `resources/js/pages/public/` | event, ticket, my-tickets — no app chrome |
| `resources/js/pages/owner/` | dashboard, events, place, scan, search, verify, door-sheet, report |
| `resources/js/components/map/` | `map-canvas` (shared Leaflet), `map-picker` (owner) |
| `resources/js/pages/admin/` | owners, settings |
| `lang/{ar,en}/ui.php` | the client string catalogue, shared via Inertia as dot-notation |

---

## Conventions that are load-bearing

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

**204 tests**, PHPStan clean, Lighthouse mobile 100 on accessibility / best practices / SEO / agentic.

---

## Design system — Basalt & Saffron

Imported from the Claude Design project. Jade carries structure, navigation and
the paid state; saffron is reserved for high intent and appears on at most one
control per screen. Basalt on warm paper, light by default.

- **Per-event palette extraction is retired.** One fixed identity carries the
  whole product, so a ticket from any venue is recognisably the same platform.
  `PaletteExtractor`, `ThemeMode` and the `.event-theme` scope are gone.
- **Status is a dot plus text, never colour alone.** The door is badly lit and
  some readers are colourblind.
- **The brand ramp lives on `:root`, not `@theme`.** Tailwind prunes unused
  `@theme` values, so `var()` lookups from inline styles would resolve to
  nothing.
- **Migrations must not reference app enums.** Deleting `ThemeMode` broke every
  test at the migration step; column defaults are literals now.
- Primary controls are 52px on coarse pointers, focus is a 2px jade ring.

## The mark — "The Pass"

Imported from the Claude Design project (`logo-final-the-pass.dc.html`). A
solid die-cut ticket with no letterform in it, so nothing needs translating:
any script sits beside the mark. Three moves only — the notch, the tear, and
the saffron dot that is the person who got in.

- **`resources/brand/*.svg` are the source of truth.** `npm run icons`
  rasterises them into `public/`. Never edit anything in `public/icons/` by
  hand; a test asserts the rasters exist at the right sizes.
- **Detail drops with size.** 48px keeps the perforation, 32px drops it, 16px
  drops the saffron dot too and widens the seat. The artboard's rule, and the
  reason `AppLogoIcon` takes a `detail` prop.
- **The mark mirrors in Arabic** so the stub trails the reading direction.
  That mirror is an inline `transform` on the `<svg>` root, which is why the
  entrance animation scales an inner `<g>` — animating the root's transform
  overrode the mirror and the mark flipped when the animation ended.
- **Punch-outs follow `--mark-cut`, defaulting to `--background`.** A punch
  reveals what is behind it, so on the dark theme the holes must go dark or
  they stop reading as holes. Override it where the mark is reversed onto a
  solid tile.
- **The maskable icon is its own drawing.** The square icon's notches bite the
  tile edge; a maskable icon is cropped to a circle, so those notches either
  vanish or read as two floating dots. It uses the reversed mark instead.

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
- **Numeral script in Arabic.** Dates render Arabic-Indic (`ar-SY`), every other figure
  renders Latin, so a single line can carry both — "14 من 89 مقعد · ١٣ أيلول". The artboards
  use Arabic-Indic for counts and dates and Latin only for currency. Picking one rule is a
  product call, not a code call, and it touches every page that prints a number.
- **Real-device testing.** The camera scanner and PWA install flow have only ever run in a desktop
  browser. This is the largest remaining risk.
