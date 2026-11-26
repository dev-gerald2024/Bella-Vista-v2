# Update Notes - sync to GitHub (November 26, 2026)

This update pushes the local working copy (Bella-Vista-v2-main) on top of `origin/main` (commit 48793f2). The local copy differed from the repository in 75 tracked files (2,088 insertions, 3,713 deletions) plus new files.

## Removed
- Legacy root PHP pages: `login.php`, `register.php`, `resetpassword.php` (replaced by `login.html`, `register.html`, `reset.html` + `api/`).
- `database/db_connection.php`.
- Old PNG images replaced by JPG versions: `Bella-Vista-Oasis-Spa-Serenity.png`, `Serene-Plumeria-Spa-Massage.png`, `about-3.png`, `relax_2.png`.

## Added
- **CSS:** `css/account.css`, `css/admin.css`, `css/fonts.css`, `css/tokens.css`.
- **JS:** `js/boot.js`, `js/phone.js`.
- **Images:** `premier-lounge.jpg`, `Bella-Vista-Oasis-Spa-Retreat.png`, `Tranquil Spa Still Life with Candles and Frangipani.png`, JPG replacements listed above, and `assets/images/team/` (Meet the Team photos).
- **Database:** `database/dev-reset.sql`, `migrations/009_reconcile_legacy_schema.php`, `migrations/010_room_reference_consistency.sql`.
- **Docs:** `ADDED_FUNCTIONS.md`, `BACKUP.md`, `DATABASE.md`, `FUNCTION_ANALYSIS.md`, `OFFLINE_MODE.md`, `RELEASE_CHECKLIST.md`, `TESTING.md`.
- **Tests and tools:** `tests/` and `tools/` folders.

## Modified
- **Pages:** all HTML pages (index, about, accommodations, account, admin, contact, faqs, forgot, gallery, login, register, reset, services, verify, wellness) - new interface, split login, redesigned booking form and dashboards.
- **Front-end scripts/styles:** `js/account.js`, `admin.js`, `auth.js`, `booking.js`, `data.js`, `db.js`, `main.js`, `notify.js`; `css/style.css`.
- **Back end:** `api/index.php`, `php/config.php`, `lib.php`, `payments.php`, `portal.php`, `rooms.php`, `schedule.php`, `.htaccess`, `database/schema.sql`.
- **Docs:** `README.md`, `README-XAMPP.md`, `CHANGELOG.md`.
- **Images:** all existing photos in `assets/images` were re-saved/optimised.

## Latest functional change
- Assigned room is now static (see top entry of `CHANGELOG.md`).

Full per-version details are in `docs/CHANGELOG.md`.
