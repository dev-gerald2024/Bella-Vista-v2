# Testing

All suites below use the **test** database (`schema.sql` + `seed.sql`). They create and remove their own "TEST-PAY" / "BKTEST" rows and put back the settings they change, but **never run them against a database with real customers**.

```
php tests/run_all.php                                   # the five PHP suites
python3 tests/http_security_check.py http://localhost/Bella-Vista-Oasis-Spa   # optional, real web server
```

| Suite | Checks | What it proves |
|---|---|---|
| `payment_suite.php` | 66 | Downpayment formula and rounding, full-payment setting, forfeiture on/off (paid, unpaid, early, late, repeated, other timezone), duplicate and invalid payments, 6 simultaneous payments, ownership, partial/full refunds, cash, simulated failure |
| `booking_suite.php` | 41 | Room existence/open/capacity/overlap/cleaning buffer/maintenance, no silent substitution, rollback when a write fails, 5 simultaneous customers, admin room change with reason and history |
| `service_suite.php` | 56 | Exact durations (70 min refused), admin-added/edited/disabled services, dashboard validation, packages (link, mismatch, inactive, 3 pricing modes) |
| `security_suite.php` | 27 | Rate limiter (including unwritable folder), source review (no legacy auth, hashing, prepared statements, cookies, CSP, blocked folders) |
| `fonts_tool_check.php` | 8 | Font tool output points at local files (logic only, no download) |
| `http_security_check.py` | 54 | Real Apache + `.htaccess`: headers, blocked paths, CSRF, login/session cookie, injection text, authorization, booking/payment ownership |

Also run when the repair was made: PHP lint on every PHP file, `node --check` on every script, local file references and duplicate ids on the 15 pages, a fresh install from `dev-reset.sql` + `schema.sql` + `seed.sql`, the upgrade paths in DATABASE.md, and a dump/restore round trip.

## Not tested (do not assume it works)
- Real browser rendering at 375 / 768 / 1280 / 1920 px (no browser was available). Only static facts are known: viewport tag on all pages, CSS breakpoints at 480-1024 px, all stylesheets parse without errors.
- Customer portal and admin dashboard screens used by a person (the changed admin forms and the booking form package line were syntax-checked and loaded without script errors, but not clicked through).
- phpMyAdmin import/export, Windows/XAMPP, PHP 8.1/8.2 (tests ran on PHP 8.3 with MariaDB 10.11 and Apache 2.4).
- Downloading the local fonts (needs internet), real SMTP, Google Maps.
- Browser console / CSP violations (the policy and the pages were checked by reading them and from the real response headers).
- A penetration test. The checks here are targeted, not a security audit.
