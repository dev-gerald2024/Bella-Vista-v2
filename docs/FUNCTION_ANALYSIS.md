# Bella Vista v9.1b — Function Analysis

Scope: every PHP and JavaScript function in the ZIP (static analysis with PHP tokenizer and acorn; plus a headless-browser load of all 15 pages). No database was available, so runtime behaviour of DB functions is not proven.

## Totals
- PHP: 222 functions across `php/`, `tools/`, `tests/` (200 in application code).
- JavaScript: 797 functions (about 180 named, the rest callbacks/arrow functions) across 10 files.
- Duplicate definitions in application code: 0 (the repeated `check()` / `cleanup()` are separate test scripts).
- Undefined PHP calls: 0. Unused PHP functions: 1 (`hours_text`, `php/schedule.php`, harmless).

## Defect found and fixed in this pass
**Phone helpers missing on two pages.** `js/account.js` (edit profile phone) and `js/admin.js` (booking form phone) call `formatPhoneInput()` and `isValidPhone()`, which lived in `js/data.js`. `account.html` and `admin.html` never load `data.js`, so those two actions would throw `ReferenceError`. Fix: the helpers moved to a new `js/phone.js`, loaded by all 15 pages; `tests/logic_suite.php` now checks every page that needs them loads it.

## PHP — what each module does
| File | Functions | Role |
|---|---|---|
| `php/lib.php` (68) | config (`cfg`), DB (`db`,`run`,`q1`,`qa`,`tx`,`booking_lock`), sessions/cookies, CSRF/origin, rate limiting, password hashing, validation (`is_date`,`clean_*`,`validate_24h_policy`,`validate_status_transition`), mail/SMTP, admin audit log, content overlay | Foundation. `tx()` joins an outer transaction; shutdown handler rolls back an open one when `fail()` exits. |
| `php/payments.php` (18) | settings readers, `required_downpayment`, `calculate_booking_payment_summary`, `process_demo_payment`, `process_demo_refund`, `forfeit_demo_downpayment`, `admin_confirm_cash_payment`, receipts, analytics | Money rules. All four write paths: per-booking lock + `tx()`. |
| `php/portal.php` (46) | `api_create_booking`, `api_booking_quote`, customer portal actions (cancel, reschedule, review, password…), admin booking/content actions, shapers | Customer and admin API handlers. Quote and booking use the same price functions. |
| `php/rooms.php` (32) | rooms, packages, therapists, availability, `resolve_booking_room`, `assign_appointment_room`, `release_booking_resources`, cleaning | Resource allocation. Room change requires a reason, keeps RELEASED history. |
| `php/schedule.php` (31) | opening hours, slot grid, `parse_service_options`, `service_duration_config`, `validate_service_duration`, `get_service_price`, `guard_slot`, `api_availability` | Scheduling and service/price source of truth. |
| `tools/migrate.php`, `tools/download-fonts.php` | migration runner (status/baseline/up, checksums, backup flag); offline font CSS builder | CLI tools. |

Full PHP function list per file is in the appendix.

## JavaScript — what each file does
| File | Named / total fns | Role |
|---|---|---|
| `booking.js` | 11 / 45 | Booking form: slots, room recommendations, live summary, server quote confirmation, submit. |
| `account.js` | 42 / 154 | Customer portal: bookings, payments/checkout, receipts, profile, reschedule, reviews. |
| `admin.js` | 45 / 253 | Admin dashboard: bookings, rooms, therapists, payments/refunds, content, settings. |
| `auth.js` | 24 / 76 | `Auth` client: sessions, CSRF, API calls. |
| `notify.js` | 25 / 70 | `Notice` dialogs and `Validate`. |
| `db.js` | 2 / 76 | `DB` API wrapper for portal/admin. |
| `main.js` | 17 / 82 | Site-wide behaviour, nav, FAQ, init. |
| `data.js` | 8 / 32 | `CONFIG`, `SITE`, `buildFAQ`. |
| `phone.js` (new) | 4 | Philippine phone clean/validate/format/mask. |
| `reviews.js`, `boot.js` | 3 / 9, 0 | Home reviews; adds the `js` class (CSP-safe). |

JS static findings: no undefined function calls other than cross-file globals (`initBooking`, `buildFAQ`, now `phone.js` helpers), which are all defined and loaded. All 15 pages load in headless Chromium with no JavaScript errors (load-time only; user interactions were not exercised).

## Observations (not defects, worth knowing)
1. `calculate_booking_payment_summary()` writes the cached amount/status/downpayment back to `bookings` every time it is called, including from read paths. Safe, but a "read" can update rows.
2. It falls back to a ₱800 baseline when a booking has no amount and no price can be found. The booking flow never creates such a booking, but legacy rows could display ₱800.
3. `process_demo_payment()` only enforces ownership when the user id exists in `users`; the API always passes the session user, so this is safe through the API.
4. `/api/quote` is a preview; availability is only checked when the booking is saved.
5. `hours_text()` is unused and can be deleted.

## Still not verified
DB-backed suites, concurrency, migrations 009/010, backup/restore, interactive browser flows, live HTTP security checks, owner-supplied business details. See `docs/RELEASE_CHECKLIST.md`.

## Appendix — PHP function inventory
### php/lib.php (70)
  cfg, cfg_test_override, test_passwords, prod, secure, cookie_name, is_https, base_path, request_origin, base_url, origin_of, today, db, run, q1, qa, last_id, appt_ts, tx, __construct, __destruct, booking_lock, now, ms, iso, sha, rnd, out, fail, str, is_list_arr, client_ip, jparse, is_date, is_time, clean_phone, is_phone, format_phone, clean_name, clean_text, validate_24h_policy, validate_status_transition, pw_error, norm_email, ok_email, pw_hash, pw_verify, rate_limit_dir, rate_limit, set_session_cookie, start_session, mail_enabled, mail_dev_log, admin_ok, is_admin_row, current_user, public_user, admin_log, admin_actor, purge_expired, send_token, take_token, send_mail, smtp_read, smtp_cmd, smtp_send, catalog, image_path_ok, content_check, content_overlay

### php/payments.php (18)
  get_downpayment_setting, get_min_downpayment, payment_setting_enabled, full_payment_allowed, late_forfeiture_enabled, db_payment_status, get_all_payment_settings, update_payment_setting, required_downpayment, calculate_booking_payment_summary, process_demo_payment, format_payment_method, resolve_txn_id, generate_digital_receipt, process_demo_refund, forfeit_demo_downpayment, admin_confirm_cash_payment, get_payment_analytics

### php/portal.php (46)
  pad, yr, bk_ref, cus_ref, parse_ref, shape_booking, shape_txn, shape_customer, act, notify, sync_txn, booking_created, set_status, api_create_booking, api_booking_quote, api_list_my_bookings, portal_snapshot, portal_fresh, portal_profile, portal_preferences, portal_cancel, portal_read_all, portal_favorite, portal_password, portal_hide, portal_clear, shape_resched, portal_bookings, own_booking, portal_reschedule, portal_withdraw_reschedule, portal_review, api_public_reviews, admin_decide_reschedule, admin_review_visibility, admin_snapshot, business_info_missing, security_checks, admin_done, clean_booking, admin_save_booking, admin_booking_status, admin_delete_booking, admin_restore_booking, admin_set_active, admin_put_content

### php/rooms.php (32)
  get_room_categories, get_room_category, get_packages, get_package, get_package_by_code, calculate_package_price, resolve_booking_package, booking_total_with_package, save_package, get_all_rooms, get_room_by_id, get_room_by_code, save_room, deactivate_room, calculate_room_live_status, is_room_available, get_available_rooms, get_room_recommendations, get_all_therapists, get_therapist_by_id, save_therapist, get_therapist_schedule, update_therapist_schedule, add_therapist_break, add_therapist_leave, is_therapist_available, get_available_therapists, resolve_booking_room, assign_appointment_room, assign_appointment_therapist, release_booking_resources, update_cleaning_status

### php/schedule.php (31)
  hm_to_min, min_to_hm, fmt12, cfg_int, service_cap, total_cap, slot_step, min_notice, window_days, buffer_minutes, min_guests, max_guests, spa_hours, parse_service_options, service_admin_overrides, service_is_active, service_duration_config, validate_service_duration, service_minutes, validate_guests, get_service_price, local_now_minutes, days_from_today, day_intervals, peak, slot_error, slot_fail, with_day_lock, guard_slot, api_availability, hours_text

### tools/download-fonts.php (1)
  build_local_css

### tools/migrate.php (4)
  say, die_, table_exists, split_sql
