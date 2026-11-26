# Bella Vista Oasis Spa — System Architecture & Deployment Guide

## 1. Project Overview
**Bella Vista Oasis Spa** is a luxury spa reservation and management web application designed for seamless appointment scheduling, room and therapist assignment, customer portal management, and simulated digital checkout.

- **Stack:** PHP 8.1+ (PDO & mysqli), MariaDB 10.4+ (tested on 10.11; MySQL 8 is not supported because the migrations use MariaDB syntax), Modern Vanilla ES6 JavaScript, HTML5, CSS3.
- **Environment:** Apache / XAMPP (Windows, Linux, macOS).
- **Timezone:** `Asia/Manila` (PHT, UTC+8).
- **Operating Hours:** Monday–Sunday, 1:00 PM to 9:00 PM (13:00–21:00).
- **Payment Mode:** Local payment recording (no live bank or e-wallet integration).

---

## 2. Directory Structure

```
Bella-Vista-Oasis-Spa/
│
├── index.html                  # Public Home Page & Experience Showcase
├── about.html                  # Heritage & Spa Philosophy
├── accommodations.html         # Treatment Suites & Facility Overview
├── services.html               # Treatment Menu & Duration Options
├── wellness.html               # Holistic Wellness Rituals
├── faqs.html                   # Frequently Asked Questions & Policies
├── gallery.html                # Sanctuary Photography & Experiences
├── contact.html                # Direct Inquiries & Appointment Booking
│
├── login.html                  # Customer & Administrator Login Portal
├── register.html               # Customer Account Registration
├── forgot.html                 # Password Recovery Initiation
├── reset.html                  # Password Reset Execution
├── verify.html                 # Email Verification Landing
├── account.html                # Customer Dashboard & Reservation History
├── admin.html                  # Management Console & Resource Scheduling
│
├── api/
│   └── index.php               # Unified REST API Router for all AJAX requests
│
├── assets/                     # Brand fonts, vector icons, visual assets
├── css/                        # Core stylesheets & responsive layouts
├── js/
│   ├── notify.js               # Reusable confirmation dialogs, toasts & alerts
│   ├── auth.js                 # Session management, navigation & auth gating
│   ├── db.js                   # Client-side API abstraction layer
│   ├── main.js                 # Public page animations & header interactions
│   ├── booking.js              # Booking widget, dynamic calendar & rules
│   ├── account.js              # Customer portal rendering, sorts & payments
│   └── admin.js                # Administrator dashboard, schedules & assignments
│
├── php/
│   ├── config.php              # Default system configuration & business rules
│   ├── config.local.php        # Ignored local overrides for secrets and environment settings
│   ├── lib.php                 # Core PDO database engine, security & helpers
│   ├── schedule.php            # Real-time slot availability & cleaning buffers
│   ├── rooms.php               # Room capacity, therapist shifts & assignments
│   ├── portal.php              # Customer portal backend & 24h policies
│   └── payments.php            # Payment processing, receipts & refunds
│
├── database/
│   ├── schema.sql              # Clean, authoritative DDL structure (24 tables)
│   └── migrations/             # Chronological migration history (001–008)
│       ├── 001_initial_schema.sql
│       ├── 002_roles_and_audit.sql
│       ├── 003_double_booking_protection.sql
│       ├── 004_features_reschedule_reviews.sql
│       ├── 005_phase4.1_scheduling_and_cancellation.sql
│       ├── 006_phase5.0_rooms_packages_therapists.sql
│       ├── 007_phase6.0_demo_payments_and_refunds.sql
│       └── 008_secure_db_user.sql
│
├── docs/
│   ├── README.md               # Main system documentation (this file)
│   └── README-XAMPP.md         # Detailed XAMPP setup walkthrough
│
├── archive/
│   ├── sql-history/            # Historical standalone SQL patch scripts
│   ├── backups/                # Obsolete development backups & drafts
│   └── releases/               # Previous packaged ZIP builds
│
└── .htaccess                   # Apache URL rewrite rules & security headers
```

---

## 3. Database Source of Truth

1. **`database/schema.sql`** - the authoritative structure (24 tables + `schema_migrations`, keys, indexes, the `userdata` view and the `trg_users_role_log` trigger). It never drops anything and is safe to re-import.
2. **`database/seed.sql`** - the starting spa set-up (room categories, rooms, packages, therapists, payment settings). Idempotent: safe to run again; keeps admin changes.
3. **`database/migrations/`** - numbered upgrade steps for existing databases, applied with `php tools/migrate.php` (tracked in `schema_migrations`). See [DATABASE.md](DATABASE.md).
4. **`database/dev-reset.sql`** - deliberately destructive: deletes the whole database (development only).

Local data is not included: exports can contain personal details and password hashes. Create your own account after installing.

### How to install
1. Import `database/schema.sql`, then `database/seed.sql` (phpMyAdmin > Import, or `mysql -u root < database/schema.sql` and `mysql -u root bellavista < database/seed.sql`).
2. Register on the site; promote your own account to administrator with the XAMPP guide.

## 4. Security & Configuration Best Practices

- **Centralized Database Configuration:**
  Default settings live in `php/config.php`. Put local database credentials, SMTP settings, and test passwords in the untracked `php/config.local.php`; both the PDO engine (`php/lib.php`) reads the combined settings.
- **Production Password Safety:**
  Argon2id and Bcrypt algorithms hash all stored passwords. Configure any local test passwords in the untracked `php/config.local.php` file; never add real credentials to version control.
- **Dedicated Database User:**
  For production deployment, run [`database/migrations/008_secure_db_user.sql`](database/migrations/008_secure_db_user.sql) to grant unprivileged data access (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) to a dedicated `bellavista_app` user rather than `root`.
- **Apache Hardening:**
  [`.htaccess`](.htaccess) prevents direct access to `.sql`, `.md`, `.env`, PHP libraries, and the `data/` folder, while enforcing modern security headers (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and restrictive `Content-Security-Policy`).
