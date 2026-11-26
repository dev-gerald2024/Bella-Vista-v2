# Functions added or changed after v9 (v9.1 to v9.8)

Compared against `Bella-Vista-v9-recovery.zip`. Everything below exists in the current code.

## New PHP functions (8)
| Function | File | What it does | Used by |
|---|---|---|---|
| `required_downpayment($total)` | `php/payments.php` | The one downpayment formula: `min(total, max(minimum, total x %))`. | `calculate_booking_payment_summary`, `api_booking_quote` |
| `api_booking_quote($q)` | `php/portal.php` | Read-only price preview (treatment, package, total, downpayment, balance) from the same functions that save a booking. | `GET /api/quote` |
| `api_last_booking($user)` | `php/portal.php` | Returns the customer's most recent treatment, length and note to pre-fill the booking form. | `GET /api/bookings/last` |
| `portal_set_phone($user, $b)` | `php/portal.php` | Makes a number the customer's default phone (validated, formatted). | `PATCH /api/portal/phone` |
| `move_booking_resources($bk, $date, $time, ...)` | `php/portal.php` | Moves a booking's room, cleaning slot and therapist to a new time; releases a resource that is no longer free. | Admin booking edit, reschedule approval |
| `send_booking_reminders($hours)` | `php/portal.php` | Creates one in-app "Reminder:" notification per PENDING/CONFIRMED booking in the next 24 h for customers with reminders on; never twice. | `POST /api/admin/reminders/run` |
| `admin_display_name()` | `php/lib.php` | Name of the signed-in admin for log lines (replaces the wrong "Admin" in 8 places). | Admin routes and handlers |
| `offline_reset_shown()` | `php/lib.php` | True only on a local site with no email server and `OFFLINE_RESET_ON_SCREEN` on; always false in production or with SMTP. | `/api/auth/forgot` |

## New JavaScript functions
| Function | File | What it does |
|---|---|---|
| `prefillPhone()` | `js/booking.js` | Fills the saved phone number into the booking form. |
| `applyLast()` | `js/booking.js` | Fills the last treatment, length and special notes (a `?service=` choice wins). |
| `peso(n)` | `js/booking.js` | Formats an amount as pesos for the confirmation. |
| `runReminders()` | `js/db.js` | Calls the reminders endpoint. |
| (click handler) | `js/admin.js` | "Send appointment reminders" button. |
| `cleanPhone`, `isValidPhone`, `formatPhone`, `formatPhoneInput` | `js/phone.js` (new file) | Phone helpers moved out of `data.js` so every page, including account and admin, has them. |

## Existing functions changed
| Function | File | Change |
|---|---|---|
| `process_demo_payment`, `process_demo_refund`, `forfeit_demo_downpayment`, `admin_confirm_cash_payment` | `php/payments.php` | All writes in one `tx()` under the booking lock. Payment now refused while the booking is PENDING (409 `awaiting_confirmation`). |
| `calculate_booking_payment_summary` | `php/payments.php` | Uses `required_downpayment()`. |
| `parse_service_options` | `php/schedule.php` | Accepts `1,100`; ambiguous lists fall back to comma separators. |
| `portal_cancel` | `php/portal.php` | Cancels first, then refunds, in one transaction under the lock. |
| `admin_save_booking`, `admin_decide_reschedule` | `php/portal.php` | Use `move_booking_resources()` and `admin_display_name()`. |
| `api_create_booking` | `php/portal.php` | Saves the first phone number to the profile. |
| `set_status` | `php/portal.php` | The "confirmed" notification tells the customer they can now pay. |
| `send_token` | `php/lib.php` | Returns the link; mail only when mail is available. |
| `current_user`, `public_user` | `php/lib.php` | Include the saved phone. |
| `assign_appointment_room` route | `api/index.php` | Admin name taken from the signed-in admin. |
| Booking form submit | `js/booking.js` | Confirmation uses the server quote; no downpayment row; asks "Use this number from now on?" after a different number. |
| `notifications()` | `js/account.js` | Hides the on-the-fly reminder when a saved one exists; Pay button only for CONFIRMED bookings; note for PENDING. |
| Forgot password handler | `js/auth.js` | Shows the "Choose a new password" button offline. |
| Service pop-up `open()` | `js/main.js` | "Send an inquiry" opens the booking form with that treatment selected. |

## New API routes
`GET /api/quote`, `GET /api/bookings/last`, `PATCH /api/portal/phone`, `POST /api/admin/reminders/run`; `POST /api/auth/forgot` now may return `resetLink` (offline only).

## New config
`OFFLINE_RESET_ON_SCREEN` in `php/config.php`.

## New tests
`tests/logic_suite.php` (no database); added sections in `payment_suite.php` (failure injection, InnoDB, PENDING refused), `booking_suite.php` (resource move, reminders, phone, last booking, cancel order), `service_suite.php` (quote parity), `security_suite.php` (offline reset).
