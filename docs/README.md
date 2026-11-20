# Bella Vista Oasis Spa — System Architecture & Deployment Guide

## 1. Project Overview
**Bella Vista Oasis Spa** is a luxury spa reservation and management web application designed for seamless appointment scheduling, room and therapist assignment, customer portal management, and simulated digital checkout.

- **Stack:** PHP 8.0+ (PDO & mysqli), MariaDB 10.4+ / MySQL 8.0+, Modern Vanilla ES6 JavaScript, HTML5, CSS3.
- **Environment:** Apache / XAMPP (Windows, Linux, macOS).
- **Timezone:** `Asia/Manila` (PHT, UTC+8).
- **Operating Hours:** Monday–Sunday, 1:00 PM to 9:00 PM (13:00–21:00).
- **Payment Mode:** Local Demo Simulation (no real bank or e-wallet integration; safe for academic and demonstration purposes).

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
│   ├── account.js              # Customer portal rendering, sorts & demo payments
│   └── admin.js                # Administrator dashboard, schedules & assignments
│
├── php/
│   ├── config.php              # Default system configuration & business rules
│   ├── config.local.php        # Ignored local overrides for secrets and environment settings
│   ├── lib.php                 # Core PDO database engine, security & helpers
│   ├── schedule.php            # Real-time slot availability & cleaning buffers
│   ├── rooms.php               # Room capacity, therapist shifts & assignments
│   ├── portal.php              # Customer portal backend & 24h policies
│   └── payments.php            # Demo payment simulation, receipts & refunds
│
├── database/
│   ├── schema.sql              # Clean, authoritative DDL structure (24 tables)
│   ├── db_connection.php       # Central mysqli connection reading php/config.php
│   ├── db_connect.php          # Alias for db_connection.php
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

The database system is organized into a clean, predictable workflow:

1. **`database/schema.sql`**:
   The authoritative structure for the entire application. Contains all 24 tables, primary keys, foreign keys with referential integrity (`RESTRICT`/`CASCADE`), performance indexes, the `userdata` view, and the `trg_users_role_log` audit trigger.

2. **Local data is not included**:
   Database exports and runtime data can contain personal details, account hashes, sessions, and other private information. Create your own account after installing the schema.

3. **`database/migrations/`**:
   The chronological record of all schema evolutions from initial release through Phase 6.0. Useful for tracking feature history or migrating older installations incrementally.

### How to Install or Restore the Database
In phpMyAdmin or MySQL CLI:
```bash
mysql -u root < database/schema.sql
```
The schema creates empty tables. Register an account through the site; if it should be an administrator, promote your own account using the instructions in the XAMPP guide. Do not publish database exports or local credentials.

---

## 4. Security & Configuration Best Practices

- **Centralized Database Configuration:**
  Default settings live in `php/config.php`. Put local database credentials, SMTP settings, and test passwords in the untracked `php/config.local.php`; both the PDO engine (`php/lib.php`) and legacy mysqli connector (`database/db_connection.php`) read the combined settings.
- **Production Password Safety:**
  Argon2id and Bcrypt algorithms hash all stored passwords. Configure any local test passwords in the untracked `php/config.local.php` file; never add real credentials to version control.
- **Dedicated Database User:**
  For production deployment, run [`database/migrations/008_secure_db_user.sql`](file:///C:/xampp/htdocs/Bella-Vista-Oasis-Spa/database/migrations/008_secure_db_user.sql) to grant unprivileged data access (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) to a dedicated `bellavista_app` user rather than `root`.
- **Apache Hardening:**
  [`.htaccess`](file:///C:/xampp/htdocs/Bella-Vista-Oasis-Spa/.htaccess) prevents direct access to `.sql`, `.md`, `.env`, PHP libraries, and the `data/` folder, while enforcing modern security headers (`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and restrictive `Content-Security-Policy`).
