<?php
/* Bella Vista Oasis Spa - API entry point (PHP port of server/server.js).
   Apache (.htaccess) sends every /api/... request here; the route comes in as ?__path=... */
require __DIR__ . '/../php/lib.php';
require __DIR__ . '/../php/schedule.php';
require __DIR__ . '/../php/rooms.php';
require __DIR__ . '/../php/payments.php';
require __DIR__ . '/../php/portal.php';

ini_set('display_errors', '0');
header_remove('X-Powered-By');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
if (secure()) header('Strict-Transport-Security: max-age=15552000; includeSubDomains');

set_exception_handler(function (Throwable $e) {
  error_log('Bella Vista API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  if (!headers_sent()) fail('Something went wrong.', 500);
});

$method = $_SERVER['REQUEST_METHOD'];
$path = '/' . trim((string)($_GET['__path'] ?? ''), '/');
$body = [];

/* CSRF defence for state-changing calls: JSON body + custom header (forces a CORS preflight we never grant) + same-origin Origin check + SameSite=Lax cookie */
if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if ($origin !== '' && $origin !== origin_of(base_url()) && $origin !== request_origin()) fail('Request blocked.', 403);
  $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
  if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'bella-vista' || strpos($ct, 'application/json') !== 0) fail('Request blocked.', 403);
  $raw = file_get_contents('php://input', false, null, 0, 20481);
  if (strlen($raw) > 20480) fail('Invalid request.', 400);
  if (trim($raw) !== '') {
    $body = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) fail('Invalid request.', 400);
    if (!is_array($body) || is_list_arr($body) && $body !== []) $body = [];
  }
}

purge_expired();
$user = current_user();   /* the user is ALWAYS derived from the session cookie, never from the request body */
$need_auth = function () use ($user) { if (!$user) fail('An account is required to make a reservation.', 401, ['code' => 'auth_required']); };

/* ---------- auth routes ---------- */
if ($path === '/auth/me' && $method === 'GET') {
  out(['authenticated' => (bool)$user, 'user' => public_user($user), 'emailVerification' => mail_enabled(), 'mailConfigured' => mail_enabled() || mail_dev_log()]);
}
if ($path === '/auth/register' && $method === 'POST') {
  rate_limit('reg', 10, 36e5, $body);
  $name = clean_name($body['fullName'] ?? ''); $email = norm_email($body['email'] ?? ''); $pw = $body['password'] ?? null;
  if ($name === '') fail('Please enter your full name.', 400, ['field' => 'fullName']);
  if (mb_strlen($name) > 100) fail('Name is too long.', 400, ['field' => 'fullName']);
  if (!ok_email($email)) fail('Please enter a valid Gmail address.', 400, ['field' => 'email']);
  $pe = pw_error($pw); if ($pe) fail($pe, 400, ['field' => 'password']);
  if (($body['confirmPassword'] ?? null) !== $pw) fail('Passwords do not match.', 400, ['field' => 'confirmPassword']);
  $dup = ['error' => 'Email ID already exists. Please log in instead.', 'field' => 'email'];
  if (q1('SELECT 1 AS x FROM users WHERE email=?', [$email])) out($dup, 409);
  $hash = pw_hash($pw);
  try { run('INSERT INTO users(full_name,email,password_hash,email_verified,role) VALUES(?,?,?,?,?)', [$name, $email, $hash, mail_enabled() ? 0 : 1, 'CUSTOMER']); }   /* everyone registers as CUSTOMER */
  catch (PDOException $e) { if ($e->getCode() === '23000') out($dup, 409); throw $e; }
  $id = last_id();
  if (mail_enabled()) {
    try { send_token($id, $email, $name, 'verify'); } catch (Throwable $e) { error_log('verification mail error: ' . $e->getMessage()); }
    out(['ok' => true, 'needsVerification' => true], 201);
  }
  start_session($id);
  out(['ok' => true, 'user' => ['fullName' => $name, 'email' => $email, 'emailVerified' => true, 'role' => 'CUSTOMER', 'isAdmin' => false]], 201);
}
if ($path === '/auth/login' && $method === 'POST') {
  rate_limit('login', 8, 9e5, $body); rate_limit('login-ip', 40, 9e5, $body, false);   // per IP+email, and per IP across all emails
  $email = norm_email($body['email'] ?? ''); $pw = $body['password'] ?? null;
  if (!ok_email($email)) out(['error' => 'Please enter a valid Gmail address.', 'field' => 'email'], 400);
  if (!is_string($pw) || $pw === '') out(['error' => 'Please enter your password.', 'field' => 'password'], 400);
  if (mb_strlen($pw) > MAX_PW) out(['error' => 'Incorrect password.', 'field' => 'password'], 401);
  
  $u = q1('SELECT * FROM users WHERE email=?', [$email]);
  if (!$u) {
    out(['error' => 'Email not found', 'field' => 'email', 'code' => 'email_not_found'], 401);
  }
  
  if (!pw_verify($pw, $u['password_hash'])) {
    if ($u['role'] === 'ADMIN') admin_log(null, 'Failed admin login', $u['email'], 'from IP ' . client_ip(), 'System');
    out(['error' => 'Incorrect password', 'field' => 'password', 'code' => 'incorrect_password'], 401);
  }
  if (!(int)$u['active']) fail('This account is inactive. Please contact the spa.', 403);
  $weak = in_array($pw, test_passwords(), true);
  if ($weak && prod()) out(['error' => 'This shared test password is no longer accepted. Use "Forgot password" to choose a new one.'], 403);
  start_session((int)$u['id'], $u['role'] === 'ADMIN');
  if ($u['role'] === 'ADMIN') admin_log((int)$u['id'], 'Admin logged in');
  out(['ok' => true, 'message' => 'Login successful', 'user' => ['fullName' => $u['full_name'], 'email' => $u['email'], 'emailVerified' => (bool)$u['email_verified'], 'role' => $u['role'], 'isAdmin' => is_admin_row($u), 'weakPassword' => $weak]]);
}
if ($path === '/auth/logout' && $method === 'POST') {
  $sid = $_COOKIE[cookie_name()] ?? ''; if (is_string($sid) && $sid !== '') run('DELETE FROM sessions WHERE token_hash=?', [sha($sid)]);
  set_session_cookie('', 0); out(['ok' => true]);
}
if ($path === '/auth/verify' && $method === 'POST') {
  $uid = take_token($body['token'] ?? null, 'verify');
  if (!$uid) fail('This verification link is invalid or has expired.');
  run('UPDATE users SET email_verified=1 WHERE id=?', [$uid]); out(['ok' => true]);
}
if ($path === '/auth/resend-verification' && $method === 'POST') {
  $need_auth(); rate_limit('resend', 3, 36e5, $body);
  if (!mail_enabled()) fail('Email delivery is not configured on this server.', 503);
  if (!$user['emailVerified']) send_token($user['id'], $user['email'], $user['fullName'], 'verify');
  out(['ok' => true]);
}
if ($path === '/auth/forgot' && $method === 'POST') {
  rate_limit('forgot', 5, 36e5, $body);
  $email = norm_email($body['email'] ?? '');
  if (!ok_email($email)) fail('Please enter a valid Gmail address.', 400, ['field' => 'email']);
  $u = q1('SELECT * FROM users WHERE LOWER(email)=LOWER(?)', [$email]);
  if (!$u) fail('Email not found. No account is registered with this address.', 404, ['field' => 'email']);
  if ($u && (mail_enabled() || mail_dev_log())) {
    try { send_token((int)$u['id'], $u['email'], $u['full_name'], 'reset'); } catch (Throwable $e) { error_log('mail error: ' . $e->getMessage()); }
  }
  out(['ok' => true, 'directReset' => !mail_enabled(), 'email' => $email]);
}
if ($path === '/auth/reset' && $method === 'POST') {
  rate_limit('reset', 10, 36e5, $body);
  $pe = pw_error($body['password'] ?? null); if ($pe) fail($pe, 400, ['field' => 'password']);
  if (($body['confirmPassword'] ?? null) !== $body['password']) fail('Passwords do not match.', 400, ['field' => 'confirmPassword']);
  $uid = take_token($body['token'] ?? null, 'reset'); if (!$uid) fail('This reset link is invalid or has expired.');
  $existing = q1('SELECT password_hash FROM users WHERE id=?', [$uid]);
  if ($existing && !empty($existing['password_hash']) && pw_verify($body['password'], $existing['password_hash'])) {
    fail('New password cannot be the same as your old password. Please enter a different password.', 400, ['field' => 'password']);
  }
  run('UPDATE users SET password_hash=?,email_verified=1 WHERE id=?', [pw_hash($body['password']), $uid]);
  $ru = q1('SELECT role FROM users WHERE id=?', [$uid]); if ($ru && $ru['role'] === 'ADMIN') admin_log($uid, 'Password reset by email link');
  run('DELETE FROM sessions WHERE user_id=?', [$uid]);   // sign out everywhere
  out(['ok' => true, 'message' => 'Password updated successfully.']);
}
if ($path === '/auth/reset-direct' && $method === 'POST') {
  rate_limit('reset', 10, 36e5, $body);
  $email = norm_email($body['email'] ?? '');
  $pw = $body['password'] ?? null;
  $cpw = $body['confirmPassword'] ?? null;
  if (!ok_email($email)) fail('Please enter a valid Gmail address.', 400, ['field' => 'email']);
  if ($pw === null || $pw === '') fail('Please enter a new password.', 400, ['field' => 'password']);
  if ($cpw !== $pw) fail('Passwords do not match.', 400, ['field' => 'confirmPassword']);
  $pe = pw_error($pw); if ($pe) fail($pe, 400, ['field' => 'password']);
  $u = q1('SELECT id, role, password_hash FROM users WHERE LOWER(email)=LOWER(?)', [$email]);
  if (!$u) fail('Error updating password (email may not exist).', 404, ['field' => 'email']);
  if (!empty($u['password_hash']) && pw_verify($pw, $u['password_hash'])) {
    fail('New password cannot be the same as your old password. Please enter a different password.', 400, ['field' => 'password']);
  }
  run('UPDATE users SET password_hash=?, email_verified=1 WHERE id=?', [pw_hash($pw), $u['id']]);
  if ($u['role'] === 'ADMIN') admin_log((int)$u['id'], 'Password reset directly by email');
  run('DELETE FROM sessions WHERE user_id=?', [$u['id']]);   // sign out everywhere
  out(['ok' => true, 'message' => 'Password updated successfully.']);
}

/* ---------- public: services and rooms as edited in the admin dashboard (no private data) ---------- */
if ($path === '/content' && $method === 'GET') { header('Cache-Control: public, max-age=20'); out(content_overlay()); }

/* ---------- public: guest reviews (visible ones only) ---------- */
if ($path === '/reviews' && $method === 'GET') api_public_reviews();

/* ---------- public: free start times for a date and service ---------- */
if ($path === '/availability' && $method === 'GET') api_availability();

/* ---------- public: Phase 5 packages catalog ---------- */
if ($path === '/packages' && $method === 'GET') {
  out(['ok' => true, 'packages' => get_packages(true), 'categories' => get_room_categories()]);
}

/* ---------- public: Dynamic Room Recommendation ---------- */
if ($path === '/rooms/recommendations' && $method === 'GET') {
  $service = trim((string)($_GET['service'] ?? ''));
  $dur = (int)($_GET['duration'] ?? 60);
  $guests = (int)($_GET['guests'] ?? 1);
  $date = !empty($_GET['date']) ? trim((string)$_GET['date']) : null;
  $time = !empty($_GET['time']) ? trim((string)$_GET['time']) : null;
  out(get_room_recommendations($service, $dur, $guests, $date, $time));
}

/* ---------- public: available rooms for appointment criteria ---------- */
if ($path === '/rooms/available' && $method === 'GET') {
  $date = trim((string)($_GET['date'] ?? ''));
  $time = trim((string)($_GET['time'] ?? ''));
  $dur = (int)($_GET['duration'] ?? 60);
  $guests = (int)($_GET['guests'] ?? 1);
  if ($date === '' || $time === '') fail('Date and time are required.', 400);
  out(['ok' => true, 'rooms' => get_available_rooms($date, $time, $dur, $guests)]);
}

/* ---------- public: available therapists for appointment criteria ---------- */
if ($path === '/therapists/available' && $method === 'GET') {
  $date = trim((string)($_GET['date'] ?? ''));
  $time = trim((string)($_GET['time'] ?? ''));
  $dur = (int)($_GET['duration'] ?? 60);
  $svc = trim((string)($_GET['service'] ?? ''));
  if ($date === '' || $time === '') fail('Date and time are required.', 400);
  out(['ok' => true, 'therapists' => get_available_therapists($date, $time, $dur, $svc)]);
}

/* ---------- public: Phase 6 payment settings & demo mode info ---------- */
if ($path === '/payment/settings' && $method === 'GET') {
  out([
    'ok' => true,
    'settings' => get_all_payment_settings(),
    'disclaimer' => cfg('DEMO_DISCLAIMER_TEXT', 'DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.')
  ]);
}

/* ---------- bookings: authenticated, customer id comes from the session only ---------- */
if ($path === '/bookings' && $method === 'POST') {
  $need_auth();
  if (!empty($user['isAdmin'])) fail('Admin accounts manage bookings from the Admin Dashboard. Use a customer account to make a reservation.', 403, ['code' => 'admin_account']);
  rate_limit('book', 20, 36e5, $body, true, (string)$user['id']); api_create_booking($user, $body);
}
if ($path === '/bookings' && $method === 'GET') { $need_auth(); api_list_my_bookings($user); }

/* ---------- customer portal ---------- */
if ($path === '/portal' || strpos($path, '/portal/') === 0) {
  if (!$user) fail('Please log in to view your account.', 401, ['code' => 'auth_required']);
  if ($path === '/portal' && $method === 'GET') out(portal_snapshot($user['id']));
  if ($path === '/portal/profile' && $method === 'PATCH') portal_profile($user, $body);
  if ($path === '/portal/preferences' && $method === 'PUT') portal_preferences($user, $body);
  if (preg_match('#^/portal/bookings/([^/]+)/cancel$#D', $path, $m) && $method === 'POST') portal_cancel($user, urldecode($m[1]), $body);
  if ($path === '/portal/notifications/read' && $method === 'POST') portal_read_all($user);
  if (preg_match('#^/portal/bookings/([^/]+)/hide$#D', $path, $m) && $method === 'POST') portal_hide($user, urldecode($m[1]));
  if (preg_match('#^/portal/bookings/([^/]+)/reschedule$#D', $path, $m) && $method === 'POST') { rate_limit('resched', 15, 36e5, $body, true, (string)$user['id']); portal_reschedule($user, urldecode($m[1]), $body); }
  if (preg_match('#^/portal/reschedules/(\d+)/withdraw$#D', $path, $m) && $method === 'POST') portal_withdraw_reschedule($user, $m[1]);
  if (preg_match('#^/portal/bookings/([^/]+)/review$#D', $path, $m) && $method === 'POST') { rate_limit('review', 15, 36e5, $body, true, (string)$user['id']); portal_review($user, urldecode($m[1]), $body); }
  if ($path === '/portal/favorites' && $method === 'POST') portal_favorite($user, $body);
  if ($path === '/portal/password' && $method === 'POST') { rate_limit('pw', 10, 36e5, $body); portal_password($user, $body); }
  if ($path === '/portal/clear' && $method === 'POST') portal_clear($user);

  /* ---- Phase 6.0 Customer Demo Payment Routes ---- */
  if (preg_match('#^/portal/bookings/([^/]+)/payment$#D', $path, $m) && $method === 'GET') {
    $b = own_booking($user, urldecode($m[1]));
    out(['ok' => true, 'payment' => calculate_booking_payment_summary((int)$b['id'])]);
  }
  if (preg_match('#^/portal/bookings/([^/]+)/pay$#D', $path, $m) && $method === 'POST') {
    rate_limit('pay', 30, 36e5, $body, true, (string)$user['id']);
    $b = own_booking($user, urldecode($m[1]));
    $res = process_demo_payment((int)$b['id'], $body + ['userId' => (int)$user['id']]);
    out(['ok' => true] + $res + portal_snapshot((int)$user['id']));
  }
  if (preg_match('#^/portal/transactions/([^/]+)/receipt$#D', $path, $m) && $method === 'GET') {
    $tid = resolve_txn_id(urldecode($m[1]));
    if (!$tid) fail('Transaction not found.', 404);
    $tx = q1('SELECT user_id FROM transactions WHERE id = ?', [$tid]);
    if (!$tx || (int)$tx['user_id'] !== (int)$user['id']) fail('Transaction not found or access denied.', 403);
    $rec = generate_digital_receipt($tid);
    if (!$rec) fail('Digital receipt is only available for completed (PAID) transactions.', 404);
    out(['ok' => true, 'receipt' => $rec]);
  }
}

/* ---------- admin dashboard (re-checked on every call) ---------- */
if ($path === '/admin' || strpos($path, '/admin/') === 0) {
  if (!$user) fail('Please log in.', 401, ['code' => 'auth_required']);
  if (empty($user['isAdmin'])) fail('Admin access required.', 403, ['code' => 'admin_required']);
  admin_actor($user);
  if ($path === '/admin/data' && $method === 'GET') out(admin_snapshot());
  if (preg_match('#^/admin/customers/([^/]+)/active$#D', $path, $m) && $method === 'POST') admin_set_active(urldecode($m[1]), $body);
  if (preg_match('#^/admin/reschedules/(\d+)/decision$#D', $path, $m) && $method === 'POST') admin_decide_reschedule($m[1], $body);
  if (preg_match('#^/admin/reviews/(\d+)/visibility$#D', $path, $m) && $method === 'POST') admin_review_visibility($m[1], $body);
  if ($path === '/admin/bookings' && $method === 'POST') admin_save_booking($body);
  if (preg_match('#^/admin/bookings/([^/]+)/status$#D', $path, $m) && $method === 'POST') admin_booking_status(urldecode($m[1]), $body);
  if (preg_match('#^/admin/bookings/([^/]+)/restore$#D', $path, $m) && $method === 'POST') admin_restore_booking(urldecode($m[1]));
  if (preg_match('#^/admin/bookings/([^/]+)$#D', $path, $m) && $method === 'DELETE') admin_delete_booking(urldecode($m[1]));
  if (preg_match('#^/admin/content/([^/]+)$#D', $path, $m) && $method === 'PUT') admin_put_content(urldecode($m[1]), $body);

  /* ---- Phase 5.0 Room Management Routes ---- */
  if ($path === '/admin/rooms' && $method === 'GET') {
    out(['ok' => true, 'rooms' => array_map(function($r) {
      $r['liveStatus'] = calculate_room_live_status((int)$r['id']);
      return $r;
    }, get_all_rooms()), 'categories' => get_room_categories()]);
  }
  if ($path === '/admin/rooms' && $method === 'POST') {
    $r = save_room($body, isset($body['id']) ? (int)$body['id'] : null);
    admin_done(['room' => $r]);
  }
  if (preg_match('#^/admin/rooms/(\d+)/deactivate$#D', $path, $m) && $method === 'POST') {
    deactivate_room((int)$m[1]);
    admin_done();
  }
  if (preg_match('#^/admin/rooms/(\d+)/maintenance$#D', $path, $m) && $method === 'POST') {
    $roomId = (int)$m[1];
    $sDate = trim((string)($body['startDate'] ?? ''));
    $eDate = trim((string)($body['endDate'] ?? $sDate));
    $sTime = trim((string)($body['startTime'] ?? '00:00'));
    $eTime = trim((string)($body['endTime'] ?? '23:59'));
    $reason = clean_text($body['reason'] ?? 'Scheduled Maintenance', 300);
    if ($sDate === '') fail('Start date is required.', 400);
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    run('INSERT INTO room_maintenance (room_id, start_date, start_time, end_date, end_time, reason, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [$roomId, $sDate, $sTime, $eDate, $eTime, $reason, 'SCHEDULED', $adminName]);
    admin_done();
  }

  /* ---- Phase 5.0 Package Management Routes ---- */
  if ($path === '/admin/packages' && $method === 'GET') {
    out(['ok' => true, 'packages' => get_packages()]);
  }
  if ($path === '/admin/packages' && $method === 'POST') {
    $p = save_package($body, isset($body['id']) ? (int)$body['id'] : null);
    admin_done(['package' => $p]);
  }

  /* ---- Phase 5.0 Therapist Management Routes ---- */
  if ($path === '/admin/therapists' && $method === 'GET') {
    out(['ok' => true, 'therapists' => get_all_therapists()]);
  }
  if ($path === '/admin/therapists' && $method === 'POST') {
    $t = save_therapist($body, isset($body['id']) ? (int)$body['id'] : null);
    admin_done(['therapist' => $t]);
  }
  if (preg_match('#^/admin/therapists/(\d+)/schedule$#D', $path, $m) && $method === 'GET') {
    out(['ok' => true, 'schedule' => get_therapist_schedule((int)$m[1])]);
  }
  if (preg_match('#^/admin/therapists/(\d+)/schedule$#D', $path, $m) && $method === 'PUT') {
    update_therapist_schedule((int)$m[1], $body['shifts'] ?? []);
    admin_done(['schedule' => get_therapist_schedule((int)$m[1])]);
  }
  if (preg_match('#^/admin/therapists/(\d+)/breaks$#D', $path, $m) && $method === 'POST') {
    add_therapist_break((int)$m[1], $body['date'] ?? null, $body['start'] ?? '17:00', $body['end'] ?? '17:30', $body['type'] ?? 'Meal Break', $body['notes'] ?? '');
    admin_done(['schedule' => get_therapist_schedule((int)$m[1])]);
  }
  if (preg_match('#^/admin/therapists/(\d+)/leaves$#D', $path, $m) && $method === 'POST') {
    add_therapist_leave((int)$m[1], $body['startDate'] ?? '', $body['endDate'] ?? '', $body['type'] ?? 'Annual Leave', $body['notes'] ?? '');
    admin_done(['schedule' => get_therapist_schedule((int)$m[1])]);
  }

  /* ---- Phase 5.0 Appointment Resource Assignments ---- */
  if (preg_match('#^/admin/bookings/([^/]+)/assign-room$#D', $path, $m) && $method === 'POST') {
    $bid = parse_ref(urldecode($m[1]), 'BK');
    if (!$bid) fail('Invalid booking ID.', 400);
    $roomId = (int)($body['roomId'] ?? 0);
    if (!$roomId) fail('Please select a valid room.', 400);
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    $res = assign_appointment_room($bid, $roomId, $adminName);
    admin_done(['assignment' => $res]);
  }
  if (preg_match('#^/admin/bookings/([^/]+)/assign-therapist$#D', $path, $m) && $method === 'POST') {
    $bid = parse_ref(urldecode($m[1]), 'BK');
    if (!$bid) fail('Invalid booking ID.', 400);
    $thId = (int)($body['therapistId'] ?? 0);
    if (!$thId) fail('Please select a valid therapist.', 400);
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    $res = assign_appointment_therapist($bid, $thId, $adminName);
    admin_done(['assignment' => $res]);
  }

  /* ---- Phase 5.0 Room Cleaning Status Updates ---- */
  if (preg_match('#^/admin/cleanings/(\d+)/status$#D', $path, $m) && $method === 'POST') {
    $cid = (int)$m[1];
    $status = trim((string)($body['status'] ?? 'COMPLETED'));
    $cleanedBy = trim((string)($body['cleanedBy'] ?? ''));
    $notes = trim((string)($body['notes'] ?? ''));
    $c = update_cleaning_status($cid, $status, $cleanedBy, $notes);
    admin_done(['cleaning' => $c]);
  }

  /* ---- Phase 6.0 Payment & Transaction Management Routes ---- */
  if ($path === '/admin/payments/analytics' && $method === 'GET') {
    out(['ok' => true, 'analytics' => get_payment_analytics(), 'settings' => get_all_payment_settings()]);
  }
  if ($path === '/admin/payments/settings' && $method === 'PUT') {
    $k = trim((string)($body['key'] ?? ''));
    $v = trim((string)($body['value'] ?? ''));
    if ($k === '') fail('Setting key is required.', 400);
    update_payment_setting($k, $v);
    admin_log(admin_actor(), 'Payment setting updated', $k, $v);
    admin_done(['settings' => get_all_payment_settings()]);
  }
  if (preg_match('#^/admin/transactions/([^/]+)/confirm-cash$#D', $path, $m) && $method === 'POST') {
    $tid = resolve_txn_id(urldecode($m[1]));
    if (!$tid) fail('Invalid transaction reference.', 400);
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    $res = admin_confirm_cash_payment($tid, $adminName);
    admin_log($admin, 'Cash payment confirmed', 'TXN-' . $tid);
    admin_done(['transaction' => $res]);
  }
  if (preg_match('#^/admin/bookings/([^/]+)/refund$#D', $path, $m) && $method === 'POST') {
    $bid = parse_ref(urldecode($m[1]), 'BK');
    if (!$bid) fail('Invalid booking ID.', 400);
    $amount = (float)($body['amount'] ?? 0);
    $reason = trim((string)($body['reason'] ?? ''));
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    $res = process_demo_refund($bid, $amount, $reason, $adminName);
    admin_log($admin, 'Refund processed', 'BK-' . $bid, '₱' . number_format($amount, 2));
    admin_done(['refund' => $res]);
  }
  if (preg_match('#^/admin/bookings/([^/]+)/forfeit$#D', $path, $m) && $method === 'POST') {
    $bid = parse_ref(urldecode($m[1]), 'BK');
    if (!$bid) fail('Invalid booking ID.', 400);
    $reason = trim((string)($body['reason'] ?? 'Late cancellation forfeiture'));
    $admin = admin_actor();
    $adminName = is_array($admin) ? ($admin['fullName'] ?? 'Admin') : 'Admin';
    $res = forfeit_demo_downpayment($bid, $reason, $adminName);
    admin_log($admin, 'Downpayment forfeited', 'BK-' . $bid, $reason);
    admin_done(['forfeiture' => $res]);
  }
  if (preg_match('#^/admin/transactions/([^/]+)/receipt$#D', $path, $m) && $method === 'GET') {
    $tid = resolve_txn_id(urldecode($m[1]));
    if (!$tid) fail('Transaction not found.', 404);
    $rec = generate_digital_receipt($tid);
    if (!$rec) fail('Digital receipt is only available for completed (PAID) transactions.', 404);
    out(['ok' => true, 'receipt' => $rec]);
  }
}

fail('Not found.', 404);
