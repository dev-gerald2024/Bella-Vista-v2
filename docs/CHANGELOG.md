# Changelog

## Room assignment locked to customer's booking (November 26, 2026)
- **Admin > Assign Room & Therapist:** when a booking already has a room, the room is shown as read-only text (reserved by the customer at booking). The room dropdown and the "reason for changing the room" field were removed for those bookings. Bookings with no room still show the "Select Room" dropdown. The therapist can still be assigned or changed. Change is in `js/admin.js` (`a==="asgn"` dialog).
- **Policy change:** this reverses the earlier BV-021 decision (admins may change a booked room with a reason). Server-side `DB.assignRoom` still accepts room changes if called directly; only the admin UI is locked.
## v9.9 + new interface (November 25, 2026)
- **Combined build:** all server logic, tests and fixes from v9.9 (atomic payments, `/api/quote`, last-booking prefill, default phone question, reminders, offline password-reset link, pay-after-approval) together with the newer interface: split login page, redesigned booking form with time-slot buttons, redesigned My Account and admin dashboards, Meet the Team (About page and Admin > Team Profiles, shown A to Z and centred), per-page banners, hero slideshow, and the new notification pop-ups.
- **Wording:** the word "demo" and the "no real money" banners were removed from every page, receipt, notification and admin screen. Internal names (`GCASH_DEMO`, `process_demo_payment`, `DEMO_MODE_ACTIVE`) were kept so the database and tests keep working. Old reference numbers saved as `TXN-DEMO-...` are shown without the tag.
- **Settings:** the "Go-live checklist" tab and the dashboard "setup items" notice were removed.

## v9.1 - Audit repair (November 24, 2026)
- **Docs:** new `docs/RELEASE_CHECKLIST.md` lists the remaining environment and owner steps.
- **Polish:** admin name now correct in every admin log line (`admin_display_name()`); an admin edit of a booking's date/time/service moves its room, cleaning slot and therapist (`move_booking_resources()`, shared with reschedule approval); simulated reminders (`send_booking_reminders()`, button on the admin overview) create one in-app notification per booking in the next 24 h for customers who keep reminders on.
- **Reminders note:** the customer portal already showed an on-the-fly "appointment is tomorrow/today" notice for CONFIRMED bookings (preference-aware). A booking that already has a saved reminder no longer shows the on-the-fly one, so customers never see both.
- **Inquiry button:** "Send an inquiry" in a service pop-up now opens the booking form with that treatment already selected (`contact.html?service=...#booking`).
- **Garbled text fixed:** `contact.html` showed `1â€“6`, `â€”`, `â‚±` (UTF-8 saved twice); repaired and a test now guards all pages, scripts and styles.
- **Cancel order:** a customer cancelling a paid booking now sees "cancelled" first and "refund" second (the booking is cancelled, then refunded, in one transaction under the booking lock; a failed refund undoes the cancellation).
- **Pay only after approval:** a customer can pay only when the booking is CONFIRMED. Server refuses payment for PENDING bookings (409 `awaiting_confirmation`); My Account shows "Payment opens once the spa confirms your appointment" instead of the Pay button; the confirmation notification tells the customer they can now pay. The booking confirmation/receipt no longer lists a downpayment (it is in My Account). This avoids paying for a slot that is later declined or given to someone else.
- **(replaced above) Downpayment wording:** the confirmation and receipt say "Downpayment due later ... not charged yet; paid later from My Account" so it no longer looks like money was taken.
- **Offline password reset:** with no email server, the Forgot password page now shows the reset link (button "Choose a new password") for an existing account; no CDN or email needed. Still token-based, single use, expires in 1 hour. Off automatically in production or when SMTP is set; `OFFLINE_RESET_ON_SCREEN` turns it off. The email-only reset in `resetpassword.php`/`check_email.php` was NOT adopted (see README note).
- **Pre-fill from last booking:** the form fills in the customer's last treatment, length and special notes (all editable; a treatment chosen on the Services page wins). New `GET /api/bookings/last`.
- **Default number question:** after a booking with a different number the customer is asked whether to make it the default (`PATCH /api/portal/phone`); "No" keeps the saved one.
- **Phone saved once:** the first booking stores the customer's number on their profile; the booking form then fills it in automatically. A different number on a later booking applies to that booking only (the profile number changes only in My Account).
- **Function audit:** found and fixed missing phone helpers on account.html and admin.html (now `js/phone.js`, loaded everywhere); full report in `docs/FUNCTION_ANALYSIS.md`.
- **Policy decision (BV-021):** admins may change a booked room with a written reason; customer choice is not immutable. Old "room locked" changelog text corrected. Admin name in the room-change log is now read from the signed-in admin.
- **Quote parity:** `/api/quote` now prices an explicitly chosen package even when the room is `auto`, matching the booking save in INDEPENDENT, INCLUSIVE and ADDITIVE modes (parity tests added to `tests/service_suite.php`).
- **Storage engine:** `tests/payment_suite.php` verifies every table is InnoDB (needed for `tx()` rollback).
- **Financial atomicity:** payment, refund, cash confirmation and forfeiture now run their writes (transaction row, booking summary, activity, notification, audit log) inside one `tx()` while keeping the per-booking lock. Any failure rolls everything back. Failure-injection tests added to `tests/payment_suite.php` (needs MariaDB).
- **Authoritative quote:** new `GET /api/quote` (`api_booking_quote`) uses the same functions as the booking save. The booking confirmation and receipt show the server's total (package included in ADDITIVE mode), downpayment and balance; the hard-coded 25% is gone. One shared formula `required_downpayment()` = `min(total, max(minimum, total x %))`.
- **Service prices:** `1,100` is accepted ("800, 1,100", "800,1100", "800,900" and "₱800; ₱1,100" all work); ambiguous input that does not match the number of durations is refused.
- **Packages (decision):** packages are room-type rate cards linked automatically from the chosen room's category and guest count; there is no customer package picker by design.
- **Tests:** new `tests/logic_suite.php` (no database needed).
- **Not verified:** database-backed suites, browser, concurrency (no MariaDB in the build environment).

## Phase 7 - System recovery and integration (November 23, 2026)
Built on Phase 6 (v8-phase6_1). Phase 1 work was recovered selectively (it had been a separate branch that Phases 2-6 never included). Every item below was checked by a test (see docs/TESTING.md) unless marked otherwise.
- **Database:** `seed.sql` recovered (3 categories, 7 rooms, 3 packages, 4 therapists, 28 shifts, 5 settings; idempotent). `schema.sql` no longer drops anything; the destructive reset is the separate `dev-reset.sql`. New `schema_migrations` table and `tools/migrate.php` runner. Migration 009 reconciles databases built from migrations 001-007 (they did not match `schema.sql`); 010 clarifies room references. Migration order, repeat-safety and the legacy upgrade were tested.
- **Payments:** minimum downpayment restored (`min(total, max(500, 25%))`); `ALLOW_FULL_PAYMENT` and `FORFEIT_LATE_CANCELLATION` are read and enforced by the server; forfeiture only for a paid, un-refunded downpayment and never twice; late cancellation judged in Manila time. New fixes found by the tests: a partial refund crashed the server; two simultaneous payments were both recorded; cancelled bookings could be paid; a client-supplied `userId` could override the session user.
- **Booking integrity:** the server validates the chosen room (exists, open, capacity, free incl. cleaning buffer) and never substitutes another; booking + room + cleaning + payment figures are one transaction under a per-day lock (no more silent catch); admin can change a customer's room with a reason (history kept, customer notified).
- **Services and packages:** admin-managed services (durations, prices, available yes/no) are the single source for the server and the booking form; only listed durations can be booked; no silent default price. Packages are saved on the booking, validated, priced by `PACKAGE_PRICING_RELATIONSHIP` and shown to customers, admins and on receipts.
- **Security:** CSP no longer needs an inline-script hash (`js/boot.js`); rate limiter refuses sign-in/registration/reset when it cannot store counters (instead of silently turning off); forgot-password no longer reveals which emails are registered; `tests/` is blocked in `.htaccess`.
- **Business details:** the generic Facebook link is hidden; the admin dashboard now lists contact details that still show sample text.
- **Docs:** README-XAMPP and README corrected (install order, PHP 8.1+/MariaDB, migrations, payments, packages, services); new DATABASE.md, TESTING.md; BACKUP.md rewritten after a real dump/restore test.
- **Design pass (from screenshots):** removed the visible placeholder text on Accommodations (real text from the room and booking rules instead); service cards and their detail pop-up now show each treatment's lengths and prices from the same data the booking form uses; body text weight 300 -> 400 and darker secondary text (#3d4b40, 8.4:1 on ivory; the old colour already passed 6.7:1, the thin weight was the problem); small labels raised to 12-13px; the intro on Home/About is now two columns; sections that were repeated on several pages (philosophy, journey, "Slow down" banner, the duplicate featured Body Massage, About's copy of the Home intro) now appear once, on the Wellness page. Cache-busters bumped to `?v=p7`. Checked by loading the pages against Apache (no script errors, no placeholder text, 5 prices shown); **not** checked by eye in a browser.
- **Not done / not verified:** local font files (needs internet once), real browser and responsive testing, phpMyAdmin and Windows/XAMPP runs, a penetration test.

## Phase 6 - Offline and quality checks
- `tools/download-fonts.php` (run once online) saves the fonts locally and writes `css/fonts.css`; every page loads `css/fonts.css` before the Google link. CSP `font-src` now allows `'self'`. `tools/` is blocked from the web in `.htaccess`.
- Contact map: the address is always shown as text under the map; no empty map box when the browser is offline.
- Added meta descriptions to `account.html` and `admin.html`.
- New docs: `OFFLINE_MODE.md`, `BACKUP.md`. Removed absolute `file:///C:/...` links from `docs/README.md`.
- Automated check of all 15 pages at 375 px and 1280 px wide: no horizontal overflow, no missing local files, no JavaScript errors.

## Phase 5 - Admin experience
- Dashboard overview reorganised around daily work: "Needs your attention" (pending bookings, reschedule requests, bookings without a room, cash to collect), "Today" (appointments, in progress, rooms and therapists available now) with a time-sorted list, then recent activity and totals. Attention tiles link to the right page.
- The old revenue card is now labelled as amounts entered on bookings, not real income.
- Booking calendar chips show time and room; the tooltip shows service, status and room (or "no room yet").
- Fixed "today" in the admin (`day()` used UTC, so between 12:00 AM and 8:00 AM in Asia/Manila it returned yesterday).
- Card labels are sentence case instead of all caps.

## Phase 4 - Customer experience
- Homepage: replaced the "Est. 2021" line with a plain facts row (hours and address read from the admin-editable contact info, plus the 30-minute room reset). Rewrote the generic wellness paragraph to say what actually happens (room match, therapist assigned).
- Removed the repeated "Slow down. Breathe deeper." banner from the homepage and services page; the closing "Make Space for Yourself" call to action stays.
- Services: removed 01/02/03 numbers from "Restore / Reconnect / Renew" (not a sequence; the Arrive-to-Renewal journey keeps its numbers). Headings no longer all caps.
- Booking: the time hint now says how many start times are unavailable and why (booked, or room being cleaned for 30 minutes).
- Customer portal booking cards already show date, treatment, duration, room, status, payment and balance, and have empty states, so they were left unchanged.

## Phase 3 - Design system
- New `css/tokens.css`: one source for colours, fonts, radius, shadow, easing. Loaded first on all 15 pages. Removed the duplicate `:root` blocks from `style.css`, `account.html` and `admin.html`.
- Moved the ~25 KB / ~16 KB embedded styles out of `account.html` and `admin.html` into `css/account.css` and `css/admin.css`.
- `contact.html`: 24 of 25 inline `style` attributes replaced by `bk-*` classes in `style.css`. Computed styles verified identical in a browser. The one left (`display:none` on `#altRoomsSection`) is toggled by `booking.js`.
- Gold text token on account/admin is now `#735617` (was `#8a6a28`), same as the public site, for better contrast.

## Phase 2 - Authentication cleanup
- Removed `/api/auth/reset-direct` (changed any password with only an email). Password reset is now token-only: `/auth/forgot` then `/auth/reset`.
- `forgot.html` asks for the email only; `reset.html` works only from a valid link (email, or `data/mail.log` with no SMTP).
- Deleted legacy `login.php`, `register.php`, `resetpassword.php` and `database/db_connection.php` (second auth system, bypassed email verification, referenced a missing `check_email.php`). Old URLs redirect to the new pages.

## 2026-11-22 — Admin booking workflow rules

Rules are enforced in the admin UI (`js/admin.js`) and again on the server so they cannot be bypassed by calling the API directly.

### Room assignment (SUPERSEDED by the v9.1 policy below)
- Earlier rule (2026-11-22): the room was locked to the customer's choice. **This was replaced in Phase 7.** Current rule, the same in UI, server and docs: an admin MAY move a pending/confirmed booking to another room, but only with a written reason (at least 5 characters); the server re-checks status, capacity and availability, keeps the old assignment as RELEASED history, logs it and notifies the customer. Bookings with no room can be assigned without a reason.

### Start button
- Blocked when the payment status is `UNPAID`; allowed for `PARTIALLY_PAID` or `PAID`.
- Blocked when no therapist is assigned to the booking.
- UI warnings: "Payment Required", "Therapist Required".
- Server (`php/lib.php`, `validate_status_transition`): rejects the `IN_PROGRESS` transition with a matching message. `php/portal.php` passes `payment_status` and `therapist_id` into this check at both status-change call sites.

### Complete button
- Blocked when the payment status is `UNPAID`; allowed for `PARTIALLY_PAID`, `PAID` or `REFUNDED`.
- Blocked before the scheduled appointment time.
- Separate warnings per scenario: "Payment Required" and "Too Early to Complete".
- Server (`php/lib.php`): same rules applied to the `COMPLETED` transition, with distinct error messages.

### Booking rows
- Removed the **Edit** button from booking rows in the admin list. Edit buttons for rooms, packages, therapists and other lists are unchanged. The server's booking-update route is not restricted.

### Files changed
| File | Change |
|---|---|
| `js/admin.js` | Start/Complete guards, Edit button removal (room lock replaced by the reason-required change, see above) |
| `php/lib.php` | Payment, therapist and time checks in `validate_status_transition` |
| `php/portal.php` | Passes payment status and therapist ID to the status check |
| `php/rooms.php` | Room change allowed only with a reason (see above) |

## Design polish pass
- Images resized to max 2000px and recompressed (88 MB to 9 MB); four opaque PNG photos converted to JPG and references updated.
- Hero: content pinned to the page grid, stronger scrim, two-line title.
- Removed eyebrow labels that sat above headings; intro strip and dead spacing tightened.
- Contrast: --gd darkened to #735617; card category tags and scroll label enlarged.
- Replaced left/top accent bars on notices, toasts and forms with full borders.
- Themed selection, scrollbar, caret; text-wrap balance on headings.
