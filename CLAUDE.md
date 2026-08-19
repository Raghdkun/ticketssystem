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
| `app/Services/` | `PaletteExtractor`, `CoverProcessor`, `MediaLibrary`, `EventReport`, `Settings`, `PushSender` |
| `app/Support/` | `Color` (WCAG maths), `QrCode`, presenters |
| `resources/js/pages/public/` | event, ticket, my-tickets — no app chrome |
| `resources/js/pages/owner/` | dashboard, events, scan, search, verify, door-sheet, report |
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
  must be readable — if motion does not run, the content must still be visible.
- **Every control is ≥44px on coarse pointers** (`@media (pointer: coarse)` in `app.css`).
- **Flash messages are `{ type, message }`** under `flash.toast`. A bare string renders nothing.

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

**176+ tests**, PHPStan clean, Lighthouse mobile 100 on accessibility / best practices / SEO.

---

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

- **FCM credentials.** `FCM_*` and `VITE_FCM_*` are blank by design; push is inert until filled.
- **Real-device testing.** The camera scanner and PWA install flow have only ever run in a desktop
  browser. This is the largest remaining risk.
- Auth screens (login, password reset) are English-only and force LTR until translated.
