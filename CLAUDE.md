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
2. **Public registration is closed.** Super admins provision owners (account + venue in one
   transaction) from `/admin/owners`. Re-enabling `Features::registration()` would let anyone create
   an account on a venue-management platform.
3. **Pending reservations hold seats**, with auto-expiry after `hold_hours` (default 24).
4. **Seat safety is a row lock.** `AppointTicket` does `lockForUpdate()` on the *event* row. Removing
   it makes `OverbookingTest` fail — verified by deleting it and watching all 12 contenders win 5 seats.
5. **Arrivals are separate from bookings.** Someone who paid for 5 and brought 3 still paid for 5;
   revenue follows `seats_paid`, never `seats_arrived`.
6. **No-show ≠ cancelled ≠ expired.** Three distinct facts: not used, called off, timed out.
7. **Ticket pages are `noindex`** and excluded from the sitemap. Lighthouse scores that as an SEO
   failure; it is correct — the URL is a bearer token beside a name and phone number.
8. **SVG is never an accepted upload.** Script-carrying markup on our own origin.
9. **Impersonation is full-access**, so the audit log is the only record of who acted. Start/stop are
   both logged, nesting is refused, super admins cannot be impersonated, banner is always visible.
10. **Arabic needs its own face.** Instrument Sans has no Arabic glyphs; IBM Plex Sans Arabic ships
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
- **`php -l file && echo ok` is not a check if you do not look for the `ok`.**
  An apostrophe in a single-quoted English string broke `lang/en/ui.php` again;
  the lint ran, failed silently into `/dev/null`, and the missing "ok" went
  unnoticed. Arabic-locale smoke tests pass right through a broken English file.
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
