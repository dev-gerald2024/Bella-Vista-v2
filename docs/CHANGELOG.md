# Changelog

## 2026-11-22 — Admin booking workflow rules

Rules are enforced in the admin UI (`js/admin.js`) and again on the server so they cannot be bypassed by calling the API directly.

### Room assignment is fixed to the customer's booking
- **UI (`js/admin.js`)**: In the *Assign Room & Therapist* dialog, the room dropdown is disabled and shows only the room the customer booked. Bookings with no room still allow selecting one.
- **Server (`php/rooms.php`, `assign_appointment_room`)**: returns 403 if the requested room differs from the booking's customer-selected room.

### Start button
- Blocked when the payment status is `UNPAID`; allowed for `PARTIALLY_PAID` or `PAID`.
- Blocked when no therapist is assigned to the booking.
- UI warnings: "Payment Required", "Therapist Required".
- Server (`php/lib.php`, `validate_status_transition`): rejects the `IN_PROGRESS` transition with a matching message. `php/portal.php` passes `payment_status` and `therapist_id` into this check at both status-change call sites.

### Complete button
- Blocked when the payment status is `UNPAID`; allowed for `PARTIALLY_PAID`, `PAID` or `REFUNDED`.
- Blocked before the scheduled appointment time.
- Separate warnings per scenario: "Payment Required" and "Too Early to Complete".
- Server (`php/lib.php`): same rules applied to the `COMPLETED` transition, with distinct error messages.

### Booking rows
- Removed the **Edit** button from booking rows in the admin list. Edit buttons for rooms, packages, therapists and other lists are unchanged. The server's booking-update route is not restricted.

### Files changed
| File | Change |
|---|---|
| `js/admin.js` | Room dropdown lock, Start/Complete guards, Edit button removal |
| `php/lib.php` | Payment, therapist and time checks in `validate_status_transition` |
| `php/portal.php` | Passes payment status and therapist ID to the status check |
| `php/rooms.php` | Rejects changing a customer-booked room |
