<?php
/* Bella Vista Oasis Spa - shared PHP library (PHP 8.0+, MySQL/MariaDB via PDO).
   PHP port of server/server.js + server/mailer.js. Same security model:
   Argon2id passwords, random session token in an HttpOnly cookie (only its SHA-256 is stored),
   the customer is always taken from the session and never from the request body. */

define('ROOT', dirname(__DIR__));
const SESSION_MS = 7 * 86400 * 1000;          // customers stay signed in for 7 days
const ADMIN_SESSION_MS = 12 * 3600 * 1000;    // admins for 12 hours
const MIN_PW = 8;
const MAX_PW = 256;

/* ---------- config ---------- */
function cfg(string $k) { static $c = null; $c ??= require __DIR__ . '/config.php'; return $c[$k] ?? null; }
function test_passwords(): array {
  $passwords = cfg('TEST_PASSWORDS');
  if (!is_array($passwords)) return [];
  return array_values(array_filter($passwords, static fn($password) => is_string($password) && $password !== ''));
}
function prod(): bool { return (bool)cfg('PRODUCTION'); }
function secure(): bool { return prod() || cfg('COOKIE_SECURE'); }
function cookie_name(): string { return (secure() ? '__Host-' : '') . 'bv_sid'; }
function is_https(): bool {
  $h = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  if (!$h && cfg('TRUST_PROXY')) $h = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  return $h;
}
function base_path(): string {   // folder the site lives in, e.g. "/Bella-Vista-Oasis-Spa" or ""
  $d = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')));
  return ($d === '/' || $d === '.') ? '' : rtrim($d, '/');
}
function request_origin(): string { return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'); }
function base_url(): string { $b = cfg('BASE_URL'); return $b ? rtrim($b, '/') : request_origin() . base_path(); }
function origin_of(string $url): string {
  $p = parse_url($url); if (!$p || empty($p['scheme']) || empty($p['host'])) return '';
  return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}
function today(): string { return (new DateTime('now', new DateTimeZone(cfg('TIMEZONE') ?: 'UTC')))->format('Y-m-d'); }

/* ---------- database ---------- */
function db(): PDO {
  static $p = null; if ($p) return $p;
  $dsn = 'mysql:host=' . cfg('DB_HOST') . ';port=' . cfg('DB_PORT') . ';dbname=' . cfg('DB_NAME') . ';charset=utf8mb4';
  $p = new PDO($dsn, cfg('DB_USER'), cfg('DB_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
  $p->exec("SET time_zone='+00:00'");
  return $p;
}
function run(string $sql, array $a = []): PDOStatement { $s = db()->prepare($sql); $s->execute($a); return $s; }
function q1(string $sql, array $a = []): ?array { $r = run($sql, $a)->fetch(); return $r ?: null; }
function qa(string $sql, array $a = []): array { return run($sql, $a)->fetchAll(); }
function last_id(): int { return (int)db()->lastInsertId(); }
function now(): string { return gmdate('Y-m-d H:i:s'); }          // stored as UTC DATETIME
function ms(): int { return (int)floor(microtime(true) * 1000); }
function iso(?string $dt): string { return $dt ? str_replace(' ', 'T', $dt) . 'Z' : ''; }   // what the browser receives
function sha(string $s): string { return hash('sha256', $s); }
function rnd(): string { return bin2hex(random_bytes(32)); }

/* ---------- small helpers ---------- */
function out($data, int $code = 200): never {
  if (defined('TEST_RUNNER') && TEST_RUNNER) {
    $err = is_array($data) ? ($data['error'] ?? json_encode($data)) : (string)$data;
    throw new RuntimeException($err, $code);
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
  exit;
}
function fail(string $msg, int $code = 400, array $extra = []): never { out(['error' => $msg] + $extra, $code); }
function str($v): string { return is_scalar($v) ? (string)$v : ''; }          // never trust types from JSON
function is_list_arr(array $a): bool { return $a === [] || array_keys($a) === range(0, count($a) - 1); }
function client_ip(): string {
  if (cfg('TRUST_PROXY') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
function jparse($s, $d) { $v = json_decode((string)$s, true); return is_array($v) ? $v : $d; }
function is_date(string $s): bool { $d = DateTime::createFromFormat('Y-m-d', $s); return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/D', $s) && $d && $d->format('Y-m-d') === $s; }
function is_time(string $s): bool { return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $s); }
function clean_phone(string $s): string {
  $digits = preg_replace('/\D/', '', $s);
  if (str_starts_with($digits, '639') && strlen($digits) === 12) {
    $digits = '0' . substr($digits, 2);
  } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
    $digits = '0' . $digits;
  }
  return $digits;
}
function is_phone(string $s): bool {
  $clean = clean_phone($s);
  return (bool)preg_match('/^09\d{9}$/', $clean);
}
function format_phone(string $s): string {
  $c = clean_phone($s);
  if (preg_match('/^(09\d{2})(\d{3})(\d{4})$/', $c, $m)) {
    return "{$m[1]} {$m[2]} {$m[3]}";
  }
  return $s;
}
function clean_name($v): string { return trim(strip_tags(preg_replace('/\s+/u', ' ', str($v)) ?? '')); }
function clean_text($v, int $max = 1000): string { return mb_substr(trim(strip_tags(str($v))), 0, $max); }

/* ---------- 24-hour cancellation and rescheduling policy (Part 7) ---------- */
function validate_24h_policy(string $date, string $time): array {
  $tzName = cfg('TIMEZONE') ?: 'Asia/Manila';
  $tz = new DateTimeZone($tzName);
  try {
    $timePart = strlen($time) === 5 ? $time . ':00' : $time;
    $appt = new DateTimeImmutable($date . ' ' . $timePart, $tz);
  } catch (Throwable $e) {
    return ['ok' => false, 'diff_seconds' => 0, 'diff_hours' => 0, 'error' => 'Invalid appointment date or time.'];
  }
  $now = new DateTimeImmutable('now', $tz);
  $diffSeconds = $appt->getTimestamp() - $now->getTimestamp();
  $diffHours = round($diffSeconds / 3600, 2);
  $cutoffHours = cfg_int('CANCELLATION_NOTICE_HOURS', 24, 1, 168);
  $requiredSeconds = $cutoffHours * 3600;

  if ($diffSeconds < $requiredSeconds) {
    return [
      'ok'           => false,
      'diff_seconds' => $diffSeconds,
      'diff_hours'   => $diffHours,
      'cutoff_hours' => $cutoffHours,
      'error'        => "Cancellations and rescheduling requests must be submitted at least $cutoffHours hours before your appointment. Your appointment is scheduled in $diffHours hours.",
      'is_late'      => true,
    ];
  }

  return [
    'ok'           => true,
    'diff_seconds' => $diffSeconds,
    'diff_hours'   => $diffHours,
    'cutoff_hours' => $cutoffHours,
    'error'        => null,
    'is_late'      => false,
  ];
}

/* ---------- status transitions (Part 9) ---------- */
const VALID_STATUSES = ['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO-SHOW', 'REJECTED'];

function validate_status_transition(string $old, string $new, bool $isAdmin = false, ?string $date = null, ?string $time = null, ?string $paymentStatus = null, $therapistId = null): ?string {
  if (!in_array($new, VALID_STATUSES, true)) {
    return "Invalid status '$new'.";
  }
  if ($old === $new) {
    return "The booking is already $new.";
  }

  // Allowed transitions
  $allowed = [
    'PENDING'     => ['CONFIRMED', 'REJECTED', 'CANCELLED'],
    'CONFIRMED'   => ['IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO-SHOW'],
    'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
    'COMPLETED'   => [], // terminal status
    'CANCELLED'   => ['PENDING'], // restore only
    'NO-SHOW'     => ['PENDING'], // restore only
    'REJECTED'    => ['PENDING'], // restore only
  ];

  $allowedNext = $allowed[$old] ?? [];
  if (!in_array($new, $allowedNext, true)) {
    return "Cannot transition status from $old to $new.";
  }

  if ($new === 'IN_PROGRESS' && empty($therapistId)) {
    return 'Cannot start treatment: no therapist has been assigned to this booking yet. Please assign a therapist first.';
  }

  // Treatment can only start or complete once the booking is partially paid or fully paid
  $okPay = ($new === 'COMPLETED') ? ['PARTIALLY_PAID', 'PAID', 'REFUNDED'] : ['PARTIALLY_PAID', 'PAID'];
  if (in_array($new, ['IN_PROGRESS', 'COMPLETED'], true) && !in_array(strtoupper((string)($paymentStatus ?? 'UNPAID')), $okPay, true)) {
    return ($new === 'COMPLETED' ? 'Cannot complete this appointment: the customer has not paid yet. A partial or full payment is required first.' : 'Cannot start treatment: the customer has not paid yet. A partial or full payment is required first.');
  }

  // Audit F4: prevent marking COMPLETED for future dates/times
  if ($new === 'COMPLETED' && $date) {
    $tz = new DateTimeZone(cfg('TIMEZONE') ?: 'Asia/Manila');
    $timePart = ($time && strlen($time) === 5) ? $time . ':00' : ($time ?: '23:59:59');
    try {
      $apptEnd = new DateTimeImmutable($date . ' ' . $timePart, $tz);
      $now = new DateTimeImmutable('now', $tz);
      if ($apptEnd > $now) {
        return "Cannot mark an appointment as COMPLETED before its scheduled appointment time.";
      }
    } catch (Throwable $e) {}
  }

  return null;
}

/* ---------- validation (the real security control) ---------- */
function pw_error($p): ?string {
  if (!is_string($p) || $p === '') return 'Please enter a password.';
  $n = mb_strlen($p, 'UTF-8');
  if ($n < MIN_PW) return 'Password must contain at least 8 characters.';
  if ($n > MAX_PW) return 'Password is too long (maximum 256 characters).';
  if (!preg_match('/[A-Z]/', $p)) return 'Password must contain an uppercase letter.';
  if (!preg_match('/[a-z]/', $p)) return 'Password must contain a lowercase letter.';
  if (!preg_match('/\d/', $p)) return 'Password must contain a number.';
  if (!preg_match('/[^A-Za-z0-9\s]/', $p)) return 'Password must contain a special character.';
  return null;
}
function norm_email($e): string { return is_string($e) ? preg_replace('/@GMAIL\.COM$/iD', '@gmail.com', trim($e)) : ''; }
function ok_email(string $e): bool { return strlen($e) <= 254 && preg_match('/^[A-Za-z0-9._%+-]+@gmail\.com$/D', $e); }

/* Argon2id when this PHP has it (XAMPP does); otherwise bcrypt over a SHA-256 pre-hash so long passwords are not truncated. */
function pw_hash(string $p): string {
  if (defined('PASSWORD_ARGON2ID')) return password_hash($p, PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]);
  return password_hash(base64_encode(hash('sha256', $p, true)), PASSWORD_BCRYPT, ['cost' => 12]);
}
function pw_verify(string $p, string $hash): bool {
  if (password_verify($p, $hash)) return true;
  if (str_starts_with($hash, '$2y$')) {
    $pPre = base64_encode(hash('sha256', $p, true));
    if (password_verify($pPre, $hash)) return true;
  }
  return false;
}

/* ---------- rate limiting (file based: PHP has no shared memory between requests) ---------- */
function rate_limit(string $name, int $max, int $windowMs, array $body = [], bool $byEmail = true, ?string $userKey = null): void {
  $key = $name . '|' . ($userKey !== null ? 'user:' . $userKey : client_ip() . '|' . ($byEmail ? strtolower(str($body['email'] ?? '')) : ''));
  $dir = ROOT . '/data/ratelimit';
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $fh = @fopen($dir . '/' . sha1($key) . '.json', 'c+'); if (!$fh) { error_log('Bella Vista: data/ratelimit is not writable, rate limiting is OFF'); return; }   // never block customers, but make the problem visible
  flock($fh, LOCK_EX);
  $h = json_decode(stream_get_contents($fh), true); $t = ms();
  if (!is_array($h) || ($h['reset'] ?? 0) < $t) $h = ['n' => 0, 'reset' => $t + $windowMs];
  $h['n']++;
  ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($h)); fflush($fh); flock($fh, LOCK_UN); fclose($fh);
  if (random_int(1, 50) === 1) foreach (glob($dir . '/*.json') ?: [] as $f) if (@filemtime($f) < time() - 86400) @unlink($f);
  if ($h['n'] > $max) { header('Retry-After: ' . (int)ceil(($h['reset'] - $t) / 1000)); fail('Too many attempts. Please try again later.', 429); }
}

/* ---------- sessions ---------- */
function set_session_cookie(string $v, int $maxAge): void {
  setcookie(cookie_name(), $v, ['expires' => $maxAge > 0 ? time() + $maxAge : 1, 'path' => '/', 'secure' => secure(), 'httponly' => true, 'samesite' => 'Lax']);
}
function start_session(int $uid, bool $admin = false): void {
  $t = rnd(); $len = $admin ? ADMIN_SESSION_MS : SESSION_MS;
  run('INSERT INTO sessions(token_hash,user_id,created_at,expires_at) VALUES(?,?,?,?)', [sha($t), $uid, ms(), ms() + $len]);
  set_session_cookie($t, intdiv($len, 1000));
}
function mail_enabled(): bool { return (string)cfg('SMTP_URL') !== ''; }
function mail_dev_log(): bool { return !mail_enabled() && cfg('DEV_MAIL_TO_LOG') && !prod(); }
function admin_ok(): bool { return mail_enabled() || !prod(); }
function is_admin_row(array $u): bool { return admin_ok() && (bool)$u['email_verified'] && ($u['role'] ?? '') === 'ADMIN'; }   // the role lives in the database, never in a config file
function current_user(): ?array {
  $sid = $_COOKIE[cookie_name()] ?? '';
  if (!is_string($sid) || !preg_match('/^[a-f0-9]{64}$/D', $sid)) return null;
  $r = q1('SELECT u.id,u.full_name,u.email,u.email_verified,u.role,u.active,s.created_at AS s_created,s.expires_at FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=?', [sha($sid)]);
  if (!$r) return null;
  if (!(int)$r['active']) { run('DELETE FROM sessions WHERE token_hash=?', [sha($sid)]); return null; }   // deactivated accounts are signed out
  if ($r['role'] === 'ADMIN' && ms() - (int)$r['s_created'] > ADMIN_SESSION_MS) { run('DELETE FROM sessions WHERE token_hash=?', [sha($sid)]); return null; }   // an older long session of an account that became admin
  if ((int)$r['expires_at'] <= ms()) { run('DELETE FROM sessions WHERE token_hash=?', [sha($sid)]); return null; }
  return ['id' => (int)$r['id'], 'fullName' => $r['full_name'], 'email' => $r['email'], 'emailVerified' => (bool)$r['email_verified'], 'role' => $r['role'],
          'isAdmin' => is_admin_row($r)];
}
function public_user(?array $u): ?array { return $u ? ['fullName' => $u['fullName'], 'email' => $u['email'], 'emailVerified' => $u['emailVerified'], 'role' => $u['role'], 'isAdmin' => !empty($u['isAdmin'])] : null; }
function admin_log(?int $adminId, string $action, string $target = '', string $detail = '', string $name = ''): void {
  $n = $adminId ? q1('SELECT full_name FROM users WHERE id=?', [$adminId]) : null;
  run('INSERT INTO admin_log(admin_id,admin_name,action,target,detail,created_at) VALUES(?,?,?,?,?,?)', [$adminId, $n ? $n['full_name'] : mb_substr($name, 0, 100), mb_substr($action, 0, 60), mb_substr($target, 0, 60), mb_substr($detail, 0, 500), now()]);
}
function admin_actor(?array $set = null): ?int { static $id = null; if ($set) $id = (int)$set['id']; return $id; }
function purge_expired(): void {   // replaces the Node server's hourly timer
  if (random_int(1, 100) !== 1) return;
  run('DELETE FROM sessions WHERE expires_at<?', [ms()]); run('DELETE FROM tokens WHERE expires_at<?', [ms()]);
}

/* ---------- one-time tokens (email verification / password reset) ---------- */
function send_token(int $uid, string $email, string $name, string $purpose): void {
  run('DELETE FROM tokens WHERE user_id=? AND purpose=?', [$uid, $purpose]);
  $t = rnd();
  run('INSERT INTO tokens(token_hash,user_id,purpose,expires_at) VALUES(?,?,?,?)', [sha($t), $uid, $purpose, ms() + ($purpose === 'verify' ? 24 : 1) * 3600000]);
  $url = base_url() . '/' . ($purpose === 'verify' ? 'verify' : 'reset') . '.html#token=' . $t;   // fragment: never sent to servers/referrers
  send_mail($email,
    $purpose === 'verify' ? 'Verify your Bella Vista Oasis Spa account' : 'Reset your Bella Vista Oasis Spa password',
    $purpose === 'verify'
      ? "Hello $name,\n\nPlease confirm your email address to finish setting up your account:\n$url\n\nThis link expires in 24 hours. If you did not create an account, you can ignore this message."
      : "Hello $name,\n\nUse this link to choose a new password:\n$url\n\nThis link expires in 1 hour. If you did not request this, you can ignore this message.");
}
function take_token($raw, string $purpose): ?int {
  if (!is_string($raw) || !preg_match('/^[a-f0-9]{64}$/D', $raw)) return null;
  $r = q1('SELECT user_id FROM tokens WHERE token_hash=? AND purpose=? AND expires_at>?', [sha($raw), $purpose, ms()]);
  if (!$r) return null;
  return run('DELETE FROM tokens WHERE token_hash=?', [sha($raw)])->rowCount() === 1 ? (int)$r['user_id'] : null;   // single use, even under a race
}

/* ---------- mail: real SMTP only (XAMPP's PHP mail() is not configured out of the box) ---------- */
function send_mail(string $to, string $subject, string $text): void {
  if (mail_enabled()) { smtp_send($to, $subject, $text); return; }
  if (mail_dev_log()) {
    $dir = ROOT . '/data'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    file_put_contents($dir . '/mail.log', "\n[DEV MAIL " . gmdate('c') . "] to=$to\nsubject=$subject\n$text\n", FILE_APPEND | LOCK_EX);
    return;
  }
  throw new RuntimeException('mail not configured');
}
function smtp_read($fp): array {
  $code = 0; $all = '';
  while (($line = fgets($fp, 1024)) !== false) { $all .= $line; $code = (int)substr($line, 0, 3); if (strlen($line) < 4 || $line[3] !== '-') break; }
  return [$code, $all];
}
function smtp_cmd($fp, string $cmd, array $ok): string {
  if ($cmd !== '') fwrite($fp, $cmd . "
");
  [$code, $all] = smtp_read($fp);
  if (!in_array($code, $ok, true)) throw new RuntimeException('SMTP error ' . $code);
  return $all;
}
function smtp_send(string $to, string $subject, string $text): void {
  $u = parse_url((string)cfg('SMTP_URL'));
  if (!$u || empty($u['host'])) throw new RuntimeException('Invalid SMTP_URL');
  $ssl = ($u['scheme'] ?? '') === 'smtps'; $port = $u['port'] ?? ($ssl ? 465 : 587);
  $user = isset($u['user']) ? urldecode($u['user']) : ''; $pass = isset($u['pass']) ? urldecode($u['pass']) : '';
  $from = (string)(cfg('MAIL_FROM') ?: 'Bella Vista Oasis Spa <no-reply@localhost>');
  $fromAddr = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
  $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $u['host'] . ':' . $port, $en, $es, 15);
  if (!$fp) throw new RuntimeException('SMTP connect failed');
  stream_set_timeout($fp, 15);
  try {
    smtp_cmd($fp, '', [220]);
    $ehlo = 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    smtp_cmd($fp, $ehlo, [250]);
    if (!$ssl && $port != 25) {
      smtp_cmd($fp, 'STARTTLS', [220]);
      if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS failed');
      smtp_cmd($fp, $ehlo, [250]);
    }
    if ($user !== '') { smtp_cmd($fp, 'AUTH LOGIN', [334]); smtp_cmd($fp, base64_encode($user), [334]); smtp_cmd($fp, base64_encode($pass), [235]); }
    smtp_cmd($fp, 'MAIL FROM:<' . $fromAddr . '>', [250]);
    smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_cmd($fp, 'DATA', [354]);
    $headers = "Date: " . gmdate('r') . "
From: $from
To: <$to>
Subject: =?UTF-8?B?" . base64_encode($subject) . "?=
"
      . "Message-ID: <" . bin2hex(random_bytes(8)) . "@bellavista>
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: base64
";
    $body = chunk_split(base64_encode($text), 76, "
");
    smtp_cmd($fp, $headers . "
" . $body . "
.", [250]);
    @fwrite($fp, "QUIT
");
  } finally { fclose($fp); }
}

/* ---------- catalog: single source of truth is js/data.js (same as the Node version) ---------- */
function catalog(): array {
  static $c = null; if ($c) return $c;
  $js = (string)@file_get_contents(ROOT . '/js/data.js');
  $dec = fn($s) => json_decode('"' . $s . '"') ?? $s;
  $S = '"((?:[^"\\\\]|\\\\.)*)"';
  $services = $rooms = [];
  if (preg_match_all('/\{\s*name:\s*' . $S . '\s*,\s*cat:\s*' . $S . '\s*,\s*desc:\s*' . $S . '/', $js, $m, PREG_SET_ORDER))
    foreach ($m as $x) $services[] = ['name' => $dec($x[1]), 'cat' => $dec($x[2]), 'desc' => $dec($x[3])];
  if (preg_match_all('/\{\s*name:\s*' . $S . '\s*,\s*desc:\s*' . $S . '/', $js, $m, PREG_SET_ORDER))
    foreach ($m as $x) $rooms[] = ['name' => $dec($x[1]), 'desc' => $dec($x[2])];
  /* Services and accommodations saved in the admin dashboard are layered on top of js/data.js: same name = replaces the text, new name = added. */
  $o = content_overlay();
  foreach ($o['services'] as $x) { $i = array_search($x['name'], array_column($services, 'name'), true);
    if ($i !== false) { $services[$i] = ['name' => $x['name'], 'cat' => $x['cat'] ?: $services[$i]['cat'], 'desc' => $x['desc'] ?: $services[$i]['desc']]; } else $services[] = ['name' => $x['name'], 'cat' => $x['cat'] ?: 'Wellness', 'desc' => $x['desc']]; }
  foreach ($o['rooms'] as $x) { $i = array_search($x['name'], array_column($rooms, 'name'), true);
    if ($i !== false) { $rooms[$i] = ['name' => $x['name'], 'desc' => $x['desc'] ?: $rooms[$i]['desc']]; } else array_splice($rooms, max(0, count($rooms) - 1), 0, [['name' => $x['name'], 'desc' => $x['desc']]]); }   // new rooms go before the featured (last) one
  return $c = ['services' => $services, 'rooms' => $rooms];
}
/* ---------- website content saved in the admin dashboard ---------- */
const CONTENT_SECTIONS = ['Brand statement', 'Address', 'Phone', 'Email', 'Opening hours', 'Accommodation information', 'Room amenities'];
function image_path_ok(string $p): bool {   // gallery images must be files inside the site's assets/ folder
  return strpos($p, '..') === false && (bool)preg_match('#^assets/[A-Za-z0-9_][A-Za-z0-9_ ./-]{0,180}\.(?:jpe?g|png|webp|gif|avif)$#i', $p);
}
function content_check(string $key, array $items): ?string {   // null = fine, otherwise a message the admin can act on
  foreach ($items as $x) {
    $g = fn($f) => trim(str($x[$f] ?? ''));
    if ($key === 'faqs') {
      if ($g('q') === '' || $g('a') === '') return 'Every FAQ needs a question and an answer.';
      if (mb_strlen($g('q')) > 200 || mb_strlen($g('a')) > 2000) return 'A FAQ is too long (question up to 200 characters, answer up to 2000).';
    } elseif ($key === 'wellness') {
      if ($g('name') === '' || mb_strlen($g('name')) > 100) return 'Enter a name for the wellness item (100 characters at most).';
      if (mb_strlen($g('desc')) > 1000 || mb_strlen($g('category')) > 60) return 'The description (1000) or category (60) is too long.';
    } elseif ($key === 'gallery') {
      if (!image_path_ok($g('path'))) return 'The image path must start with assets/ and end in .jpg, .png, .webp or .gif, for example assets/images/spa.jpg.';
      if (mb_strlen($g('title')) > 100) return 'The image title is too long (100 characters at most).';
    } elseif ($key === 'content') {
      $sec = $g('section'); $t = $g('text');
      if (!in_array($sec, CONTENT_SECTIONS, true)) return 'Choose one of the listed sections.';
      if ($t === '' || mb_strlen($t) > 1000) return 'Enter the text (1000 characters at most).';
      if ($sec === 'Email' && !preg_match('/^[^\s"\'<>@]+@[^\s"\'<>@]+\.[^\s"\'<>@]+$/', $t)) return 'Enter a valid email address.';
      if ($sec === 'Phone' && !preg_match('/^[+\d][\d\s()-]{5,30}$/', $t)) return 'Enter a valid phone number.';
    }
  }
  return null;
}
function content_overlay(): array {   // clean, size-limited copy of what the admin saved; safe to send to any visitor
  $out = ['services' => [], 'rooms' => [], 'wellness' => [], 'faqs' => [], 'gallery' => [], 'content' => []];
  try {
    foreach (array_keys($out) as $k) {
      $r = q1('SELECT json FROM content WHERE content_key=?', [$k]);
      $rows = jparse($r['json'] ?? '[]', []);
      if (in_array($k, ['wellness', 'faqs', 'gallery'], true)) $rows = array_reverse($rows);   // admin lists newest first; visitors see them in the order added
      foreach ($rows as $x) {
        if (!is_array($x)) continue;
        $t = fn($f, $n) => is_string($x[$f] ?? null) ? mb_substr(trim($x[$f]), 0, $n) : '';
        if ($k === 'services' || $k === 'rooms' || $k === 'wellness') {
          $n = $t('name', 100); if ($n === '') continue;
          if ($k === 'wellness' && content_check($k, [$x]) !== null) continue;
          $out[$k][] = ['name' => $n, 'cat' => $t('category', 60), 'desc' => is_string($x['desc'] ?? null) ? mb_substr($x['desc'], 0, 1000) : ''];
        } elseif (content_check($k, [$x]) === null) {
          if ($k === 'faqs') $out[$k][] = ['q' => $t('q', 200), 'a' => $t('a', 2000)];
          elseif ($k === 'gallery') $out[$k][] = ['title' => $t('title', 100), 'path' => $t('path', 200)];
          else $out[$k][] = ['section' => $t('section', 60), 'text' => $t('text', 1000)];
        }
      }
    }
  } catch (Throwable $e) { /* database unavailable: fall back to js/data.js only */ }
  return $out;
}
