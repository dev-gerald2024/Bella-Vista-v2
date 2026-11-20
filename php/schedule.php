<?php
/* Bella Vista Oasis Spa - opening hours, time slots, cleaning buffer,
   service duration management, and double-booking protection.
   Phase 4.1: 1:00 PM - 9:00 PM operating hours, 30-min cleaning buffer,
   both treatment and cleaning must finish before 9:00 PM closing. */

const BLOCKING_STATUSES = ['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED'];   // these hold a time slot; CANCELLED, NO-SHOW, and REJECTED release it

function hm_to_min(string $t): int { return (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2); }
function min_to_hm(int $m): string { return sprintf('%02d:%02d', intdiv($m, 60), $m % 60); }
function fmt12(int $m): string { $h = intdiv($m, 60); return (($h % 12) ?: 12) . ':' . sprintf('%02d', $m % 60) . ' ' . ($h < 12 ? 'AM' : 'PM'); }

function cfg_int(string $k, int $def, int $min, int $max): int { $v = cfg($k); return is_int($v) || (is_string($v) && ctype_digit($v)) ? max($min, min($max, (int)$v)) : $def; }
function service_cap(): int { return cfg_int('SERVICE_CAPACITY', 1, 1, 50); }    // same service at the same time
function total_cap(): int { return cfg_int('TOTAL_CAPACITY', 3, 1, 100); }      // everything at the same time (rooms / therapists)
function slot_step(): int { return cfg_int('SLOT_STEP', 30, 5, 240); }          // start times are offered every N minutes
function min_notice(): int { return cfg_int('MIN_NOTICE_MINUTES', 60, 0, 1440); }
function window_days(): int { return cfg_int('BOOKING_WINDOW_DAYS', 90, 1, 730); }
function buffer_minutes(): int { return cfg_int('BUFFER_MINUTES', 30, 0, 180); }
function min_guests(): int { return cfg_int('MIN_GUESTS', 1, 1, 50); }
function max_guests(): int { return cfg_int('MAX_GUESTS', 6, 1, 50); }

/* [open, close] in minutes after midnight, or null when the spa is closed that day */
function spa_hours(string $date): ?array {
  if (in_array($date, (array)cfg('CLOSED_DATES'), true)) return null;
  $dow = (int)(new DateTime($date, new DateTimeZone('Asia/Manila')))->format('N');   // 1 = Monday ... 7 = Sunday
  if (in_array($dow, array_map('intval', (array)cfg('CLOSED_WEEKDAYS')), true)) return null;
  $o = is_time((string)cfg('OPEN_TIME')) ? hm_to_min((string)cfg('OPEN_TIME')) : 13 * 60; // 1:00 PM default
  $c = is_time((string)cfg('CLOSE_TIME')) ? hm_to_min((string)cfg('CLOSE_TIME')) : 21 * 60; // 9:00 PM default
  return $c > $o ? [$o, $c] : null;
}

/* Service duration configuration lookup */
function service_duration_config(string $service): ?array {
  $durations = cfg('SERVICE_DURATIONS');
  if (!is_array($durations)) return null;
  return $durations[$service] ?? null;
}

/* Validate service and selected duration.
   Rejects services without management duration config (e.g. 'Other Spa Treatments', 'Not sure yet').
   Returns ['ok' => true, 'minutes' => int] or ['ok' => false, 'error' => string, 'code' => string]. */
function validate_service_duration(string $service, $duration, bool $requireDuration = true): array {
  $conf = service_duration_config($service);
  if (!$conf) {
    return [
      'ok' => false,
      'error' => "The selected service ('$service') requires management duration configuration before booking. Please contact the spa directly.",
      'code' => 'unconfigured_service',
      'field' => 'interest'
    ];
  }

  if ($duration === null || $duration === '') {
    if ($requireDuration) {
      return [
        'ok' => false,
        'error' => "Please select a duration for $service.",
        'code' => 'duration_required',
        'field' => 'duration'
      ];
    }
    return ['ok' => true, 'minutes' => (int)($conf['default'] ?? $conf['min'])];
  }

  if (!is_numeric($duration)) {
    return [
      'ok' => false,
      'error' => "Please choose a valid duration for $service.",
      'code' => 'invalid_duration',
      'field' => 'duration'
    ];
  }

  $durInt = (int)$duration;
  $allowedOptions = (array)($conf['options'] ?? [$conf['min'], $conf['max']]);
  if (!in_array($durInt, $allowedOptions, true) && ($durInt < $conf['min'] || $durInt > $conf['max'])) {
    $optsStr = implode(', ', $allowedOptions);
    return [
      'ok' => false,
      'error' => "Invalid duration for $service ($durInt min). Valid options: {$optsStr} minutes (range {$conf['min']}–{$conf['max']} min).",
      'code' => 'invalid_duration',
      'field' => 'duration'
    ];
  }

  return ['ok' => true, 'minutes' => $durInt];
}

/* how long a treatment lasts in minutes */
function service_minutes(string $service, ?string $duration = null): int {
  $v = validate_service_duration($service, $duration, false);
  if ($v['ok']) return $v['minutes'];
  if ($duration !== null && preg_match('/^\d{1,4}$/D', (string)$duration) && (int)$duration >= 15 && (int)$duration <= 480) {
    return (int)$duration;
  }
  return 0; // Unconfigured
}

/* Guest count validation helper */
function validate_guests($guests): array {
  if ($guests === null || $guests === '' || !is_numeric($guests)) {
    return ['ok' => false, 'error' => 'Please enter a valid guest count (1 to 6).', 'code' => 'invalid_guests', 'field' => 'guests'];
  }
  $n = (int)$guests;
  if ($n < min_guests() || $n > max_guests()) {
    return ['ok' => false, 'error' => 'Guest count must be between ' . min_guests() . ' and ' . max_guests() . ' guests.', 'code' => 'invalid_guests', 'field' => 'guests'];
  }
  return ['ok' => true, 'guests' => $n];
}

/**
 * Calculates official service rate based on service, duration, and guest count.
 * Rates from Complete Revised Project Documentation v2.2 (Section 2, Page 2):
 * - Body Massage: 60m => ₱800, 90m => ₱1,100
 * - Foot Massage: 30m => ₱450, 45m => ₱600
 * - Body Scrub: 45m => ₱800, 60m => ₱1,000
 * - Facial Treatment: 45m => ₱700, 60m => ₱900
 * - Hair and Scalp Treatment: 30m => ₱500, 45m => ₱700
 */
function get_service_price(string $service, int $durationMinutes, int $guests = 1): float {
  $conf = service_duration_config($service);
  $base = 800.0;
  if ($conf && !empty($conf['prices']) && isset($conf['prices'][$durationMinutes])) {
    $base = (float)$conf['prices'][$durationMinutes];
  } elseif ($conf && !empty($conf['prices']) && isset($conf['default'], $conf['prices'][$conf['default']])) {
    $base = (float)$conf['prices'][$conf['default']];
  }
  return round($base * max(1, $guests), 2);
}

function local_now_minutes(): int {
  $n = new DateTime('now', new DateTimeZone(cfg('TIMEZONE') ?: 'Asia/Manila'));
  return (int)$n->format('G') * 60 + (int)$n->format('i');
}

function days_from_today(string $date): int {
  $tz = new DateTimeZone(cfg('TIMEZONE') ?: 'Asia/Manila');
  $t = new DateTime(today(), $tz);
  $d = new DateTime($date, $tz);
  return (int)$t->diff($d)->format('%r%a');
}

/* The day's bookings that currently hold a slot.
   IMPORTANT (Part 3 & Part 5):
   Occupied interval = [Appointment Start, Appointment Start + Treatment Duration + 30-min cleaning buffer).
   Both treatment and cleaning block the resource from another booking! */
function day_intervals(string $date, ?int $excludeId = null): array {
  $buf = buffer_minutes();
  $sql = "SELECT id, user_id, service, time, duration FROM bookings WHERE preferred_date=? AND archived=0 AND time<>'' AND status IN ('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED')";
  $args = [$date];
  if ($excludeId) {
    $sql .= ' AND id<>?';
    $args[] = $excludeId;
  }
  return array_map(function ($r) use ($buf) {
    $s = hm_to_min($r['time']);
    $dur = service_minutes($r['service'], $r['duration']) ?: 60;
    return [
      'id'   => (int)$r['id'],
      'uid'  => $r['user_id'] ? (int)$r['user_id'] : null,
      'svc'  => $r['service'],
      'dur'  => $dur,
      's'    => $s,
      'e'    => $s + $dur + $buf, // Occupied end time includes the 30-min cleaning buffer
    ];
  }, qa($sql, $args));
}

/* Highest number of intervals running at the same instant anywhere inside [s, e) */
function peak(array $iv, int $s, int $e): int {
  $pts = [$s];
  foreach ($iv as $x) if ($x['s'] > $s && $x['s'] < $e) $pts[] = $x['s'];
  $max = 0;
  foreach ($pts as $p) {
    $n = 0;
    foreach ($iv as $x) if ($x['s'] <= $p && $p < $x['e']) $n++;
    $max = max($max, $n);
  }
  return $max;
}

/* Returns null when the slot is fine, otherwise [message, field, code].
   Enforces:
   1. Operating hours: 1:00 PM to 9:00 PM (13:00 to 21:00).
   2. Strict closing-time rule: Treatment AND 30-min cleaning must finish before 9:00 PM.
   3. Service duration validation: must be configured and valid.
   4. Double-booking protection: includes cleaning buffer in peak capacity calculations.
   5. Customer conflict prevention (Audit F2): same customer cannot hold overlapping appointments.
   6. Open bookings cap (Audit F3): max active bookings per customer. */
function slot_error(string $date, string $time, string $service, ?string $duration, ?int $excludeId, bool $customer, ?array $iv = null, ?int $userId = null): ?array {
  $h = spa_hours($date);
  if (!$h) return ['The spa is closed on that date. Please choose another day.', 'date', 'closed'];

  // Validate service duration
  $vd = validate_service_duration($service, $duration, $customer);
  if (!$vd['ok']) {
    return [$vd['error'], $vd['field'], $vd['code']];
  }
  $len = $vd['minutes'];
  $buf = buffer_minutes();

  $s = hm_to_min($time);
  $occ_end = $s + $len + $buf;

  // Strict closing-time and operating-hours check:
  // Both treatment and cleaning must finish by closing time (9:00 PM / $h[1])
  if ($s < $h[0] || $occ_end > $h[1]) {
    $last = $h[1] - $len - $buf;
    if ($s < $h[0]) {
      return ['Appointments start at ' . fmt12($h[0]) . '. Please choose a time during operating hours (' . fmt12($h[0]) . ' to ' . fmt12($h[1]) . ').', 'time', 'outside_hours'];
    }
    return [
      'Appointments run from ' . fmt12($h[0]) . ' to ' . fmt12($h[1]) . '. Both treatment (' . $len . ' min) and mandatory 30-min cleaning must finish by ' . fmt12($h[1]) . ($last >= $h[0] ? ', so the latest start is ' . fmt12($last) . '.' : '. This treatment is too long for the remaining hours.'),
      'time',
      'outside_hours'
    ];
  }

  if ($customer) {
    if (($s - $h[0]) % slot_step() !== 0) return ['Please choose one of the listed start times.', 'time', 'bad_slot'];
    if ($date === today() && $s < local_now_minutes() + min_notice()) return ['That time has passed or is too soon. Please choose a later time.', 'time', 'too_soon'];
    if (days_from_today($date) > window_days()) return ['We take online bookings up to ' . window_days() . ' days ahead. Please choose an earlier date.', 'date', 'too_far'];

    // Audit F3: check open bookings cap for this customer
    if ($userId) {
      $maxOpen = cfg_int('MAX_OPEN_BOOKINGS_PER_CUSTOMER', 5, 1, 50);
      $openCount = (int)q1("SELECT COUNT(*) AS c FROM bookings WHERE user_id=? AND archived=0 AND status IN ('PENDING','CONFIRMED','IN_PROGRESS') AND preferred_date >= ?", [$userId, today()])['c'];
      if ($openCount >= $maxOpen) {
        return ["You have reached the maximum limit of $maxOpen active appointments. Please wait until your existing appointments are completed before booking again.", 'general', 'too_many_open'];
      }
    }
  }

  $iv ??= day_intervals($date, $excludeId);

  // Audit F2: check that the same customer has no overlapping active booking
  if ($userId) {
    foreach ($iv as $x) {
      if (!empty($x['uid']) && (int)$x['uid'] === (int)$userId) {
        if (max($x['s'], $s) < min($x['e'], $occ_end)) {
          return ['You already have another appointment scheduled during this time window (including sanitization period). Please select a different time.', 'time', 'customer_conflict'];
        }
      }
    }
  }

  // Check service-level capacity (e.g. 1 Body Massage at the same time, including cleaning buffer)
  if ($service !== '' && peak(array_values(array_filter($iv, fn($x) => $x['svc'] === $service)), $s, $occ_end) >= service_cap()) {
    return ['That time is already booked for this treatment (including cleaning buffer). Please choose another time.', 'time', 'slot_taken'];
  }

  // Check total spa capacity (e.g. 3 treatments/cleanings at the same time)
  if (peak($iv, $s, $occ_end) >= total_cap()) {
    return ['The spa is fully booked at that time (including cleaning buffer). Please choose another time.', 'time', 'slot_taken'];
  }

  return null;
}

function slot_fail(array $e): never {
  fail($e[0], $e[2] === 'slot_taken' || $e[2] === 'customer_conflict' ? 409 : 400, ['field' => $e[1], 'code' => $e[2]]);
}

/* Two requests for the same slot arriving at the same moment must not both pass the check:
   one MySQL lock per calendar day makes "check, then save" a single step. */
function with_day_lock(string $date, callable $fn) {
  $name = 'bv_day_' . $date;
  $row = q1('SELECT GET_LOCK(?, 8) AS l', [$name]);
  if (!$row || (int)$row['l'] !== 1) fail('The booking system is busy. Please try again in a moment.', 503);
  try {
    return $fn();
  } finally {
    try { q1('SELECT RELEASE_LOCK(?) AS r', [$name]); } catch (Throwable $e) {}
  }
}

/* check the slot, and only if it is free run $then (the INSERT / UPDATE) inside the same lock */
function guard_slot(string $date, string $time, string $service, ?string $duration, ?int $excludeId, bool $customer, callable $then, ?int $userId = null) {
  return with_day_lock($date, function () use ($date, $time, $service, $duration, $excludeId, $customer, $then, $userId) {
    $e = slot_error($date, $time, $service, $duration, $excludeId, $customer, null, $userId);
    if ($e) slot_fail($e);
    return $then();
  });
}

/* public: which start times are free for a date, service, and duration */
function api_availability(): void {
  rate_limit('avail', 240, 6e4, [], false);
  $date = str($_GET['date'] ?? '');
  $service = trim(str($_GET['service'] ?? ''));
  $duration = trim(str($_GET['duration'] ?? ''));

  if (!is_date($date) || $date < today()) fail('Choose a date from today onward.', 400, ['field' => 'date']);
  if (days_from_today($date) > window_days()) fail('We take online bookings up to ' . window_days() . ' days ahead.', 400, ['field' => 'date']);

  if ($service === '' || $service === 'Not sure yet') {
    fail('Please choose a valid treatment service to view available times.', 400, ['field' => 'interest', 'code' => 'service_required']);
  }

  $vd = validate_service_duration($service, $duration !== '' ? $duration : null, false);
  if (!$vd['ok']) {
    fail($vd['error'], 400, ['field' => $vd['field'], 'code' => $vd['code']]);
  }
  $len = $vd['minutes'];
  $buf = buffer_minutes();

  $h = spa_hours($date);
  if (!$h) out(['date' => $date, 'open' => false, 'slots' => []]);

  $own = null;
  $uid = null;
  $ex = str($_GET['exclude'] ?? '');
  if (($u = current_user())) {
    $uid = (int)$u['id'];
    if ($ex !== '') {
      $xid = parse_ref($ex, 'BK');
      if ($xid && q1('SELECT 1 AS x FROM bookings WHERE id=? AND user_id=?', [$xid, $uid])) $own = $xid;
    }
  }

  $iv = day_intervals($date, $own);
  $slots = [];

  // Strictly offer only slots where treatment AND cleaning finish before closing (h[1])
  for ($s = $h[0]; $s + $len + $buf <= $h[1]; $s += slot_step()) {
    $t = min_to_hm($s);
    $err = slot_error($date, $t, $service, (string)$len, $own, true, $iv, $uid);
    $slots[] = [
      'time' => $t,
      'free' => ($err === null),
    ];
  }

  out([
    'date'    => $date,
    'open'    => true,
    'opens'   => min_to_hm($h[0]),
    'closes'  => min_to_hm($h[1]),
    'minutes' => $len,
    'buffer'  => $buf,
    'price'   => get_service_price($service, $len, 1),
    'slots'   => $slots
  ]);
}

/* the opening hours as shown to visitors, built from the configured settings, e.g. "Daily, 1:00 PM - 9:00 PM" */
function hours_text(): string {
  $o = is_time((string)cfg('OPEN_TIME')) ? hm_to_min((string)cfg('OPEN_TIME')) : 13 * 60;
  $c = is_time((string)cfg('CLOSE_TIME')) ? hm_to_min((string)cfg('CLOSE_TIME')) : 21 * 60;
  if ($c <= $o) return '';
  $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
  $closed = array_map('intval', (array)cfg('CLOSED_WEEKDAYS'));
  $open = array_values(array_diff(array_keys($names), $closed));
  if (!$open) return 'Closed';
  if (count($open) === 7) $days = 'Daily';
  else {
    $runs = []; $start = $prev = null;
    foreach ($open as $d) {
      if ($start === null) { $start = $prev = $d; }
      elseif ($d === $prev + 1) { $prev = $d; }
      else { $runs[] = [$start, $prev]; $start = $prev = $d; }
    }
    $runs[] = [$start, $prev];
    $days = implode(', ', array_map(fn($r) => $r[0] === $r[1] ? $names[$r[0]] : $names[$r[0]] . ' - ' . $names[$r[1]], $runs));
  }
  return $days . ', ' . fmt12($o) . ' - ' . fmt12($c);
}
