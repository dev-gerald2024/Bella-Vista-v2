<?php
/* Bella Vista Oasis Spa - customer portal + admin dashboard logic (port of server/portal.js).
   Every customer function takes the user from the SESSION. Every admin function is behind the isAdmin check in api/index.php. */

require_once __DIR__ . '/rooms.php';
require_once __DIR__ . '/payments.php';

const STATUSES = ['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO-SHOW', 'REJECTED'];
const PREFS = ['bookingReminders' => true, 'emailNotifications' => true, 'promotions' => false];
const CONTENT_KEYS = ['services', 'rooms', 'wellness', 'faqs', 'content', 'gallery', 'log'];
const REASONS = ['Schedule conflict', 'Change of plans', 'Found another time', 'Other'];
const CONTACTS = ['', 'Email', 'Phone call', 'SMS'];
const TXQ = 'SELECT t.*,b.created_at AS bcreated FROM transactions t JOIN bookings b ON b.id=t.booking_id';

function pad($n): string { return str_pad((string)$n, 4, '0', STR_PAD_LEFT); }
function yr(array $r): string { return substr((string)$r['created_at'], 0, 4); }
function bk_ref(array $r): string { return 'BK-' . yr($r) . '-' . pad($r['id']); }
function cus_ref($id): string { return 'CUS-' . pad($id); }
function parse_ref($s, string $p): ?int { return preg_match('/^' . $p . '-(?:\d{4}-)?(\d{1,9})$/D', str($s), $m) ? (int)$m[1] : null; }

/* ----- shapes sent to the browser (identical to the Node API) ----- */
function shape_booking(array $r): array {
  $cEnd = null;
  if (!empty($r['time']) && !empty($r['duration']) && is_numeric($r['duration'])) {
    $cEnd = min_to_hm(hm_to_min($r['time']) + (int)$r['duration'] + buffer_minutes());
  }
  return [
    'id'                 => bk_ref($r),
    'customerId'         => $r['user_id'] ? cus_ref($r['user_id']) : '',
    'name'               => $r['name'],
    'email'              => $r['email'],
    'phone'              => $r['phone'],
    'serviceName'        => $r['service'],
    'date'               => $r['preferred_date'],
    'time'               => $r['time'],
    'duration'           => $r['duration'],
    'guests'             => (int)($r['guests'] ?? 1),
    'amount'             => (float)$r['amount'],
    'status'             => $r['status'],
    'message'            => (string)$r['message'],
    'cancelReason'       => $r['cancellation_reason'] ?: $r['cancel_reason'],
    'cancelledAt'        => !empty($r['cancelled_at']) ? iso($r['cancelled_at']) : null,
    'cancelledBy'        => $r['cancelled_by'] ?? null,
    'originalDate'       => $r['original_date'] ?? null,
    'originalTime'       => $r['original_time'] ?? null,
    'rescheduledAt'      => !empty($r['rescheduled_at']) ? iso($r['rescheduled_at']) : null,
    'rescheduledBy'      => $r['rescheduled_by'] ?? null,
    'roomId'             => $r['room_id'] ?? null,
    'roomName'           => $r['room_name'] ?? ($r['room_id'] ?? null),
    'therapistId'        => !empty($r['therapist_id']) ? (int)$r['therapist_id'] : null,
    'therapistName'      => $r['therapist_name'] ?? null,
    'packageId'          => !empty($r['package_id']) ? (int)$r['package_id'] : null,
    'paymentStatus'      => $r['payment_status'] ?? 'UNPAID',
    'paidAmount'         => (float)($r['paid_amount'] ?? 0.0),
    'downpaymentRequired'=> (float)($r['downpayment_required'] ?? 0.0),
    'remainingBalance'   => in_array($r['status'], ['CANCELLED', 'REJECTED'], true) || in_array($r['payment_status'] ?? '', ['REFUNDED', 'FORFEITED'], true) ? 0.0 : max(0.0, (float)$r['amount'] - (float)($r['paid_amount'] ?? 0.0)),
    'rawId'              => (int)$r['id'],
    'numericId'          => (int)$r['id'],
    'cleaningEnd'        => $cEnd,
    'archived'           => (bool)$r['archived'],
    'hiddenForCustomer'  => (bool)($r['hidden_for_customer'] ?? 0),
    'createdAt'          => iso($r['created_at']),
    'updatedAt'          => iso($r['updated_at'])
  ];
}
function shape_txn(array $r): array {
  return [
    'id'                 => 'TXN-' . yr($r) . '-' . pad($r['id']),
    'numericId'          => (int)$r['id'],
    'rawId'              => (int)$r['id'],
    'transactionRef'     => $r['transaction_reference'] ?? ('TXN-' . yr($r) . '-' . pad($r['id'])),
    'transaction_reference' => $r['transaction_reference'] ?? ('TXN-' . yr($r) . '-' . pad($r['id'])),
    'customerId'         => $r['user_id'] ? cus_ref($r['user_id']) : '',
    'bookingId'          => 'BK-' . substr((string)($r['bcreated'] ?? $r['created_at']), 0, 4) . '-' . pad($r['booking_id']),
    'rawBookingId'       => (int)$r['booking_id'],
    'description'        => $r['description'],
    'amount'             => (float)$r['amount'],
    'paymentMethod'      => $r['payment_method'] ?? 'CASH_AT_SPA',
    'payment_method'     => $r['payment_method'] ?? 'CASH_AT_SPA',
    'paymentMethodLabel' => format_payment_method($r['payment_method'] ?? 'CASH_AT_SPA'),
    'transactionType'    => $r['transaction_type'] ?? 'DOWNPAYMENT',
    'receiptNumber'      => $r['receipt_number'] ?? null,
    'receipt_number'     => $r['receipt_number'] ?? null,
    'demoMode'           => true,
    'notes'              => $r['notes'] ?? '',
    'refundReason'       => $r['refund_reason'] ?? '',
    'processedAt'        => !empty($r['processed_at']) ? iso($r['processed_at']) : null,
    'status'             => $r['status'],
    'createdAt'          => iso($r['created_at'])
  ];
}
function shape_customer(array $u): array {
  return ['id' => cus_ref($u['id']), 'fullName' => $u['full_name'], 'email' => $u['email'], 'phone' => $u['phone'], 'birthday' => $u['birthday'], 'contact' => $u['contact'],
    'role' => $u['role'], 'active' => (bool)$u['active'], 'createdAt' => iso($u['created_at']), 'preferences' => array_merge(PREFS, jparse($u['prefs'], []))];
}

/* ----- shared actions (used by customers and by the admin) ----- */
function act($uid, string $text): void { if ($uid) run('INSERT INTO activity(user_id,text,created_at) VALUES(?,?,?)', [$uid, $text, now()]); }
function notify($uid, string $text): void { if ($uid) run('INSERT INTO notifications(user_id,text,is_read,created_at) VALUES(?,?,0,?)', [$uid, $text, now()]); }
function sync_txn(array $b): void {
  // Phase 6: Recalculate authoritative server-side payment summary and status
  calculate_booking_payment_summary((int)$b['id']);
}
function booking_created(array $b): void {
  act($b['user_id'], 'Reservation created: ' . $b['service']);
  notify($b['user_id'], 'Your reservation request was received. The spa will confirm it.');
  sync_txn($b);
}
function set_status(int $id, string $status, string $reason = '', ?string $by = null): array {
  $params = [$status, $reason, $reason, $reason, $reason];
  $sql = "UPDATE bookings SET status=?, cancel_reason=CASE WHEN ?<>'' THEN ? ELSE cancel_reason END, cancellation_reason=CASE WHEN ?<>'' THEN ? ELSE cancellation_reason END";
  if ($status === 'CANCELLED') {
    $sql .= ", cancelled_at=?, cancelled_by=?";
    $params[] = now();
    $params[] = $by ?: 'SYSTEM';
  }
  $sql .= " WHERE id=?";
  $params[] = $id;
  run($sql, $params);
  $b = q1('SELECT * FROM bookings WHERE id=?', [$id]);
  $m = [
    'CONFIRMED'   => ['Reservation confirmed', 'Your reservation has been confirmed.'],
    'IN_PROGRESS' => ['Treatment in progress', 'Your treatment session is now in progress.'],
    'COMPLETED'   => ['Booking completed', 'Thank you for visiting. Your booking is completed.'],
    'CANCELLED'   => ['Reservation cancelled', 'Your reservation was cancelled.'],
    'REJECTED'    => ['Reservation declined', 'Your reservation request was declined.'],
    'NO-SHOW'     => ['Marked as no-show', 'Your reservation was marked as a no-show.']
  ][$status] ?? null;
  if ($m && !empty($b['user_id'])) { act($b['user_id'], $m[0] . ': ' . $b['service']); notify($b['user_id'], $m[1]); }
  if (!in_array($status, ['PENDING', 'CONFIRMED', 'IN_PROGRESS'], true)) {
    run("UPDATE reschedule_requests SET status='WITHDRAWN',decided_at=? WHERE booking_id=? AND status='PENDING'", [now(), $id]);
    release_booking_resources($id, $by ?: 'SYSTEM');
    run("UPDATE transactions SET status='CANCELLED' WHERE booking_id=? AND status='PENDING'", [$id]);

    // Phase 6: Late cancellation check for downpayment forfeiture (< 24 hours notice)
    if ($status === 'CANCELLED' && !empty($b['preferred_date']) && !empty($b['time'])) {
      $apptT = strtotime($b['preferred_date'] . ' ' . $b['time']);
      $diffH = ($apptT - time()) / 3600.0;
      if ($diffH < 24.0) {
        forfeit_demo_downpayment($id, 'Late cancellation (< 24h notice)', $by ?: 'SYSTEM');
      }
    }
  }
  sync_txn($b);
  return $b;
}

/* ======================= public booking API ======================= */
function api_create_booking(array $user, array $b): void {
  if (mail_enabled() && !$user['emailVerified']) fail('Please verify your email address before making a reservation.', 403, ['code' => 'verify_required']);
  $service = !empty($b['service']) ? clean_name($b['service']) : 'Not sure yet';
  $date = str($b['date'] ?? '');
  $time = str($b['time'] ?? '');
  $phone = trim(str($b['phone'] ?? ''));
  $msg = clean_text($b['message'] ?? '', 1000);

  // Validate service duration (Part 4: do not invent duration for 'Not sure yet' or unconfigured services)
  $vd = validate_service_duration($service, $b['duration'] ?? null, true);
  if (!$vd['ok']) {
    fail($vd['error'], 400, ['field' => $vd['field'], 'code' => $vd['code']]);
  }
  $dur = (string)$vd['minutes'];

  // Validate guest count (Part 6)
  $vg = validate_guests($b['guests'] ?? 1);
  if (!$vg['ok']) {
    fail($vg['error'], 400, ['field' => $vg['field'], 'code' => $vg['code']]);
  }
  $guests = $vg['guests'];

  if (!is_date($date) || $date < today()) fail('Choose a date from today onward.', 400, ['field' => 'date']);
  if (!is_phone($phone)) fail('Phone number must contain 11 digits (09…) or 12 digits (639…).', 400, ['field' => 'phone']);
  $phone = format_phone($phone);
  if ($time === '') fail('Choose a time for your appointment.', 400, ['field' => 'time']);
  if (!is_time($time)) fail('Choose an available time.', 400, ['field' => 'time']);

  // BR-12: Acceptance of Terms and Conditions
  if (empty($b['agreeTerms'])) {
    fail('You must review and accept the Terms and Conditions before requesting an appointment.', 400, ['field' => 'agreeTerms', 'code' => 'terms_required']);
  }

  // BR-13, T&C 03, 17, 20: Completion and confirmation of Health Declaration
  if (empty($b['agreeHealth'])) {
    fail('Please confirm your health declaration before submitting your booking.', 400, ['field' => 'agreeHealth', 'code' => 'health_declaration_required']);
  }

  // BR-11, BR-50: Calculate official booking amount from service, duration, and guests
  $amount = get_service_price($service, (int)$dur, $guests);

  // Protection against duplicate booking submissions
  $dup = q1("SELECT id FROM bookings WHERE user_id=? AND service=? AND preferred_date=? AND time=? AND created_at >= (UTC_TIMESTAMP() - INTERVAL 60 SECOND)",
            [$user['id'], $service, $date, $time]);
  if ($dup) {
    fail('A reservation request for this service and time was already submitted. Please check your bookings.', 409, ['code' => 'duplicate_booking']);
  }

  $t = now();
  /* opening hours, slot grid, buffer, and double-booking are checked and the row is saved under one lock */
  $row = guard_slot($date, $time, $service, $dur, null, true, function () use ($user, $service, $date, $time, $dur, $guests, $amount, $phone, $msg, $t, $b) {
    $uName = $user['fullName'] ?? $user['full_name'] ?? 'Guest';

    // Resolve room selection or dynamic recommendation
    $reqRoom = trim((string)($b['selectedRoomCode'] ?? $b['roomCode'] ?? $b['selectedRoomId'] ?? $b['roomId'] ?? $b['accommodation'] ?? 'auto'));
    $assignedRoomCode = null;
    $assignedRoomId = null;

    if ($reqRoom !== '' && $reqRoom !== 'auto') {
      $rm = is_numeric($reqRoom) ? get_room_by_id((int)$reqRoom) : get_room_by_code($reqRoom);
      if (!$rm) {
        $catCode = ($reqRoom === 'Single Room Massage' || $reqRoom === 'SINGLE') ? 'SINGLE' : (($reqRoom === 'Couples Suite' || $reqRoom === 'COUPLES') ? 'COUPLES' : (($reqRoom === 'Family Spa Lounge' || $reqRoom === 'FAMILY') ? 'FAMILY' : ''));
        if ($catCode) {
          $candidates = qa("SELECT * FROM rooms WHERE category_code=? AND room_status='AVAILABLE'", [$catCode]);
          foreach ($candidates as $cand) {
            $chk = is_room_available((int)$cand['id'], $date, $time, (int)$dur);
            if ($chk['ok']) { $rm = $cand; break; }
          }
          if (!$rm && !empty($candidates)) $rm = $candidates[0];
        }
      }
      if ($rm) {
        $assignedRoomCode = $rm['room_code'];
        $assignedRoomId = (int)$rm['id'];
      }
    }

    if (!$assignedRoomId) {
      $rec = get_room_recommendations($service, (int)$dur, (int)$guests, $date, $time);
      if ($rec['ok'] && !empty($rec['recommended'])) {
        $assignedRoomCode = $rec['recommended']['roomCode'];
        $assignedRoomId = (int)$rec['recommended']['id'];
      }
    }

    run('INSERT INTO bookings(user_id,name,email,service,preferred_date,time,duration,guests,amount,phone,message,status,room_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$user['id'], $uName, $user['email'], $service, $date, $time, $dur, $guests, $amount, $phone, $msg, 'PENDING', $assignedRoomCode, $t, $t]);
    $newBk = q1('SELECT * FROM bookings WHERE id=?', [last_id()]);

    if ($newBk && $assignedRoomId) {
      try {
        $sMin = hm_to_min($time);
        $eMin = $sMin + (int)$dur;
        $cMin = $eMin + buffer_minutes();
        $endTime = min_to_hm($eMin);
        $cleanEndTime = min_to_hm($cMin);

        run('INSERT INTO appointment_room_assignments (booking_id, room_id, assigned_date, start_time, end_time, cleaning_end_time, status, assigned_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$newBk['id'], $assignedRoomId, $date, $time, $endTime, $cleanEndTime, 'ASSIGNED', 'Online Booking']);
        run('INSERT INTO room_cleaning (room_id, booking_id, cleaning_date, start_time, end_time, status, cleaned_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$assignedRoomId, $newBk['id'], $date, $endTime, $cleanEndTime, 'SCHEDULED', '', 'Automated post-treatment sanitization (30 min buffer)']);
      } catch (Throwable $ignore) {}
    }

    return $newBk;
  }, (int)$user['id']);

  $ref = bk_ref($row);
  booking_created($row);
  $to = (string)cfg('NOTIFY_EMAIL');
  if ($to !== '' && (mail_enabled() || mail_dev_log())) {
    try { send_mail($to, 'New appointment request ' . $ref, "{$user['fullName']} <{$user['email']}>\nPhone: $phone\nService: $service\nDuration: {$dur} min\nGuests: $guests\nAmount: ₱" . number_format($amount, 2) . "\nPreferred date: $date $time\n\n$msg"); }
    catch (Throwable $e) { error_log('notify error: ' . $e->getMessage()); }
  }
  out(['ok' => true, 'booking' => ['id' => $ref, 'service' => $service, 'date' => $date, 'time' => $time, 'duration' => $dur, 'guests' => $guests, 'amount' => $amount, 'roomId' => $row['room_id'] ?? null, 'status' => 'PENDING']], 201);
}
function api_list_my_bookings(array $user): void {
  $rows = qa('SELECT id,service,preferred_date AS date,time,status,created_at FROM bookings WHERE user_id=? ORDER BY id DESC', [$user['id']]);
  out(['bookings' => array_map(fn($r) => ['id' => (int)$r['id'], 'service' => $r['service'], 'date' => $r['date'], 'time' => $r['time'], 'status' => $r['status'], 'createdAt' => iso($r['created_at'])], $rows)]);
}

/* ======================= customer portal ======================= */
function portal_snapshot(int $uid): array {
  $u = q1('SELECT * FROM users WHERE id=?', [$uid]); $cid = cus_ref($uid);
  return ['portal' => [
    'customer' => shape_customer($u),
    'bookings' => portal_bookings($uid),
    'txns' => array_map('shape_txn', qa(TXQ . ' WHERE t.user_id=? ORDER BY t.id DESC', [$uid])),
    'notifs' => array_map(fn($n) => ['id' => (int)$n['id'], 'customerId' => $cid, 'text' => $n['text'], 'read' => (bool)$n['is_read'], 'at' => iso($n['created_at'])],
                          qa('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$uid])),
    'activity' => array_map(fn($a) => ['customerId' => $cid, 'text' => $a['text'], 'at' => iso($a['created_at'])],
                            qa('SELECT * FROM activity WHERE user_id=? ORDER BY id DESC LIMIT 100', [$uid])),
    'favs' => array_column(qa('SELECT item_key FROM favorites WHERE user_id=?', [$uid]), 'item_key'),
    'catalog' => catalog()]];
}
function portal_fresh(array $user, array $extra = []): void { out(['ok' => true] + portal_snapshot($user['id']) + $extra); }

function portal_profile(array $user, array $b): void {
  $name = clean_name($b['fullName'] ?? ''); $phone = trim(str($b['phone'] ?? '')); $bday = str($b['birthday'] ?? ''); $contact = str($b['contact'] ?? '');
  if ($name === '') fail('Enter your full name.', 400, ['field' => 'fullName']);
  if (mb_strlen($name) > 100) fail('Name is too long.', 400, ['field' => 'fullName']);
  if ($phone !== '' && !is_phone($phone)) fail('Phone number must contain 11 digits (09…) or 12 digits (639…).', 400, ['field' => 'phone']);
  if ($phone !== '') $phone = format_phone($phone);
  if ($bday !== '' && (!is_date($bday) || $bday > today())) fail('Enter a valid birthday.', 400, ['field' => 'birthday']);
  if (!in_array($contact, CONTACTS, true)) fail('Choose a valid contact method.', 400, ['field' => 'contact']);
  run('UPDATE users SET full_name=?,phone=?,birthday=?,contact=? WHERE id=?', [$name, $phone, $bday, $contact, $user['id']]);
  act($user['id'], 'Profile updated');
  portal_fresh($user);
}
function portal_preferences(array $user, array $b): void {
  $p = []; foreach (PREFS as $k => $d) $p[$k] = is_bool($b[$k] ?? null) ? $b[$k] : $d;
  run('UPDATE users SET prefs=? WHERE id=?', [json_encode($p), $user['id']]);
  portal_fresh($user);
}
function portal_cancel(array $user, string $ref, array $b): void {
  $id = parse_ref($ref, 'BK');
  $row = $id ? q1('SELECT * FROM bookings WHERE id=? AND user_id=?', [$id, $user['id']]) : null;   // ownership enforced here
  if (!$row) fail('Booking not found.', 404);
  if (!in_array($row['status'], ['PENDING', 'CONFIRMED'], true)) {
    fail('Only pending or confirmed appointments can be cancelled online.', 400);
  }

  // Part 7: 24-hour customer cancellation policy
  $pol = validate_24h_policy($row['preferred_date'], $row['time']);
  if (!$pol['ok']) {
    fail($pol['error'], 400, ['code' => 'cancellation_cutoff_passed', 'policy' => '24h_cancellation']);
  }

  $r = clean_text($b['reason'] ?? 'Customer cancelled online', 300);

  // Check if booking has confirmed payments to automatically process full refund to original payment method
  $summary = calculate_booking_payment_summary((int)$row['id']);
  $refundResult = null;
  if ($summary['netPaid'] > 0) {
    $origTxn = q1("SELECT payment_method FROM transactions WHERE booking_id = ? AND status = 'PAID' AND transaction_type IN ('DOWNPAYMENT', 'BALANCE_PAYMENT', 'FULL_PAYMENT') ORDER BY id DESC LIMIT 1", [(int)$row['id']]);
    $refundMethod = $origTxn['payment_method'] ?? 'GCASH_DEMO';
    $refundResult = process_demo_refund((int)$row['id'], (float)$summary['netPaid'], 'Customer cancellation refund: ' . $r, 'CUSTOMER', $refundMethod);
  }

  set_status((int)$row['id'], 'CANCELLED', $r, 'CUSTOMER');
  portal_fresh($user, $refundResult ? ['refund' => $refundResult] : []);
}
function portal_read_all(array $user): void { run('UPDATE notifications SET is_read=1 WHERE user_id=?', [$user['id']]); portal_fresh($user); }
function portal_favorite(array $user, array $b): void {
  $k = str($b['key'] ?? ''); $c = catalog(); $ok = false;
  foreach ($c['services'] as $s) if ('s:' . $s['name'] === $k) $ok = true;
  foreach ($c['rooms'] as $r) if ('r:' . $r['name'] === $k) $ok = true;
  if (!$ok) fail('Unknown item.');
  if (q1('SELECT 1 AS x FROM favorites WHERE user_id=? AND item_key=?', [$user['id'], $k])) run('DELETE FROM favorites WHERE user_id=? AND item_key=?', [$user['id'], $k]);
  else run('INSERT INTO favorites(user_id,item_key) VALUES(?,?)', [$user['id'], $k]);
  portal_fresh($user);
}
function portal_password(array $user, array $b): void {
  $u = q1('SELECT * FROM users WHERE id=?', [$user['id']]);
  $cur = $b['current'] ?? null;
  $good = is_string($cur) && mb_strlen($cur) <= MAX_PW && pw_verify($cur, $u['password_hash']);
  if (!$good) fail('Current password is incorrect.', 400, ['field' => 'current']);
  $pe = pw_error($b['next'] ?? null); if ($pe) fail($pe, 400, ['field' => 'next']);
  if (($b['confirm'] ?? null) !== $b['next']) fail('Passwords do not match.', 400, ['field' => 'confirm']);
  $keep = $_COOKIE[cookie_name()] ?? '';
  run('UPDATE users SET password_hash=? WHERE id=?', [pw_hash($b['next']), $user['id']]);
  run('DELETE FROM sessions WHERE user_id=? AND token_hash<>?', [$user['id'], is_string($keep) && $keep !== '' ? sha($keep) : '']);   // other devices are signed out
  act($user['id'], 'Password changed');
  if (!empty($user['isAdmin'])) admin_log($user['id'], 'Password changed');
  out(['ok' => true]);
}
function portal_hide(array $user, string $ref): void {   // hides a finished booking from the customer's own view; the record stays for the spa
  $id = parse_ref($ref, 'BK');
  $row = $id ? q1('SELECT * FROM bookings WHERE id=? AND user_id=?', [$id, $user['id']]) : null;   // ownership enforced here
  if (!$row) fail('Booking not found.', 404);
  if (!in_array($row['status'], ['COMPLETED', 'CANCELLED', 'NO-SHOW'], true)) fail('Only completed or cancelled bookings can be hidden.');
  run('UPDATE bookings SET hidden_for_customer=1 WHERE id=?', [$id]);
  act($user['id'], 'Hid booking from view: ' . $row['service']);
  portal_fresh($user);
}
function portal_clear(array $user): void {   // removes only notifications and favorites; booking, payment and activity history stay
  foreach (['notifications', 'favorites'] as $t) run("DELETE FROM $t WHERE user_id=?", [$user['id']]);
  portal_fresh($user);
}

/* ======================= reschedule requests and reviews (customer side) ======================= */
function shape_resched(array $r): array {
  return [
    'id'           => (int)$r['id'],
    'date'         => $r['new_date'],
    'time'         => $r['new_time'],
    'originalDate' => $r['original_date'] ?? null,
    'originalTime' => $r['original_time'] ?? null,
    'reason'       => $r['reason'],
    'status'       => $r['status'],
    'adminNote'    => $r['admin_note'],
    'decidedBy'    => $r['decided_by'] ?? null,
    'decidedAt'    => !empty($r['decided_at']) ? iso($r['decided_at']) : null,
    'createdAt'    => iso($r['created_at'])
  ];
}
function portal_bookings(int $uid): array {
  $rows = qa('SELECT * FROM bookings WHERE user_id=? AND hidden_for_customer=0 ORDER BY preferred_date DESC,time DESC,id DESC', [$uid]);
  $rs = []; foreach (qa('SELECT * FROM reschedule_requests WHERE user_id=? ORDER BY id', [$uid]) as $r) $rs[(int)$r['booking_id']] = $r;   // the newest request per booking wins
  $rv = []; foreach (qa('SELECT * FROM reviews WHERE user_id=?', [$uid]) as $r) $rv[(int)$r['booking_id']] = $r;

  // Aggregate transaction history per booking
  $txRows = qa("SELECT booking_id, transaction_type, status, amount FROM transactions WHERE user_id=? AND status='PAID'", [$uid]);
  $txMap = [];
  foreach ($txRows as $tx) {
    $bid = (int)$tx['booking_id'];
    if (!isset($txMap[$bid])) {
      $txMap[$bid] = ['paid' => 0.0, 'refunded' => 0.0];
    }
    $amt = (float)$tx['amount'];
    if ($tx['transaction_type'] === 'REFUND') {
      $txMap[$bid]['refunded'] += $amt;
    } else {
      $txMap[$bid]['paid'] += $amt;
    }
  }

  return array_map(function ($b) use ($rs, $rv, $txMap) {
    $o = shape_booking($b); $id = (int)$b['id']; $v = $rv[$id] ?? null;
    $o['reschedule'] = isset($rs[$id]) ? shape_resched($rs[$id]) : null;
    $o['review'] = $v ? ['rating' => (int)$v['rating'], 'comment' => $v['comment'], 'createdAt' => iso($v['created_at'])] : null;
    $txInfo = $txMap[$id] ?? ['paid' => (float)($b['paid_amount'] ?? 0.0), 'refunded' => 0.0];
    $o['totalPaid'] = (float)$txInfo['paid'];
    $o['totalRefunded'] = (float)$txInfo['refunded'];
    $o['netPaid'] = max(0.0, round($txInfo['paid'] - $txInfo['refunded'], 2));
    if (in_array($b['status'], ['CANCELLED', 'REJECTED'], true) || in_array($b['payment_status'] ?? '', ['REFUNDED', 'FORFEITED'], true)) {
      $o['remainingBalance'] = 0.0;
      $o['outstandingAmount'] = 0.0;
      $o['isPayable'] = false;
    } else {
      $o['remainingBalance'] = max(0.0, (float)$b['amount'] - $o['netPaid']);
      $o['outstandingAmount'] = $o['remainingBalance'];
      $o['isPayable'] = ($o['outstandingAmount'] > 0.0 && in_array($b['status'], ['PENDING', 'CONFIRMED'], true));
    }
    return $o;
  }, $rows);
}
function own_booking(array $user, string $ref): array {
  $id = parse_ref($ref, 'BK'); $row = $id ? q1('SELECT * FROM bookings WHERE id=? AND user_id=?', [$id, $user['id']]) : null;   // ownership enforced here
  if (!$row) fail('Booking not found.', 404);
  return $row;
}
function portal_reschedule(array $user, string $ref, array $b): void {
  $row = own_booking($user, $ref); $id = (int)$row['id'];
  if (!in_array($row['status'], ['PENDING', 'CONFIRMED'], true) || $row['archived']) {
    fail('This reservation can no longer be rescheduled online. Please contact the spa.');
  }

  // Part 7: 24-hour customer rescheduling policy calculated using the ORIGINAL appointment date and time
  $pol = validate_24h_policy($row['preferred_date'], $row['time']);
  if (!$pol['ok']) {
    fail($pol['error'], 400, ['code' => 'reschedule_cutoff_passed', 'policy' => '24h_rescheduling']);
  }

  if (q1("SELECT 1 AS x FROM reschedule_requests WHERE booking_id=? AND status='PENDING'", [$id])) {
    fail('You already have a reschedule request waiting for the spa.');
  }
  $n = q1('SELECT COUNT(*) AS n FROM reschedule_requests WHERE booking_id=?', [$id]);
  $maxResched = cfg_int('MAX_RESCHEDULE_REQUESTS', 3, 1, 10);
  if ($n && (int)$n['n'] >= $maxResched) {
    fail("This booking has reached the limit of $maxResched reschedule requests. Please contact the spa.");
  }

  $date = str($b['date'] ?? '');
  $time = str($b['time'] ?? '');
  $reason = clean_text($b['reason'] ?? '', 300);

  if (!is_date($date) || $date < today()) fail('Choose a date from today onward.', 400, ['field' => 'date']);
  if (!is_time($time)) fail('Choose an available time.', 400, ['field' => 'time']);
  if ($date === $row['preferred_date'] && $time === $row['time']) fail('Choose a different date or time.', 400, ['field' => 'time']);

  $dur = $row['duration'] !== '' ? (string)$row['duration'] : null;
  $e = slot_error($date, $time, $row['service'], $dur, $id, true, null, (int)$user['id']);
  if ($e) slot_fail($e);

  run('INSERT INTO reschedule_requests(booking_id,user_id,new_date,new_time,original_date,original_time,reason,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
      [$id, $user['id'], $date, $time, $row['preferred_date'], $row['time'], $reason, 'PENDING', now()]);
  act($user['id'], 'Reschedule requested: ' . $row['service']);
  notify($user['id'], 'Your reschedule request was sent. The spa will review it. Your current time stays until it is approved.');
  $to = (string)cfg('NOTIFY_EMAIL');
  if ($to !== '' && (mail_enabled() || mail_dev_log())) {
    try { send_mail($to, 'Reschedule request ' . bk_ref($row), "{$user['fullName']} <{$user['email']}>\nFrom: {$row['preferred_date']} {$row['time']}\nTo: $date $time\n\n$reason"); }
    catch (Throwable $ex) { error_log('notify error: ' . $ex->getMessage()); }
  }
  portal_fresh($user);
}
function portal_withdraw_reschedule(array $user, string $rid): void {
  $r = ctype_digit($rid) ? q1('SELECT * FROM reschedule_requests WHERE id=? AND user_id=?', [(int)$rid, $user['id']]) : null;   // ownership enforced here
  if (!$r) fail('Request not found.', 404);
  if ($r['status'] !== 'PENDING') fail('This request has already been answered.');
  run("UPDATE reschedule_requests SET status='WITHDRAWN',decided_at=? WHERE id=?", [now(), $r['id']]);
  act($user['id'], 'Reschedule request withdrawn');
  portal_fresh($user);
}
function portal_review(array $user, string $ref, array $b): void {
  $row = own_booking($user, $ref);
  if ($row['status'] !== 'COMPLETED') fail('You can leave a review once your visit is completed.');
  if (q1('SELECT 1 AS x FROM reviews WHERE booking_id=?', [$row['id']])) fail('You already reviewed this visit. Thank you!');
  $rt = $b['rating'] ?? null; $rt = (is_int($rt) || (is_string($rt) && ctype_digit($rt))) ? (int)$rt : 0;
  if ($rt < 1 || $rt > 5) fail('Please choose a rating from 1 to 5 stars.', 400, ['field' => 'rating']);
  $c = mb_substr(trim(str($b['comment'] ?? '')), 0, 1000);
  try { run('INSERT INTO reviews(booking_id,user_id,rating,comment,created_at) VALUES(?,?,?,?,?)', [$row['id'], $user['id'], $rt, $c, now()]); }
  catch (PDOException $e) { if ($e->getCode() === '23000') fail('You already reviewed this visit. Thank you!'); throw $e; }   // two clicks at once
  act($user['id'], 'Review submitted: ' . $row['service']);
  notify($user['id'], 'Thank you for your review!');
  portal_fresh($user);
}
/* public: visible reviews only, first name + last initial, no email or phone */
function api_public_reviews(): void {
  rate_limit('reviews', 120, 6e4, [], false);
  $rows = qa('SELECT r.rating,r.comment,r.created_at,b.service,COALESCE(u.full_name,b.name) AS who FROM reviews r JOIN bookings b ON b.id=r.booking_id LEFT JOIN users u ON u.id=r.user_id WHERE r.hidden=0 ORDER BY r.id DESC LIMIT 12');
  $a = q1('SELECT COUNT(*) AS n,AVG(rating) AS avg FROM reviews WHERE hidden=0');
  header('Cache-Control: public, max-age=60');
  out(['count' => (int)($a['n'] ?? 0), 'average' => $a && $a['n'] ? round((float)$a['avg'], 1) : 0, 'reviews' => array_map(function ($r) {
    $p = preg_split('/\s+/', trim((string)$r['who'])); $nm = ($p[0] ?? '') . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : '');
    return ['name' => $nm ?: 'Guest', 'rating' => (int)$r['rating'], 'comment' => $r['comment'], 'service' => $r['service'], 'date' => substr((string)$r['created_at'], 0, 10)];
  }, $rows)]);
}

/* ======================= reschedule requests and reviews (admin side) ======================= */
function admin_decide_reschedule(string $rid, array $b): void {
  $r = ctype_digit($rid) ? q1('SELECT * FROM reschedule_requests WHERE id=?', [(int)$rid]) : null;
  if (!$r) fail('Request not found.', 404);
  if ($r['status'] !== 'PENDING') fail('This request was already answered.');
  $dec = strtoupper(str($b['decision'] ?? '')); $note = clean_text($b['note'] ?? '', 300);
  if (!in_array($dec, ['APPROVED', 'DECLINED'], true)) fail('Choose approve or decline.');
  $bk = q1('SELECT * FROM bookings WHERE id=?', [$r['booking_id']]);
  if (!$bk) fail('Booking not found.', 404);

  $admin = admin_actor();
  $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';

  if ($dec === 'DECLINED') {
    run("UPDATE reschedule_requests SET status='DECLINED',admin_note=?,decided_by=?,decided_at=? WHERE id=?", [$note, $adminName, now(), $r['id']]);
    notify($bk['user_id'], 'Your reschedule request was not approved' . ($note !== '' ? ': ' . $note : '. Your original time stays.'));
    act($bk['user_id'], 'Reschedule declined: ' . $bk['service']);
    admin_log($admin, 'Reschedule declined', bk_ref($bk), $r['new_date'] . ' ' . $r['new_time'] . ($note ? " ($note)" : ''));
    admin_done();
  }
  if ($bk['archived'] || !in_array($bk['status'], ['PENDING', 'CONFIRMED'], true)) fail('This booking can no longer be moved. Decline the request instead.');
  if ($r['new_date'] < today()) fail('The requested date has already passed. Decline the request instead.');

  guard_slot($r['new_date'], $r['new_time'], $bk['service'], $bk['duration'] !== '' ? (string)$bk['duration'] : null, (int)$bk['id'], false, function () use ($r, $bk, $note, $adminName) {
    run('UPDATE bookings SET original_date=?,original_time=?,preferred_date=?,time=?,rescheduled_at=?,rescheduled_by=? WHERE id=?',
        [$bk['preferred_date'], $bk['time'], $r['new_date'], $r['new_time'], now(), $adminName, $bk['id']]);
    run("UPDATE reschedule_requests SET status='APPROVED',admin_note=?,decided_by=?,decided_at=? WHERE id=?",
        [$note, $adminName, now(), $r['id']]);

    // Phase 5: Revalidate assigned room and therapist for the new slot
    $dur = service_minutes($bk['service'], $bk['duration']) ?: 60;
    $s = hm_to_min($r['new_time']);
    $e = $s + $dur;
    $c = $e + buffer_minutes();
    $endTime = min_to_hm($e);
    $cleanEndTime = min_to_hm($c);

    // 1. Revalidate room assignment
    $roomAsgn = q1("SELECT * FROM appointment_room_assignments WHERE booking_id=? AND status='ASSIGNED'", [$bk['id']]);
    if ($roomAsgn) {
      $roomChk = is_room_available((int)$roomAsgn['room_id'], $r['new_date'], $r['new_time'], $dur, (int)$bk['id']);
      if ($roomChk['ok']) {
        run("UPDATE appointment_room_assignments SET assigned_date=?, start_time=?, end_time=?, cleaning_end_time=? WHERE id=?",
            [$r['new_date'], $r['new_time'], $endTime, $cleanEndTime, $roomAsgn['id']]);
        run("UPDATE room_cleaning SET cleaning_date=?, start_time=?, end_time=? WHERE booking_id=?",
            [$r['new_date'], $endTime, $cleanEndTime, $bk['id']]);
      } else {
        run("UPDATE appointment_room_assignments SET status='RELEASED' WHERE id=?", [$roomAsgn['id']]);
        run("DELETE FROM room_cleaning WHERE booking_id=?", [$bk['id']]);
        run("UPDATE bookings SET room_id=NULL WHERE id=?", [$bk['id']]);
      }
    }

    // 2. Revalidate therapist assignment
    $thAsgn = q1("SELECT * FROM appointment_therapist_assignments WHERE booking_id=? AND status='ASSIGNED'", [$bk['id']]);
    if ($thAsgn) {
      $thChk = is_therapist_available((int)$thAsgn['therapist_id'], $r['new_date'], $r['new_time'], $dur, $bk['service'], (int)$bk['id']);
      if ($thChk['ok']) {
        run("UPDATE appointment_therapist_assignments SET assigned_date=?, start_time=?, end_time=? WHERE id=?",
            [$r['new_date'], $r['new_time'], $endTime, $thAsgn['id']]);
      } else {
        run("UPDATE appointment_therapist_assignments SET status='RELEASED' WHERE id=?", [$thAsgn['id']]);
        run("UPDATE bookings SET therapist_id=NULL WHERE id=?", [$bk['id']]);
      }
    }
  });
  notify($bk['user_id'], 'Your reservation was moved to ' . $r['new_date'] . ' at ' . $r['new_time'] . '.');
  act($bk['user_id'], 'Reschedule approved: ' . $bk['service']);
  admin_log($admin, 'Reschedule approved', bk_ref($bk), $bk['preferred_date'] . ' ' . $bk['time'] . ' -> ' . $r['new_date'] . ' ' . $r['new_time']);
  admin_done();
}
function admin_review_visibility(string $rid, array $b): void {
  $r = ctype_digit($rid) ? q1('SELECT * FROM reviews WHERE id=?', [(int)$rid]) : null;
  if (!$r) fail('Review not found.', 404);
  $h = !empty($b['hidden']) ? 1 : 0;
  run('UPDATE reviews SET hidden=? WHERE id=?', [$h, $r['id']]);
  admin_log(admin_actor(), $h ? 'Review hidden' : 'Review shown', 'REV-' . pad($r['id']), $r['rating'] . ' stars');
  admin_done();
}

/* ======================= admin dashboard ======================= */
function admin_snapshot(): array {
  $content = []; foreach (qa('SELECT content_key,json FROM content') as $r) $content[$r['content_key']] = jparse($r['json'], []);
  $bksQuery = "SELECT b.*, t.full_name AS therapist_name, t.employee_code AS therapist_code, rm.room_name 
               FROM bookings b 
               LEFT JOIN therapists t ON t.id = b.therapist_id 
               LEFT JOIN rooms rm ON rm.room_code = b.room_id";

  $allRooms = get_all_rooms();
  $roomsWithStatus = array_map(function($r) {
    $r['liveStatus'] = calculate_room_live_status((int)$r['id']);
    return $r;
  }, $allRooms);

  $activeRooms = array_filter($roomsWithStatus, fn($r) => $r['room_status'] !== 'INACTIVE');
  $activeTherapists = qa("SELECT * FROM therapists WHERE employment_status = 'ACTIVE'");

  $roomStats = [
    'totalRooms'         => count($allRooms),
    'activeRooms'        => count($activeRooms),
    'availableRooms'     => count(array_filter($activeRooms, fn($r) => $r['liveStatus'] === 'AVAILABLE')),
    'occupiedRooms'      => count(array_filter($activeRooms, fn($r) => $r['liveStatus'] === 'OCCUPIED')),
    'cleaningRooms'      => count(array_filter($activeRooms, fn($r) => $r['liveStatus'] === 'CLEANING')),
    'maintenanceRooms'   => count(array_filter($allRooms, fn($r) => $r['room_status'] === 'MAINTENANCE' || $r['liveStatus'] === 'MAINTENANCE')),
    'activeTherapists'   => count($activeTherapists),
    'unassignedBookings' => (int)q1("SELECT COUNT(*) AS c FROM bookings WHERE archived = 0 AND status IN ('PENDING','CONFIRMED','IN_PROGRESS') AND (room_id IS NULL OR room_id = '' OR therapist_id IS NULL)")['c']
  ];

  return ['admin' => [
    'bookings' => array_map('shape_booking', qa($bksQuery . ' WHERE b.archived=0 ORDER BY b.id DESC')),
    'archived' => array_map('shape_booking', qa($bksQuery . ' WHERE b.archived=1 ORDER BY b.id DESC')),
    'checks' => security_checks(), 'live' => prod(),
    'reschedules' => array_map(fn($r) => shape_resched($r) + ['booking' => bk_ref(['id' => $r['booking_id'], 'created_at' => $r['bcreated']]), 'customer' => $r['name'], 'service' => $r['service'], 'currentDate' => $r['cdate'], 'currentTime' => $r['ctime'], 'bookingStatus' => $r['bstatus'], 'decidedBy' => $r['decided_by'] ?? '', 'decidedAt' => $r['decided_at'] ? iso($r['decided_at']) : ''],
      qa('SELECT r.*,b.created_at AS bcreated,b.name,b.service,b.preferred_date AS cdate,b.time AS ctime,b.status AS bstatus FROM reschedule_requests r JOIN bookings b ON b.id=r.booking_id ORDER BY r.id DESC')),
    'reviews' => array_map(fn($r) => ['id' => (int)$r['id'], 'ref' => 'REV-' . pad($r['id']), 'booking' => bk_ref(['id' => $r['booking_id'], 'created_at' => $r['bcreated']]), 'customer' => $r['name'], 'service' => $r['service'], 'rating' => (int)$r['rating'], 'comment' => $r['comment'], 'hidden' => (bool)$r['hidden'], 'createdAt' => iso($r['created_at'])],
      qa('SELECT r.*,b.created_at AS bcreated,b.name,b.service FROM reviews r JOIN bookings b ON b.id=r.booking_id ORDER BY r.id DESC')),
    'customers' => array_map('shape_customer', qa('SELECT * FROM users ORDER BY id')),
    'txns' => array_map('shape_txn', qa(TXQ . ' ORDER BY t.id DESC')),
    'activity' => array_map(fn($a) => ['customerId' => cus_ref($a['user_id']), 'text' => $a['text'], 'at' => iso($a['created_at'])], qa('SELECT * FROM activity ORDER BY id DESC LIMIT 500')),
    'adminLog' => array_map(fn($l) => ['admin' => $l['admin_name'], 'action' => $l['action'], 'target' => $l['target'], 'detail' => $l['detail'], 'at' => iso($l['created_at'])], qa('SELECT * FROM admin_log ORDER BY id DESC LIMIT 500')),
    'content' => (object)$content,
    /* Phase 5.0 Room, Package & Therapist Data */
    'rooms' => $roomsWithStatus,
    'roomCategories' => get_room_categories(),
    'packages' => get_packages(),
    'therapists' => array_map(function($t) {
      $t['schedule'] = get_therapist_schedule((int)$t['id']);
      return $t;
    }, get_all_therapists()),
    'cleanings' => qa("SELECT c.*, r.room_code, r.room_name FROM room_cleaning c JOIN rooms r ON r.id = c.room_id ORDER BY c.cleaning_date DESC, c.start_time DESC LIMIT 100"),
    'roomAssignments' => qa("SELECT a.*, r.room_code, r.room_name, b.name AS customer_name, b.service FROM appointment_room_assignments a JOIN rooms r ON r.id = a.room_id JOIN bookings b ON b.id = a.booking_id WHERE a.status = 'ASSIGNED' ORDER BY a.assigned_date DESC, a.start_time DESC"),
    'therapistAssignments' => qa("SELECT a.*, t.full_name AS therapist_name, t.employee_code, b.name AS customer_name, b.service FROM appointment_therapist_assignments a JOIN therapists t ON t.id = a.therapist_id JOIN bookings b ON b.id = a.booking_id WHERE a.status = 'ASSIGNED' ORDER BY a.assigned_date DESC, a.start_time DESC"),
    'roomStats' => $roomStats,
    /* Phase 6.0 Payment Data */
    'paymentAnalytics' => get_payment_analytics(),
    'paymentSettings' => get_all_payment_settings()
  ]];
}
function security_checks(): array {
  $dbOk = !(cfg('DB_USER') === 'root' && (string)cfg('DB_PASS') === '');
  $dir = ROOT . '/data'; $writable = is_dir($dir) ? is_writable($dir) : @mkdir($dir, 0775, true);
  return [
    ['id' => 'db', 'ok' => $dbOk, 'title' => 'Database login', 'fix' => $dbOk ? 'The site uses its own database user.' : 'The site connects as root with no password (the XAMPP default). Fine on your own computer. Before going online create a dedicated MySQL user with a strong password and put it in php/config.php.'],
    ['id' => 'prod', 'ok' => prod(), 'title' => 'Production mode', 'fix' => prod() ? 'On: Secure cookies, no debug mail log.' : 'Off (development). Set PRODUCTION to true in php/config.php on a live server.'],
    ['id' => 'https', 'ok' => is_https(), 'title' => 'HTTPS', 'fix' => is_https() ? 'This page is served over HTTPS.' : 'This page is not served over HTTPS. Use HTTPS on a live server.'],
    ['id' => 'smtp', 'ok' => mail_enabled(), 'title' => 'Email (SMTP)', 'fix' => mail_enabled() ? 'Email is configured: verification and password reset work.' : 'SMTP_URL is empty, so accounts are not email-verified and password reset only writes to data/mail.log. Set SMTP_URL before going live.'],
    ['id' => 'tz', 'ok' => cfg('TIMEZONE') && cfg('TIMEZONE') !== 'UTC', 'title' => 'Time zone', 'fix' => (cfg('TIMEZONE') && cfg('TIMEZONE') !== 'UTC') ? 'Booking dates are checked in ' . cfg('TIMEZONE') . '.' : 'TIMEZONE is UTC. Set your local time zone in php/config.php so "today" is correct.'],
    ['id' => 'ratelimit', 'ok' => $writable, 'title' => 'Login protection', 'fix' => $writable ? 'Rate limiting is active (data/ratelimit is writable).' : 'The data folder is not writable, so login rate limiting is OFF. Make data/ writable.'],
  ];
}
function admin_done(array $extra = [], int $code = 200): void { out(['ok' => true] + admin_snapshot() + $extra, $code); }

function clean_booking(array $b): array {
  $d = [
    'name'      => clean_name($b['name'] ?? ''),
    'phone'     => trim(str($b['phone'] ?? '')),
    'email'     => strtolower(trim(str($b['email'] ?? ''))),
    'service'   => trim(str($b['service'] ?? '') !== '' ? str($b['service']) : str($b['serviceName'] ?? '')) ?: 'Not sure yet',
    'date'      => str($b['date'] ?? ''),
    'time'      => str($b['time'] ?? ''),
    'duration'  => trim(str($b['duration'] ?? '')),
    'guests'    => isset($b['guests']) && is_numeric($b['guests']) ? (int)$b['guests'] : 1,
    'amount'    => is_numeric($b['amount'] ?? null) ? (float)$b['amount'] : 0.0,
    'status'    => strtoupper(str($b['status'] ?? '') ?: 'PENDING'),
  ];
  $e = null;
  if ($d['name'] === '' || mb_strlen($d['name']) > 100) $e = ['name', 'Enter the customer name (100 characters max).'];
  elseif ($d['phone'] !== '' && !is_phone($d['phone'])) $e = ['phone', 'Phone number must contain 11 digits (09…) or 12 digits (639…).'];
  elseif ($d['phone'] !== '') $d['phone'] = format_phone($d['phone']);
  elseif ($d['email'] !== '' && (strlen($d['email']) > 254 || !preg_match('/^[^\s@]+@[^\s@]+$/D', $d['email']))) $e = ['email', 'Enter a valid email address.'];
  elseif (mb_strlen($d['service']) > 100) $e = ['service', 'Service name is too long.'];
  elseif ($d['date'] !== '' && !is_date($d['date'])) $e = ['date', 'Enter a valid date.'];
  elseif ($d['time'] !== '' && !is_time($d['time'])) $e = ['time', 'Enter a valid time.'];
  elseif ($d['duration'] !== '' && !preg_match('/^\d{1,4}$/D', $d['duration'])) $e = ['duration', 'Duration is a number of minutes.'];
  elseif ($d['guests'] < min_guests() || $d['guests'] > max_guests()) $e = ['guests', 'Guest count must be between ' . min_guests() . ' and ' . max_guests() . '.'];
  elseif (!($d['amount'] >= 0 && $d['amount'] <= 1e7)) $e = ['amount', 'Enter a valid amount.'];
  elseif (!in_array($d['status'], STATUSES, true)) $e = ['status', 'Choose a valid status.'];
  return [$d, $e];
}
function admin_save_booking(array $b): void {
  [$d, $e] = clean_booking($b); if ($e) fail($e[1], 400, ['field' => $e[0]]);
  $holds = in_array($d['status'], BLOCKING_STATUSES, true) && $d['date'] !== '' && $d['time'] !== '';   // only bookings that hold a slot are checked
  $admin = admin_actor();
  $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';

  if (!empty($b['id'])) {
    $id = parse_ref($b['id'], 'BK'); $old = $id ? q1('SELECT * FROM bookings WHERE id=?', [$id]) : null;
    if (!$old) fail('Booking not found.', 404);
    $dur = $d['duration'] !== '' ? $d['duration'] : ($d['service'] === $old['service'] ? (string)$old['duration'] : (string)service_minutes($d['service']));

    // Status transition validation
    if ($d['status'] !== $old['status']) {
      $stErr = validate_status_transition($old['status'], $d['status'], true, $d['date'], $d['time'], $old['payment_status'] ?? null, $old['therapist_id'] ?? null);
      if ($stErr) fail($stErr, 400);
    }

    $write = function () use ($d, $dur, $id, $old, $adminName) {
      run('UPDATE bookings SET name=?,phone=?,email=?,service=?,preferred_date=?,time=?,duration=?,guests=?,amount=? WHERE id=?',
          [$d['name'], $d['phone'], $d['email'], $d['service'], $d['date'], $d['time'], $dur, $d['guests'], $d['amount'], $id]);
      if ($d['status'] !== $old['status']) {
        set_status($id, $d['status'], $d['status'] === 'CANCELLED' ? 'Admin status update' : '', $adminName);
        admin_log(admin_actor(), 'Booking status changed', bk_ref($old), $old['status'] . ' -> ' . $d['status']);
      } else sync_txn(q1('SELECT * FROM bookings WHERE id=?', [$id]));
    };
    if ($holds && !$old['archived']) guard_slot($d['date'], $d['time'], $d['service'], $dur, $id, false, $write); else $write();
    $new = ['name' => $d['name'], 'phone' => $d['phone'], 'email' => $d['email'], 'service' => $d['service'], 'preferred_date' => $d['date'], 'time' => $d['time'], 'duration' => $dur, 'guests' => $d['guests'], 'amount' => (float)$d['amount']];
    $diff = []; foreach ($new as $k => $v) if ((string)($k === 'amount' ? (float)$old[$k] : $old[$k]) !== (string)$v) $diff[] = $k === 'preferred_date' ? 'date' : $k;
    if ($diff) admin_log(admin_actor(), 'Booking edited', bk_ref($old), 'Changed: ' . implode(', ', $diff));
    admin_done();
  }
  if ($d['date'] === '') fail('Choose the booking date.', 400, ['field' => 'date']);
  if ($d['time'] === '') fail('Choose the booking time.', 400, ['field' => 'time']);
  $dur = $d['duration'] !== '' ? $d['duration'] : (string)service_minutes($d['service']);
  $u = $d['email'] !== '' ? q1('SELECT id FROM users WHERE email=?', [$d['email']]) : null;
  $write = function () use ($d, $dur, $u) {
    run('INSERT INTO bookings(user_id,name,email,service,preferred_date,time,duration,guests,amount,phone,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$u ? $u['id'] : null, $d['name'], $d['email'], $d['service'], $d['date'], $d['time'], $dur, $d['guests'], $d['amount'], $d['phone'], $d['status'], now(), now()]);
    return q1('SELECT * FROM bookings WHERE id=?', [last_id()]);
  };
  $nb = $holds ? guard_slot($d['date'], $d['time'], $d['service'], $dur, null, false, $write) : $write();
  booking_created($nb);
  admin_log(admin_actor(), 'Booking created', bk_ref($nb), $d['service'] . ' for ' . $d['name'] . ' (' . $d['guests'] . ' guests, ' . $dur . ' min)');
  admin_done([], 201);
}
function admin_booking_status(string $ref, array $b): void {
  $id = parse_ref($ref, 'BK'); $s = strtoupper(str($b['status'] ?? ''));
  $old = $id ? q1('SELECT * FROM bookings WHERE id=?', [$id]) : null;
  if (!$old) fail('Booking not found.', 404);
  if (!in_array($s, STATUSES, true)) fail('Choose a valid status.');

  // Validate status transition
  $stErr = validate_status_transition($old['status'], $s, true, $old['preferred_date'], $old['time'], $old['payment_status'] ?? null, $old['therapist_id'] ?? null);
  if ($stErr) fail($stErr, 400);

  $admin = admin_actor();
  $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
  $reason = clean_text($b['reason'] ?? '', 200);

  // If admin is cancelling within 24h notice window, record administrative override
  if ($s === 'CANCELLED') {
    $pol = validate_24h_policy($old['preferred_date'], $old['time']);
    if (!$pol['ok']) {
      admin_log($admin, 'Late cancellation override', bk_ref($old), "Admin override within 24h notice cutoff ($pol[diff_hours]h remaining). Reason: " . ($reason ?: 'None stated'));
    }
  }

  $reopens = in_array($s, BLOCKING_STATUSES, true) && !in_array($old['status'], BLOCKING_STATUSES, true) && !$old['archived'] && $old['preferred_date'] !== '' && $old['time'] !== '';
  if ($reopens) guard_slot($old['preferred_date'], $old['time'], $old['service'], (string)$old['duration'], $id, false, fn() => set_status($id, $s, $reason, $adminName));
  else set_status($id, $s, $reason, $adminName);
  admin_log($admin, 'Booking status changed', bk_ref($old), $old['status'] . ' -> ' . $s . ($reason ? " ($reason)" : ''));
  admin_done();
}
/* "Delete" in the admin screen ARCHIVES the booking: it leaves the working list but the booking, its payment record and history are kept. */
function admin_delete_booking(string $ref): void {
  $id = parse_ref($ref, 'BK'); $row = $id ? q1('SELECT * FROM bookings WHERE id=?', [$id]) : null;
  if ($row) {
    if (!empty($_GET['permanent']) && (int)$row['archived'] === 1) {
      run('DELETE FROM appointment_room_assignments WHERE booking_id=?', [$id]);
      run('DELETE FROM appointment_therapist_assignments WHERE booking_id=?', [$id]);
      run('DELETE FROM room_cleaning WHERE booking_id=?', [$id]);
      run('DELETE FROM reschedule_requests WHERE booking_id=?', [$id]);
      run('DELETE FROM bookings WHERE id=?', [$id]);
      admin_log(admin_actor(), 'Booking permanently deleted', bk_ref($row), $row['service']);
    } else {
      run('UPDATE bookings SET archived=1 WHERE id=?', [$id]);
      admin_log(admin_actor(), 'Booking archived', bk_ref($row), $row['service']);
    }
  }
  admin_done();
}
function admin_restore_booking(string $ref): void {
  $id = parse_ref($ref, 'BK'); $row = $id ? q1('SELECT * FROM bookings WHERE id=? AND archived=1', [$id]) : null;
  if (!$row) fail('Archived booking not found.', 404);
  $back = fn() => run('UPDATE bookings SET archived=0 WHERE id=?', [$id]);
  if (in_array($row['status'], BLOCKING_STATUSES, true) && $row['preferred_date'] !== '' && $row['time'] !== '') guard_slot($row['preferred_date'], $row['time'], $row['service'], (string)$row['duration'], $id, false, $back); else $back();
  admin_log(admin_actor(), 'Booking restored', bk_ref($row), $row['service']);
  admin_done();
}
function admin_set_active(string $ref, array $b): void {   // deactivate / reactivate a customer instead of deleting them
  $id = parse_ref($ref, 'CUS'); $u = $id ? q1('SELECT * FROM users WHERE id=?', [$id]) : null;
  if (!$u) fail('Customer not found.', 404);
  if ($u['role'] === 'ADMIN') fail('Admin accounts cannot be deactivated here.', 400);
  $on = !empty($b['active']) ? 1 : 0;
  run('UPDATE users SET active=? WHERE id=?', [$on, $id]);
  if (!$on) run('DELETE FROM sessions WHERE user_id=?', [$id]);
  admin_log(admin_actor(), $on ? 'Customer reactivated' : 'Customer deactivated', cus_ref($id), $u['email']);
  admin_done();
}
function admin_put_content(string $key, array $b): void {
  $items = $b['items'] ?? null;
  if (!in_array($key, CONTENT_KEYS, true)) fail('Unknown section.', 404);
  $bad = !is_array($items) || !is_list_arr($items) || count($items) > 500;
  if (!$bad) foreach ($items as $x) {
    if (!is_array($x) || ($x !== [] && is_list_arr($x))) { $bad = true; break; }
    foreach ($x as $v) if (!is_string($v) || mb_strlen($v) > 4000) { $bad = true; break 2; }
  }
  if ($bad) fail('Invalid content.');
  if ($e = content_check($key, $items)) fail($e);
  $items = array_map(fn($x) => $x === [] ? new stdClass : $x, $items);
  run('INSERT INTO content(content_key,json) VALUES(?,?) ON DUPLICATE KEY UPDATE json=VALUES(json)', [$key, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
  admin_log(admin_actor(), 'Content saved', $key, count($items) . ' items');
  admin_done();
}
