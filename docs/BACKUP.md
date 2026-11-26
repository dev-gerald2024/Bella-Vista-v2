# Backing up and restoring

Bookings, customers, payments and website content live in the `bellavista` MariaDB database. The project folder alone is not a backup.

## What to back up
| What | Why |
|---|---|
| The `bellavista` database (an `.sql` export) | Everything above. |
| `php/config.local.php` | Your settings and any database password. **Keep this copy private; do not email or upload it.** |
| `assets/` (only if you added or changed photos) | Gallery and page images. The site has no file-upload feature; images are files you copy there yourself. |
| `data/mail.log` (optional) | Development password-reset / verification links. |

## Back up
**phpMyAdmin:** open `http://localhost/phpmyadmin`, click `bellavista` > **Export** > *Custom* > make sure **triggers** and **views** are ticked > **Export**.

**Command line (tested):**
```
C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --routines --triggers bellavista > bellavista-backup.sql
```
(add `-p` if root has a password). The file contains customers' names, emails, phone numbers and password **hashes** (not passwords): store it somewhere private (USB drive, encrypted folder), never in a public or shared folder, and never put database credentials in it or next to it.

## Restore
1. phpMyAdmin > click `bellavista` > **Import** > choose the `.sql` file > **Go**. (To restore into a new empty database, create it first with collation `utf8mb4_unicode_ci`.)
2. Or: `C:\xampp\mysql\bin\mysql.exe -u root bellavista < bellavista-backup.sql`
3. **A restore replaces the tables with the backup's version.** Anything saved after the backup is gone.

## What has been tested (by the repair team, November 2026)
Command line only, MariaDB 10.11: dump with the options above, restore into a second empty database. Result: all 25 tables restored with identical row counts, the role trigger and the `userdata` view present, content and users intact, migration records included. Restoring an older dump over a live database removed rows added after the dump (so it does replace data, as stated above).

**Not tested:** the phpMyAdmin Export/Import screens, restoring on Windows/XAMPP, and the file copies (`php/config.local.php`, `assets/`). Do a trial restore into a spare database before you rely on a backup.

## Habit
Back up before every upgrade or migration (`tools/migrate.php` makes you confirm this) and at the end of each day with real bookings.
