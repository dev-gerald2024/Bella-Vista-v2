# Release checklist (what still needs a real environment or the owner)

The source fixes are done. These steps cannot be proven from source and must be run before going live.
Use a **disposable copy** of the database for every test below.

1. **Database tests** (XAMPP with MariaDB and `pdo_mysql` enabled): import `schema.sql` + `seed.sql` (twice, to prove it is repeat-safe), then `php tests/run_all.php`. Expect: logic, payment (incl. failure injection + InnoDB check), booking, service (incl. quote parity), security, fonts all passed. Send any FAIL line back for a fix.
2. **Concurrency:** the payment suite starts parallel workers for double-click payments. Also try two admins refunding/confirming cash at once and two customers booking the same room and time.
3. **Migrations:** clone a Phase 1-8 database, `php tools/migrate.php status`, take a backup, `up --backup-done`, compare row counts, run again (must change nothing). Keep `_pre009` tables until signed off.
4. **Backup/restore:** follow `docs/BACKUP.md` once and open the restored site.
5. **Live HTTP/browser:** `python tests/http_security_check.py <url>` (creates test accounts, bookings and payments: disposable DB only); check `data/ratelimit` is writable; look at every page at phone and desktop width; run a full customer flow and an admin flow.
6. **Owner sign-off:** real address, phone, email, hours, map and social links (the admin dashboard lists the ones that still show sample text); service prices; package relationship (`PACKAGE_PRICING_RELATIONSHIP`); payment and cancellation policy; whether payments stay demo-only.

## Decisions recorded
- Packages are room-type rate cards linked automatically (no customer package picker).
- Admins may change a booked room with a written reason (history kept, customer notified).
- Price lists: prefer `800, 1100`; `800, 1,100` also works.
