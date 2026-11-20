# Bella Vista Oasis Spa - running on XAMPP (PHP + MySQL)

The Node.js server has been replaced by a PHP backend. The website files and the browser code are unchanged,
and the PHP API answers the same `/api/...` routes, so login, bookings, the customer portal and the admin dashboard work as before.

## Setup (about 5 minutes)
1. **Copy the folder** `Bella-Vista-Oasis-Spa` into `C:\xampp\htdocs\` (it also works directly in the htdocs root).
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open **http://localhost/phpmyadmin** > **Import** > choose `database/schema.sql` > **Go**. This creates the empty application tables.
4. Open **http://localhost/Bella-Vista-Oasis-Spa/** and register an account.

If your MySQL root user has a password, or you use a different port, put the overrides in `php/config.local.php`.

## How ADMIN and CUSTOMER differ
| | CUSTOMER | ADMIN |
|---|---|---|
| After login goes to | My Account (`account.html`) | Admin Dashboard (`admin.html`) |
| Menu on the public pages | My Profile, My Bookings, Logout | Admin Dashboard, All Bookings, Logout |
| Can make a reservation (Book now) | Yes | No: the button opens the booking list; the server also refuses it |
| Sees | Only their own bookings, payments, notifications, favorites | All bookings, customers, payments, reports, activity log |
| Booking actions | Cancel (pending/confirmed), remove a finished booking from their own list | Confirm, Complete, No-show, Cancel, Edit, Archive |
| Customers | Edit own profile and password | View, Deactivate / Reactivate (never deleted) |
| Opening the other page | `admin.html` sends them back to My Account | `account.html` sends them to the dashboard |

The admin can change their own password under Admin Dashboard > Settings. Every admin action is written to `admin_log` and shown in Activity Log (who, what, when).

## Admin and customer accounts
Everyone who registers on the site is a **CUSTOMER**. The role is stored in the database (`users.role`), not in a config file.
To make an admin: register on the site with your Gmail, then in phpMyAdmin > `bellavista` > **SQL** run:

    UPDATE users SET role='ADMIN' WHERE email='you@gmail.com';

Log in again and an **Admin** link appears in the account menu. Every `/api/admin/*` call is re-checked on the server.
Admins cannot be deactivated from the admin API; customers can be deactivated or reactivated (`users.active`).

- **Archive, not delete:** the admin "Delete" button archives a booking (`bookings.archived = 1`). The booking and its payment record are kept.
- **Customer history:** "Clear" removes only notifications and favorites; booking, payment and activity history stay. Customers can hide a finished booking from their own view (`bookings.hidden_for_customer`).
- **Admin activity log:** every admin action is recorded in `admin_log`.
- **Fresh install:** `database/schema.sql` creates empty tables. Register an account, then use the admin instructions above if it should have the ADMIN role.
- **Already running v4 or older with real bookings?** Import `upgrade-features.sql` once (phpMyAdmin > `bellavista` > Import). It only adds the two new tables for reschedule requests and reviews and keeps all your data.

## Website content from the admin dashboard
Everything in the admin menu now changes the public site (allow up to ~20 seconds for the change to appear):

| Admin menu | Where it appears on the public site |
|---|---|
| Services | Services page, home page, footer, and the booking list (same name replaces the text, new name is added) |
| Accommodations | Accommodations page and home page |
| Wellness | Wellness page, under "Wellness experiences" |
| FAQs | FAQs page, after the built-in questions (a question worded exactly like a built-in one replaces its answer) |
| Gallery | "Gallery" section on the Home and About pages |
| Content | One named text each: Brand statement (footer), Address, Phone, Email, Opening hours, Accommodation information, Room amenities. These also feed the Contact page and the FAQ answers |

- **Gallery images:** copy the picture file into the project's `assets/images/` folder first, then enter its path in the admin (for example `assets/images/spa.jpg`). Only paths inside `assets/` ending in .jpg, .png, .webp, .gif or .avif are accepted. A path to a file that does not exist is simply not shown.
- Sections with nothing saved stay hidden, so the pages look the same as before until you add something.
- Built-in services, rooms and FAQs live in `js/data.js` and cannot be removed from the admin, only replaced or added to.

## Email (verification + password reset)
- **No SMTP (local testing):** verification is off. "Forgot password" writes the reset link to `data/mail.log`; open that file and copy the link.
- **Real email:** set `SMTP_URL` and `MAIL_FROM` in `php/config.local.php`, for example
  `smtps://you%40gmail.com:YOUR-APP-PASSWORD@smtp.gmail.com:465` (use a Gmail app password; URL-encode special characters).
  This turns on email verification (booking blocked until verified) and working password reset.
- `NOTIFY_EMAIL` optionally emails the spa when a new request arrives.

## What is where
- `api/index.php` - the API router (all `/api/...` calls land here via `.htaccess`)
- `php/lib.php` - database, sessions, validation, rate limiting, SMTP mailer
- `php/portal.php` - customer portal + admin logic
- `php/config.php` - default settings; keep local secrets in the untracked `php/config.local.php`
- `.htaccess` - routes `/api`, blocks `php/`, `data/`, `.sql`, `.md`, backups, adds security headers (XAMPP allows .htaccess by default; keep `mod_rewrite` and `mod_headers` enabled)
- `js/auth.js` - one small change: API calls now work when the site is in a subfolder

## Requirements
PHP 8.1 or newer (current XAMPP versions), with the default `pdo_mysql` extension. Passwords use Argon2id; if a PHP build lacks it, bcrypt is used automatically.

## Going live
Serve over HTTPS and set `PRODUCTION => true`, `BASE_URL`, and `TRUST_PROXY => true` if behind a proxy. Set `SMTP_URL`. Back up the database with phpMyAdmin > Export.
You can read appointment requests in phpMyAdmin (table `bookings`) or in the admin dashboard.

## Notes
- Data from the old Node/SQLite version (`data/bellavista.db`) is not migrated.
- Payment states are simulated records; no payment is processed.
- Services and rooms for validation are read from `js/data.js` (entries written as `{name:"...",cat:"...",desc:"..."}` and `{name:"...",desc:"..."}` with double quotes). Admin edits of services/rooms/FAQ content are saved to the database but the public pages still read `js/data.js`, as before.

## Audit fixes (November 9, 2026)
- **Archive keeps the numbers:** archived bookings still count in Reports, Revenue and customer totals. They are listed in **Admin Dashboard > Archived**, where **Restore** puts one back.
- **Payment history is permanent for the customer:** "Remove from my list" hides only the booking card; the customer still sees their payments.
- **Wider audit log:** failed logins on admin accounts, admin password changes and resets, and role changes made in phpMyAdmin (`UPDATE users SET role=...`) are written to `admin_log`. A booking is logged as "edited" only when something really changed.
- **Shorter admin sessions:** 12 hours for admins (customers stay 7 days).
- **Existing database?** Apply the role-audit migration if it has not already been installed. A fresh install from `database/schema.sql` includes the role-change trigger.
- **Services and accommodations edited in the admin now reach the public site.** Items you add under Services or Accommodations appear on the public pages and in the booking list (and are accepted by the booking rules). An item with the same name as a built-in one replaces its text; built-in items cannot be removed from the admin. FAQs, Content, Gallery and Wellness are still saved only in the database.
- **Setup checks:** Admin Dashboard > Settings > **Security check** lists what still needs attention (database login, production mode, HTTPS, email, time zone, login protection). With `PRODUCTION => true` the dashboard also shows a red warning while any item is open.
- **Local test passwords:** keep any test-password configuration in the untracked `php/config.local.php`; do not commit real credentials or database exports.
- **Own MySQL user instead of root:** create a limited user, then set `DB_USER` and `DB_PASS` in `php/config.local.php`. The site works with this limited user, which cannot alter tables or read other databases.
- **Login protection:** besides 8 tries per 15 minutes for each IP and email, an IP is limited to 40 login attempts per 15 minutes across all emails.
- **Time zone:** the default is now `Asia/Manila`; override it in `php/config.local.php` if you are elsewhere.

### Turning on real email (verification and password reset)
1. Use a Gmail account with 2-Step Verification, then create an **App password** (Google Account > Security > App passwords).
2. In `php/config.local.php` set `'SMTP_URL' => 'smtps://yourname%40gmail.com:YOUR-APP-PASSWORD@smtp.gmail.com:465'` and `'MAIL_FROM' => 'Bella Vista Oasis Spa <yourname@gmail.com>'`. Write `@` as `%40` and URL-encode other special characters.
3. Register a test account: the verification email must arrive. Then try "Forgot password".
Tested here against a plain SMTP server (no encryption). The encrypted Gmail connection could not be tested, so send yourself one real email before relying on it.

## Operating Hours, Cleaning Buffer, and Scheduling Rules (Phase 4.1 Update)
Every booking is checked and enforced on the **server** (the frontend form assists with dynamic availability and duration selection).

**Official Operating Hours:**
- **Monday – Sunday:** 1:00 PM to 9:00 PM (13:00 to 21:00)
- **Timezone:** `Asia/Manila` (enforced across all date/time operations)

**Strict Closing-Time & 30-Minute Cleaning Buffer Rule:**
- The spa requires a **30-minute cleaning buffer** after every treatment session (`BUFFER_MINUTES = 30`).
- **Occupied End Time = Appointment Start + Treatment Duration + 30 Minutes.**
- **Strict Rule:** Both treatment AND cleaning must finish on or before closing at 9:00 PM (`Occupied End Time <= 21:00`).
- Appointments whose cleaning period extends past 9:00 PM are strictly rejected by the server.
  - *Example (60 min treatment):* 7:00 PM (ends 8:00 PM, cleaning 8:30 PM) -> Allowed.
  - *Example (60 min treatment):* 7:30 PM (ends 8:30 PM, cleaning 9:00 PM) -> Allowed (latest possible 60 min slot).
  - *Example (60 min treatment):* 8:00 PM (ends 9:00 PM, cleaning 9:30 PM) -> **REJECTED** (cleaning exceeds 9:00 PM).
  - *Example (90 min treatment):* 7:00 PM (ends 8:30 PM, cleaning 9:00 PM) -> Allowed (latest possible 90 min slot).
- Cleaning time is strictly an internal buffer; it is included in resource and availability calculations, but is never displayed to the customer as part of their treatment.

**Treatment Duration Selection:**
- Treatment durations are configurable per service in `php/config.php` (`SERVICE_DURATIONS`); override them in `php/config.local.php`:
  - **Body Massage:** 60 min or 90 min
  - **Foot Massage:** 30 min or 45 min
  - **Body Scrub:** 45 min or 60 min
  - **Facial Treatment:** 45 min or 60 min
  - **Hair and Scalp Treatment:** 30 min or 45 min
- Unconfigured services ("Other Spa Treatments", "Not sure yet") require explicit management configuration in `SERVICE_DURATIONS` before bookings are accepted. The system rejects unconfigured services with a clear error rather than guessing a duration.

**Guest Count Support:**
- Bookings require a valid guest count (default range 1 to 6 guests).
- Validated on server and saved to `bookings.guests`. Existing records are safely backfilled with `1`.

**24-Hour Cancellation and Rescheduling Policy:**
- Customer cancellations and reschedule requests require at least **24 hours notice** prior to the original appointment date and time (`Asia/Manila` timezone).
- Late cancellations or reschedules (< 24h) requested by customers are rejected by the server.
- Authorized administrators can perform administrative overrides or late cancellations, with every override logged to `admin_log` recording administrator identity, timestamp, and reason.

**Which bookings hold a slot:** `PENDING`, `CONFIRMED`, and `IN_PROGRESS`. `CANCELLED`, `NO-SHOW`, and `REJECTED` bookings release availability.
**Double-Booking & Capacity Checks:**
- Service concurrency limit (`SERVICE_CAPACITY`, default 1) and global spa concurrency limit (`TOTAL_CAPACITY`, default 3) include the 30-minute cleaning buffer.
- Customer overlap protection (F2): A customer cannot hold overlapping bookings at the same time.
- Open bookings cap (F3): Customers are capped at a maximum of 5 active open bookings (`MAX_OPEN_BOOKINGS_PER_CUSTOMER`).
- Race condition protection: Slot availability check and reservation occur inside a per-day MySQL table/row lock (`with_day_lock`).

## SQL Upgrade & Migration Reference
The project includes several migration scripts for upgrades over time:

| Migration File | Description & Safe Usage |
|---|---|
| `upgrade-roles.sql` | Adds user roles (`ADMIN`, `CUSTOMER`) and updates `users` table. Safe for existing data. |
| `double-booking.sql` | Adds indexes on `bookings` (`date`, `time`, `service`) for scheduling lookups. |
| `audit-fixes.sql` | Adds MySQL triggers for logging role changes and administrative security. |
| `upgrade-features.sql` | Adds `reschedule_requests` and `reviews` tables for v5 features. |
| `upgrade-v5-phase4.1.sql` | **Phase 4.1 safe migration:** Adds `guests`, cancellation audit columns (`cancelled_at`, `cancelled_by`, `cancellation_reason`), reschedule audit columns (`original_date`, `original_time`, `rescheduled_at`, `rescheduled_by`), `room_id` architectural column, and expands status ENUM to include `IN_PROGRESS` and `REJECTED`. Safe and idempotent. |
| `upgrade-v5-phase5.0.sql` | **Phase 5.0 safe migration:** Adds resource management tables: `room_categories`, `rooms`, `packages`, `therapists`, `therapist_schedules`, `therapist_breaks`, `therapist_leaves`, `room_maintenance`, `room_cleaning`, `appointment_room_assignments`, `appointment_therapist_assignments`. Adds `therapist_id` and `package_id` to `bookings`. Populates documented seed suites, packages, and therapists. Completely safe and idempotent. |
| `upgrade-v5-phase6.0.sql` | **Phase 6.0 safe migration:** Adds `payment_settings` table, updates `transactions` table with references, receipt numbers, method/type ENUMs, demo flags, and adds payment tracking columns (`payment_status`, `paid_amount`, `downpayment_required`) to `bookings`. Safe and idempotent. |
| `database/schema.sql` | **Fresh installation schema:** Creates the application tables, triggers, and indexes without importing user or session data. |

## Phase 6.0: Demo Payment & Transaction Management System
> [!IMPORTANT]
> **SIMULATION ONLY — NO REAL MONEY WILL BE TRANSFERRED.**  
> The payment system is strictly a demonstration checkout experience. No real GCash, Maya, bank accounts, or financial gateways are connected. No real credentials, MPINs, OTPs, or passwords are ever requested.

### 1. Downpayment & Balance Calculation Rules
- **Configurable Downpayment:** Default is **25%** (`DOWNPAYMENT_PERCENTAGE = 25`), with a configurable minimum of **₱500.00** (`MIN_DOWNPAYMENT_AMOUNT = 500.00`).
- **Calculation Formula:**
  - `Downpayment Required = round(Total Amount * (Percentage / 100), 2)`
  - `Remaining Balance = max(0, Total Amount - Confirmed Payments)`
- **Full Payment Option:** Customers can choose to pay the required downpayment or the full 100% treatment amount in advance.
- **Strict Server Enforcement:** All calculations are performed on the server. Client-submitted payment amounts are ignored or re-validated to prevent manipulation.

### 2. Supported Demo Payment Methods
1. **GCash Demo:** Simulated mobile wallet payment with interactive processing animation.
2. **Maya Demo:** Simulated digital wallet payment with interactive processing animation.
3. **Cash at Spa:** Demo record for payment at the spa. Creates an immediate `PENDING` transaction record; funds are **NOT** credited to `paid_amount` or deducted from `remainingBalance` until an administrator explicitly confirms physical cash receipt at the front desk.
4. **Demo Bank Transfer:** Simulated bank transfer payment with interactive processing animation.

### 3. Payment Status Lifecycle
- `UNPAID`: Booking created, no confirmed payments received yet.
- `PENDING`: Awaiting confirmation (e.g. Cash at Spa selected).
- `PARTIALLY_PAID`: Confirmed downpayment received; remaining balance is due.
- `PAID`: Booking is paid in full; remaining balance is ₱0.00.
- `FAILED`: Simulated payment declined during checkout test scenario.
- `CANCELLED`: Simulated checkout abandoned by customer.
- `PARTIALLY_REFUNDED`: Admin issued partial refund; net paid decreases and balance adjusts.
- `REFUNDED`: 100% of confirmed payments refunded to customer.
- `FORFEITED`: Booking cancelled with less than 24 hours notice; downpayment forfeited per spa policy.

### 4. Admin Payment Management & Analytics
- **Prominent Disclaimer:** All administrative financial views and reports display:  
  `SIMULATED PAYMENT TOTALS — NOT ACTUAL REVENUE`
- **Cash Confirmation:** Front desk staff can click "Confirm Cash" on pending Cash-at-Spa rows to record payment and issue a digital demo receipt.
- **Refund Processing:** Administrators can process partial or full refunds up to the confirmed net payment amount. Over-refunding is strictly blocked.
- **Rule Configuration:** Downpayment percentage, minimum amount, full payment allowance, and late forfeiture rules can be updated live from Admin Dashboard > Payments > Payment Rules.

## Phase 5.0 Resource Management: Rooms, Packages & Therapists

Phase 5.0 introduces comprehensive physical resource scheduling for Bella Vista Oasis Spa, transitioning from global capacity checks to individual physical suites, spa packages, and assigned therapists.

### 1. Physical Room Inventory & Categories
The spa includes 7 individual physical treatment rooms across 3 documented categories:
- **Single Room Massage (1 guest each):** `SR-1`, `SR-2`, `SR-3` (Standard single treatment suites, documented rate: ₱800)
- **Couples Suite (2 guests each):** `CS-1`, `CS-2` (Dual massage suites, documented rate: ₱1,800)
- **Family Spa Lounge (up to 4 guests, maximum 6):** `FL-1`, `FL-2` (VIP group lounges, documented rate: ₱4,000 base + ₱750 per extra guest)

**Room States & Real-Time Availability:**
- **Physical Operational Status:** `AVAILABLE`, `MAINTENANCE`, `INACTIVE`.
- **Dynamic Live State:** Calculated in real-time from active appointment assignments, maintenance downtime, and post-treatment cleaning blocks:
  - `OCCUPIED`: Currently in a treatment session.
  - `CLEANING`: In mandatory 30-minute post-treatment cleaning buffer.
  - `RESERVED`: Reserved for an upcoming confirmed appointment today.
  - `MAINTENANCE`: Scheduled downtime / maintenance period.
  - `AVAILABLE`: Open and ready for appointments.

### 2. Documented Spa Packages & Independent Pricing
Documented room/package packages are configured in `packages`:
- `PKG-SR`: Single Room Massage (60 min, min 1 guest, standard 1, max 1, base rate ₱800)
- `PKG-CS`: Couples Suite (60 min, min 2 guests, standard 2, max 2, base rate ₱1,800)
- `PKG-FL`: Family Spa Lounge (90 min, min 1 guest, standard 4, max 6, base rate ₱4,000 + ₱750/extra guest)

**Pricing Relationship Configuration:**
- In `php/config.php` or `php/config.local.php`: `'PACKAGE_PRICING_RELATIONSHIP' => 'INDEPENDENT'`.
- Package and room rates are configured independently from service treatment prices. Package prices are never silently added or double-counted into treatment amounts.

### 3. Therapist Management & Shift Scheduling
Therapists are tracked with employee codes, contact info, specializations, employment status, and shift schedules:
- Documented therapists: `TH-001` (Ana Cruz), `TH-002` (Mark David), `TH-003` (Elena Bautista), `TH-004` (Grace Lim).
- Working hours are strictly bound to official operating hours: **1:00 PM – 9:00 PM (Asia/Manila)**.
- Full support for shift days, daily meal breaks (default 17:00–17:30), and approved leaves.

### 4. Appointment Assignments & Cleaning Buffer Workflow
- Appointments can be assigned to an eligible room and therapist from the Admin Dashboard.
- When an appointment is scheduled or completed in a room, an automatic **30-minute cleaning buffer** block is created in `room_cleaning` (`[treatment_end, treatment_end + 30m]`).
- Early completion of cleaning logs the task as completed, but does **not** shorten the mandatory 30-minute buffer for room availability checks.
- Cancelling, rejecting, or marking an appointment as no-show automatically releases assigned room and therapist resources (`RELEASED`).
- Rescheduling an appointment dynamically revalidates room and therapist availability for the new time slot.

### 5. Phase 5.0 API Routes Reference
- `GET /api/packages` - Public catalog of active spa packages and categories.
- `GET /api/rooms/available` - Filter rooms by date, time, duration, and guest count.
- `GET /api/therapists/available` - Filter therapists by date, time, duration, and specialization.
- `GET /api/admin/rooms`, `POST /api/admin/rooms` - Admin room CRUD.
- `POST /api/admin/rooms/{id}/deactivate` - Deactivate room (preserves history).
- `POST /api/admin/rooms/{id}/maintenance` - Schedule room maintenance downtime.
- `GET /api/admin/packages`, `POST /api/admin/packages` - Admin package CRUD.
- `GET /api/admin/therapists`, `POST /api/admin/therapists` - Admin therapist CRUD.
- `GET /api/admin/therapists/{id}/schedule`, `PUT /api/admin/therapists/{id}/schedule` - Manage weekly shifts.
- `POST /api/admin/therapists/{id}/breaks` - Add break period.
- `POST /api/admin/therapists/{id}/leaves` - Record therapist leave.
- `POST /api/admin/bookings/{id}/assign-room` - Assign eligible room to booking.
- `POST /api/admin/bookings/{id}/assign-therapist` - Assign qualified therapist to booking.
- `POST /api/admin/cleanings/{id}/status` - Update cleaning task status and notes.

## Customer features: reschedule, printable confirmation, reviews (v5)
- **Request reschedule** (My Account > My Bookings): a customer picks a new date and one of the free times. The booking keeps its current time until the admin answers. Rules: only Pending/Confirmed bookings that have not passed, one open request per booking, at most 3 requests per booking, same opening-hours and double-booking checks as a new booking. The customer can withdraw an open request.
- **Admin: Bookings > Reschedules.** Approve moves the booking (after checking the new slot again at that moment) and notifies the customer. Decline lets you add a note the customer sees. A request is closed automatically if the booking is cancelled or completed.
- **Print confirmation:** a button on Confirmed and Completed bookings opens the browser print dialog (or Save as PDF) with a clean one-page confirmation: spa name, address/phone from the Content blocks, reference number, service, date, time, price.
- **Reviews:** after a booking is Completed the customer can leave 1 to 5 stars and an optional comment, once per booking. Visible reviews appear in "Guest reviews" on the home page with first name + last initial only (the section stays hidden until there is at least one). Admin > Business > Reviews lets you hide or show a review; reviews are never deleted.
- Database: tables `reschedule_requests` and `reviews` (in `database/schema.sql`, or add them to an existing database with the applicable migration).
