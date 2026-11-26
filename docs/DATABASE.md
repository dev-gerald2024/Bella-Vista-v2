# Database: install, upgrade, migrations

Database `bellavista` on MariaDB 10.4+ (XAMPP). Every file below is in `database/`.

| File | Safe on a database that has data? | Use it for |
|---|---|---|
| `schema.sql` | **Yes.** Only `CREATE TABLE IF NOT EXISTS`; never drops or empties anything. | New database. Importing it again changes nothing that exists. It does **not** upgrade old tables. |
| `seed.sql` | **Yes.** Inserts only what is missing; keeps values changed in the dashboard. | Starting set-up: 3 room categories, 7 rooms, 3 packages, 4 therapists (28 shift rows, 4 breaks), 5 payment settings. |
| `migrations/NNN_*` | Yes, through the runner, after a backup. | Upgrading an existing database. |
| `dev-reset.sql` | **NO. Destroys everything.** | Wiping a development database on purpose. |

## A. New installation
1. XAMPP: start Apache and MySQL.
2. phpMyAdmin > Import `schema.sql`, then select the `bellavista` database and import `seed.sql`.
3. Check: `php tools/migrate.php status` shows 001-007, 009 and 010 as *applied*.

## B. Upgrading an existing database (it already has bookings or customers)
Always **back up first** ([BACKUP.md](BACKUP.md)). Then, from the project folder (XAMPP: `C:\xampp\php\php.exe`):

```
php tools/migrate.php status
```
- **"no schema_migrations table yet"** and the database was created from the project's `schema.sql` (any Phase 1-6 copy): record it as up to date, then apply what is new.
  ```
  php tools/migrate.php baseline --backup-done
  php tools/migrate.php up --backup-done
  ```
- Created by running the old numbered migrations one by one: skip `baseline`; just `php tools/migrate.php up --backup-done`. Migration 009 rebuilds tables whose structure differed from `schema.sql` and keeps the old rows in `<table>_pre009` tables (you can drop those when you are satisfied).
- Then re-import `schema.sql` (adds `schema_migrations` if missing, changes nothing else) and `seed.sql` if the room set-up is missing.

The runner refuses to change anything without `--backup-done`, applies files in order, records each one with a checksum, skips `008` (optional manual hardening: creates a restricted database user for going live) and reports a changed file. MariaDB cannot roll back structure changes, so if a migration fails it is not recorded; fix the cause and run again (the migrations can be repeated safely), or restore your backup.

### What was found in the original migration files
Running 001-007 on an empty database did **not** produce the structure that the application uses (`content`, `sessions`, `tokens`, `therapist_leaves`, `therapist_breaks`, `room_maintenance`, `room_cleaning` and four `bookings` columns differed). Migration 009 closes that gap and was tested by building a database from 001-007 and comparing every column with `schema.sql` (only two wider legacy text columns remain, which is harmless).

## Room references (BV-013)
- `bookings.room_id` holds the room **code** (`SR-1`). It is the readable reference used by the API and the screens.
- `appointment_room_assignments.room_id` holds the numeric `rooms.id`. This table decides conflicts (treatment + 30-minute cleaning). Its old rows stay as `RELEASED` history when a room is changed.
Both are written together inside one transaction. Migration 010 adds an assignment for older active bookings that only had the code. The column was not renamed so existing code and records keep working.

## Booking writes are all-or-nothing
Creating a booking (booking row, room assignment, cleaning task, payment figures) runs in one transaction under a per-day lock. If any step fails, nothing is kept and the customer gets an error instead of a booking without a room.
