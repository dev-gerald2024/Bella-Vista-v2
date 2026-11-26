<?php
/**
 * Bella Vista Oasis Spa - Phase 5.0 Room, Package & Therapist Management
 * Implements physical room inventory, package management, therapist schedules,
 * appointment resource assignments, and room cleaning workflows.
 */

/* ============================================================================
   1. ROOM CATEGORIES & PACKAGES
   ============================================================================ */

function get_room_categories(): array {
  return qa('SELECT * FROM room_categories ORDER BY min_guests ASC, code ASC');
}

function get_room_category(string $code): ?array {
  return q1('SELECT * FROM room_categories WHERE code = ?', [$code]);
}

function get_packages(bool $activeOnly = false): array {
  $sql = 'SELECT p.*, rc.name AS category_name FROM packages p JOIN room_categories rc ON rc.code = p.category_code';
  if ($activeOnly) $sql .= " WHERE p.package_status = 'ACTIVE'";
  $sql .= ' ORDER BY p.min_guests ASC, p.id ASC';
  return qa($sql);
}

function get_package(int $id): ?array {
  return q1('SELECT p.*, rc.name AS category_name FROM packages p JOIN room_categories rc ON rc.code = p.category_code WHERE p.id = ?', [$id]);
}

function get_package_by_code(string $code): ?array {
  return q1('SELECT p.*, rc.name AS category_name FROM packages p JOIN room_categories rc ON rc.code = p.category_code WHERE p.package_code = ?', [$code]);
}

/**
 * Calculates package price based on documented pricing rules:
 * - Single Room: ₱800
 * - Couples Suite: ₱1,800
 * - Family Spa Lounge: ₱4,000 base (up to 4 guests) + ₱750 per guest above 4 (max 6)
 */
function calculate_package_price(array $pkg, int $guests): float {
  $base = (float)$pkg['base_price'];
  $std = (int)$pkg['standard_capacity'];
  $addlRate = (float)$pkg['additional_guest_price'];
  if ($guests > $std && $addlRate > 0) {
    $extraGuests = $guests - $std;
    return $base + ($extraGuests * $addlRate);
  }
  return $base;
}

/**
 * Which package (room-category rate card) a new booking is linked to, decided on the server.
 * A package is picked by code or id (the booking form sends the package of the chosen room's category). It must exist, be ACTIVE,
 * belong to the same category as the booked room and fit the guest count. If the customer sent nothing and the pricing mode does
 * not change the price (INDEPENDENT / INCLUSIVE), the active package of the room's category is linked automatically; in ADDITIVE
 * mode nothing is added to a price unless the package was explicitly chosen.
 */
function resolve_booking_package(array $b, array $room, int $guests): ?array {
  $ref = trim((string)($b['packageCode'] ?? $b['packageId'] ?? ''));
  $mode = strtoupper((string)(cfg('PACKAGE_PRICING_RELATIONSHIP') ?: 'INDEPENDENT'));
  if ($ref === '') {
    if ($mode === 'ADDITIVE') return null;
    $p = q1("SELECT p.*, rc.name AS category_name FROM packages p JOIN room_categories rc ON rc.code = p.category_code WHERE p.category_code = ? AND p.package_status = 'ACTIVE' AND ? BETWEEN p.min_guests AND p.max_capacity ORDER BY p.id LIMIT 1", [$room['category_code'], $guests]);
    return $p ?: null;
  }
  $pkg = ctype_digit($ref) ? get_package((int)$ref) : get_package_by_code(strtoupper($ref));
  if (!$pkg) fail('The selected package does not exist.', 400, ['field' => 'package', 'code' => 'package_not_found']);
  if ($pkg['package_status'] !== 'ACTIVE') fail('The selected package is not available right now.', 409, ['field' => 'package', 'code' => 'package_inactive']);
  if ($pkg['category_code'] !== $room['category_code']) fail("Package {$pkg['name']} is for a different room type than {$room['room_code']}.", 400, ['field' => 'package', 'code' => 'package_room_mismatch']);
  if ($guests < (int)$pkg['min_guests'] || $guests > (int)$pkg['max_capacity']) fail("Package {$pkg['name']} is for {$pkg['min_guests']}-{$pkg['max_capacity']} guest(s); this booking has $guests.", 400, ['field' => 'package', 'code' => 'package_capacity']);
  return $pkg;
}
/* Booking total from the treatment price and the package, following PACKAGE_PRICING_RELATIONSHIP:
   INDEPENDENT (default) and INCLUSIVE: the treatment price is the total (the package rate is tracked separately / already covered);
   ADDITIVE: treatment price + package price. */
function booking_total_with_package(float $treatment, ?array $pkg, int $guests): float {
  $mode = strtoupper((string)(cfg('PACKAGE_PRICING_RELATIONSHIP') ?: 'INDEPENDENT'));
  if ($mode === 'ADDITIVE' && $pkg) return round($treatment + calculate_package_price($pkg, $guests), 2);
  return round($treatment, 2);
}

function save_package(array $d, ?int $id = null): array {
  $code = strtoupper(trim(clean_name($d['package_code'] ?? $d['code'] ?? '')));
  $name = trim(clean_name($d['name'] ?? ''));
  $cat = strtoupper(trim($d['category_code'] ?? $d['category'] ?? ''));
  if ($cat === 'SINGLE ROOM MASSAGE' || $cat === 'SINGLE') $cat = 'SINGLE';
  elseif ($cat === 'COUPLES SUITE' || $cat === 'COUPLES') $cat = 'COUPLES';
  elseif ($cat === 'FAMILY SPA LOUNGE' || $cat === 'FAMILY') $cat = 'FAMILY';
  $minG = max(1, (int)($d['min_guests'] ?? $d['minGuests'] ?? 1));
  $stdCap = max($minG, (int)($d['standard_capacity'] ?? $d['stdCap'] ?? $minG));
  $maxCap = max($stdCap, (int)($d['max_capacity'] ?? $d['maxCap'] ?? $stdCap));
  $dur = max(15, (int)($d['default_duration'] ?? $d['duration'] ?? 60));
  $basePrice = max(0.0, (float)($d['base_price'] ?? $d['basePrice'] ?? 0));
  $addlPrice = max(0.0, (float)($d['additional_guest_price'] ?? $d['extraGuestPrice'] ?? 0));
  $status = in_array($d['package_status'] ?? $d['status'] ?? 'ACTIVE', ['ACTIVE', 'INACTIVE'], true) ? ($d['package_status'] ?? $d['status']) : 'ACTIVE';
  $desc = clean_text($d['description'] ?? $d['desc'] ?? '', 500);
  $notes = clean_text($d['management_notes'] ?? $d['notes'] ?? '', 500);

  if ($code === '') fail('Please enter a package code (e.g. PKG-SR).', 400);
  if ($name === '') fail('Please enter a package name.', 400);
  if (!get_room_category($cat)) fail('Invalid room category code.', 400);

  if ($id) {
    run('UPDATE packages SET package_code=?, name=?, category_code=?, min_guests=?, standard_capacity=?, max_capacity=?, default_duration=?, base_price=?, additional_guest_price=?, package_status=?, description=?, management_notes=? WHERE id=?',
      [$code, $name, $cat, $minG, $stdCap, $maxCap, $dur, $basePrice, $addlPrice, $status, $desc, $notes, $id]);
    return get_package($id);
  } else {
    run('INSERT INTO packages (package_code, name, category_code, min_guests, standard_capacity, max_capacity, default_duration, base_price, additional_guest_price, package_status, description, management_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [$code, $name, $cat, $minG, $stdCap, $maxCap, $dur, $basePrice, $addlPrice, $status, $desc, $notes]);
    return get_package(last_id());
  }
}

/* ============================================================================
   2. PHYSICAL ROOM INVENTORY & STATUS
   ============================================================================ */

function get_all_rooms(bool $activeOnly = false): array {
  $sql = 'SELECT r.*, rc.name AS category_name, rc.documented_rate FROM rooms r JOIN room_categories rc ON rc.code = r.category_code';
  if ($activeOnly) $sql .= " WHERE r.room_status <> 'INACTIVE'";
  $sql .= ' ORDER BY r.category_code ASC, r.room_code ASC';
  return qa($sql);
}

function get_room_by_id(int $id): ?array {
  return q1('SELECT r.*, rc.name AS category_name, rc.documented_rate FROM rooms r JOIN room_categories rc ON rc.code = r.category_code WHERE r.id = ?', [$id]);
}

function get_room_by_code(string $code): ?array {
  return q1('SELECT r.*, rc.name AS category_name, rc.documented_rate FROM rooms r JOIN room_categories rc ON rc.code = r.category_code WHERE r.room_code = ?', [$code]);
}

function save_room(array $d, ?int $id = null): array {
  $code = strtoupper(trim(clean_name($d['room_code'] ?? $d['code'] ?? '')));
  $name = trim(clean_name($d['room_name'] ?? $d['name'] ?? ''));
  $cat = strtoupper(trim($d['category_code'] ?? $d['category'] ?? ''));
  if ($cat === 'SINGLE ROOM MASSAGE' || $cat === 'SINGLE') $cat = 'SINGLE';
  elseif ($cat === 'COUPLES SUITE' || $cat === 'COUPLES') $cat = 'COUPLES';
  elseif ($cat === 'FAMILY SPA LOUNGE' || $cat === 'FAMILY') $cat = 'FAMILY';
  $minG = max(1, (int)($d['min_guests'] ?? $d['minGuests'] ?? 1));
  $stdCap = max($minG, (int)($d['standard_capacity'] ?? $d['stdCap'] ?? $minG));
  $maxCap = max($stdCap, (int)($d['max_capacity'] ?? $d['maxCap'] ?? $stdCap));
  $status = in_array($d['room_status'] ?? $d['status'] ?? 'AVAILABLE', ['AVAILABLE', 'MAINTENANCE', 'INACTIVE'], true) ? ($d['room_status'] ?? $d['status']) : 'AVAILABLE';
  $notes = clean_text($d['notes'] ?? $d['desc'] ?? '', 500);

  if ($code === '') fail('Please enter a room code (e.g. SR-1, CS-1).', 400);
  if ($name === '') fail('Please enter a room name.', 400);
  if (!get_room_category($cat)) fail('Invalid room category code.', 400);

  // Check unique room code
  $dup = q1('SELECT id FROM rooms WHERE room_code = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$code, $id] : [$code]);
  if ($dup) fail("A room with code '$code' already exists.", 409);

  if ($id) {
    run('UPDATE rooms SET room_code=?, room_name=?, category_code=?, min_guests=?, standard_capacity=?, max_capacity=?, room_status=?, notes=? WHERE id=?',
      [$code, $name, $cat, $minG, $stdCap, $maxCap, $status, $notes, $id]);
    return get_room_by_id($id);
  } else {
    run('INSERT INTO rooms (room_code, room_name, category_code, min_guests, standard_capacity, max_capacity, room_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [$code, $name, $cat, $minG, $stdCap, $maxCap, $status, $notes]);
    return get_room_by_id(last_id());
  }
}

function deactivate_room(int $id): void {
  $r = get_room_by_id($id);
  if (!$r) fail('Room not found.', 404);
  run("UPDATE rooms SET room_status = 'INACTIVE' WHERE id = ?", [$id]);
}

/**
 * Calculates real-time room operational & occupancy status for a given point in time or today.
 * States: AVAILABLE, RESERVED, OCCUPIED, CLEANING, MAINTENANCE, INACTIVE
 */
function calculate_room_live_status(int $roomId, ?string $date = null, ?string $time = null): string {
  $room = get_room_by_id($roomId);
  if (!$room) return 'INACTIVE';
  if ($room['room_status'] === 'INACTIVE') return 'INACTIVE';

  $tz = new DateTimeZone(cfg('TIMEZONE') ?: 'Asia/Manila');
  $now = new DateTimeImmutable('now', $tz);
  $chkDate = $date ?: $now->format('Y-m-d');
  $chkTime = $time ?: $now->format('H:i');
  $chkMin = hm_to_min($chkTime);

  // 1. Check room maintenance
  $maint = q1("SELECT id FROM room_maintenance WHERE room_id = ? AND status IN ('SCHEDULED','IN_PROGRESS') AND ? >= start_date AND ? <= end_date", [$roomId, $chkDate, $chkDate]);
  if ($maint) return 'MAINTENANCE';

  // 2. Check active appointments in this room on this date
  $assigns = qa("SELECT a.*, b.status AS booking_status, b.duration 
                 FROM appointment_room_assignments a 
                 JOIN bookings b ON b.id = a.booking_id 
                 WHERE a.room_id = ? AND a.assigned_date = ? AND a.status = 'ASSIGNED' AND b.status IN ('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED')",
                 [$roomId, $chkDate]);

  foreach ($assigns as $as) {
    $s = hm_to_min($as['start_time']);
    $e = hm_to_min($as['end_time']);
    $c = hm_to_min($as['cleaning_end_time']);

    // Treatment is active right now
    if ($chkMin >= $s && $chkMin < $e) {
      return 'OCCUPIED';
    }
    // Cleaning buffer is active right now
    if ($chkMin >= $e && $chkMin < $c) {
      return 'CLEANING';
    }
    // Scheduled for later today
    if ($chkMin < $s) {
      return 'RESERVED';
    }
  }

  return 'AVAILABLE';
}

/**
 * Checks if a specific room is free and available for an occupied interval [start, end + 30m buffer)
 */
function is_room_available(int $roomId, string $date, string $startTime, int $durationMinutes, ?int $excludeBookingId = null): array {
  $room = get_room_by_id($roomId);
  if (!$room) return ['ok' => false, 'error' => 'Room not found.', 'code' => 'room_not_found'];
  if ($room['room_status'] === 'INACTIVE') return ['ok' => false, 'error' => 'This room is currently inactive.', 'code' => 'room_inactive'];

  $s = hm_to_min($startTime);
  $buf = buffer_minutes();
  $e = $s + $durationMinutes;
  $c = $e + $buf;

  // Strict closing check: treatment + cleaning must finish by 9:00 PM (21:00)
  $h = spa_hours($date);
  if ($h && $c > $h[1]) {
    return ['ok' => false, 'error' => 'Treatment and mandatory cleaning would exceed closing time (9:00 PM).', 'code' => 'exceeds_closing'];
  }

  // 1. Check room maintenance
  $maint = q1("SELECT * FROM room_maintenance WHERE room_id = ? AND status IN ('SCHEDULED','IN_PROGRESS') AND ? >= start_date AND ? <= end_date", [$roomId, $date, $date]);
  if ($maint) {
    $mStart = hm_to_min($maint['start_time']);
    $mEnd = hm_to_min($maint['end_time']);
    if (max($s, $mStart) < min($c, $mEnd)) {
      return ['ok' => false, 'error' => 'This room is scheduled for maintenance during this time window.', 'code' => 'room_maintenance'];
    }
  }

  // 2. Check overlapping room assignments (including cleaning buffer)
  $sql = "SELECT a.*, b.id AS booking_ref_id, b.name FROM appointment_room_assignments a 
          JOIN bookings b ON b.id = a.booking_id 
          WHERE a.room_id = ? AND a.assigned_date = ? AND a.status = 'ASSIGNED' 
            AND b.status IN ('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED') AND b.archived = 0";
  $params = [$roomId, $date];
  if ($excludeBookingId) {
    $sql .= ' AND a.booking_id <> ?';
    $params[] = $excludeBookingId;
  }
  $existing = qa($sql, $params);

  foreach ($existing as $ex) {
    $exStart = hm_to_min($ex['start_time']);
    $exCleanEnd = hm_to_min($ex['cleaning_end_time']);
    // Overlap test: [s, c) overlaps with [exStart, exCleanEnd)
    if (max($s, $exStart) < min($c, $exCleanEnd)) {
      return [
        'ok' => false,
        'error' => "Room {$room['room_code']} is already reserved or in cleaning ({$ex['start_time']}–{$ex['cleaning_end_time']}) for another appointment.",
        'code' => 'room_occupied',
        'conflict' => $ex
      ];
    }
  }

  // 3. Check legacy / non-migrated bookings assigned to this room code in bookings.room_id
  $sqlB = "SELECT id, time, duration FROM bookings 
           WHERE room_id = ? AND preferred_date = ? AND archived = 0 
             AND status IN ('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED')";
  $paramsB = [$room['room_code'], $date];
  if ($excludeBookingId) {
    $sqlB .= ' AND id <> ?';
    $paramsB[] = $excludeBookingId;
  }
  $bks = qa($sqlB, $paramsB);
  foreach ($bks as $bk) {
    $bkStart = hm_to_min($bk['time']);
    $bkEnd = $bkStart + (int)$bk['duration'] + $buf;
    if (max($s, $bkStart) < min($c, $bkEnd)) {
      return ['ok' => false, 'error' => "Room {$room['room_code']} has an overlapping reservation during this time window.", 'code' => 'room_occupied'];
    }
  }

  return ['ok' => true, 'room' => $room];
}

/**
 * Returns all active rooms compatible with guest count and available for date/time/duration.
 */
function get_available_rooms(string $date, string $time, int $durationMinutes, int $guests, ?int $excludeBookingId = null): array {
  $all = get_all_rooms(true);
  $available = [];
  foreach ($all as $r) {
    // Check capacity
    if ($guests < (int)$r['min_guests'] || $guests > (int)$r['max_capacity']) {
      continue;
    }
    $chk = is_room_available((int)$r['id'], $date, $time, $durationMinutes, $excludeBookingId);
    if ($chk['ok']) {
      $available[] = $r;
    }
  }
  return $available;
}

/**
 * Dynamic Room Recommendation Engine
 * Evaluates rooms based on treatment, duration, guests, capacity, availability, and treatment suitability.
 */
function get_room_recommendations(string $service, int $duration, int $guests, ?string $date = null, ?string $time = null): array {
  $guests = max(1, min(6, $guests));
  $duration = max(30, $duration);
  $allRooms = get_all_rooms(true);

  // Determine target category based on guest count & business rules
  $targetCategory = 'SINGLE';
  $categoryReason = 'Recommended for solo relaxation with private therapy bed, en-suite shower, and calming ambient lighting.';
  if ($guests === 2) {
    $targetCategory = 'COUPLES';
    $categoryReason = 'Specially recommended for two guests. Features dual side-by-side massage beds, aromatic steam shower, and relaxation lounge.';
  } elseif ($guests >= 3) {
    $targetCategory = 'FAMILY';
    $categoryReason = 'Spacious private VIP spa pavilion configured for families and groups (3–6 guests) with hydrotherapy tub and relaxation lounge.';
  }

  // Treatment suitability notes
  $svcNote = '';
  if (!empty($service)) {
    $svcLower = strtolower($service);
    if (strpos($svcLower, 'massage') !== false || strpos($svcLower, 'body') !== false) {
      $svcNote = 'Fully equipped with premium ergonomic massage beds and therapeutic linen.';
    } elseif (strpos($svcLower, 'facial') !== false) {
      $svcNote = 'Equipped with dedicated facial therapy setup and serene environment.';
    }
  }

  $evaluatedRooms = [];
  $recommended = null;
  $alternatives = [];
  $hasDateTime = !empty($date) && !empty($time);

  foreach ($allRooms as $r) {
    $id = (int)$r['id'];
    $catCode = $r['category_code'];
    $minG = (int)$r['min_guests'];
    $maxG = (int)$r['max_capacity'];
    $fitsGuests = ($guests >= $minG && $guests <= $maxG);

    // Friendly display name
    $num = preg_replace('/[^0-9]/', '', $r['room_code']);
    $displayName = $r['room_name'];
    if ($catCode === 'SINGLE') {
      $displayName = 'Executive Room ' . $num . ' (' . $r['room_code'] . ')';
    } elseif ($catCode === 'COUPLES') {
      $displayName = 'Junior Suite ' . $num . ' (' . $r['room_code'] . ')';
    } elseif ($catCode === 'FAMILY') {
      $displayName = 'Premier Suite ' . $num . ' - Family Spa (' . $r['room_code'] . ')';
    }

    $isAvail = true;
    $availStatus = 'AVAILABLE';
    $conflictReason = '';

    if ($hasDateTime) {
      $chk = is_room_available($id, $date, $time, $duration);
      if (!$chk['ok']) {
        $isAvail = false;
        $availStatus = ($chk['code'] === 'MAINTENANCE') ? 'MAINTENANCE' : 'OCCUPIED';
        $conflictReason = $chk['error'] ?? 'Room is scheduled for another appointment or cleaning buffer.';
      }
    } else {
      $live = calculate_room_live_status($id);
      if ($live === 'MAINTENANCE') {
        $isAvail = false;
        $availStatus = 'MAINTENANCE';
        $conflictReason = 'Under scheduled maintenance.';
      }
    }

    // Rate display
    $rate = (float)($r['documented_rate'] ?? 0);
    $rateText = 'Included with treatment';
    if ($catCode === 'COUPLES' && $rate > 0) {
      $rateText = '₱' . number_format($rate, 2) . ' / suite rate';
    } elseif ($catCode === 'FAMILY' && $rate > 0) {
      $rateText = '₱' . number_format($rate, 2) . ' (groups up to 6)';
    }

    // Capacity text
    $capText = ($maxG === 1) ? '1 Guest' : ($minG . '–' . $maxG . ' Guests');

    // Specific reason for this room
    $reason = $categoryReason;
    if ($svcNote) $reason .= ' ' . $svcNote;

    $item = [
      'id'             => $id,
      'roomCode'       => $r['room_code'],
      'name'           => $displayName,
      'categoryCode'   => $catCode,
      'categoryName'   => $r['category_name'],
      'description'    => $r['notes'] ?: ($r['description'] ?? 'Private serene treatment suite.'),
      'minGuests'      => $minG,
      'maxCapacity'    => $maxG,
      'capacityText'   => $capText,
      'rate'           => $rate,
      'rateText'       => $rateText,
      'isAvailable'    => $isAvail,
      'availabilityStatus' => $availStatus,
      'fitsGuests'     => $fitsGuests,
      'isEligible'     => ($fitsGuests && $availStatus !== 'MAINTENANCE'),
      'recommendReason' => $reason,
      'conflictReason' => $conflictReason
    ];

    $evaluatedRooms[] = $item;
  }

  // Determine primary recommended room:
  // 1st priority: Target category and fits guests and available
  foreach ($evaluatedRooms as $r) {
    if ($r['categoryCode'] === $targetCategory && $r['fitsGuests'] && $r['isAvailable']) {
      $recommended = $r;
      break;
    }
  }

  // 2nd priority: Target category and fits guests (even if date/time conflict)
  if (!$recommended) {
    foreach ($evaluatedRooms as $r) {
      if ($r['categoryCode'] === $targetCategory && $r['fitsGuests']) {
        $recommended = $r;
        break;
      }
    }
  }

  // 3rd priority: Any room that fits guests and is available
  if (!$recommended) {
    foreach ($evaluatedRooms as $r) {
      if ($r['fitsGuests'] && $r['isAvailable']) {
        $recommended = $r;
        break;
      }
    }
  }

  // If still none, fallback to last evaluated room
  if (!$recommended && !empty($evaluatedRooms)) {
    $recommended = $evaluatedRooms[count($evaluatedRooms) - 1];
  }

  // Alternatives: all other eligible rooms fitting guest count
  foreach ($evaluatedRooms as $r) {
    if ($recommended && $r['id'] === $recommended['id']) continue;
    if ($r['fitsGuests'] && $r['availabilityStatus'] !== 'MAINTENANCE') {
      $alternatives[] = $r;
    }
  }

  return [
    'ok'              => true,
    'targetCategory'  => $targetCategory,
    'guests'          => $guests,
    'service'         => $service,
    'duration'        => $duration,
    'hasDateTime'     => $hasDateTime,
    'recommended'     => $recommended,
    'alternatives'    => $alternatives,
    'allRooms'        => $evaluatedRooms
  ];
}

/* ============================================================================
   3. THERAPIST MANAGEMENT & SCHEDULING
   ============================================================================ */

function get_all_therapists(bool $activeOnly = false): array {
  $sql = 'SELECT * FROM therapists';
  if ($activeOnly) $sql .= " WHERE employment_status = 'ACTIVE'";
  $sql .= ' ORDER BY full_name ASC';
  return qa($sql);
}

function get_therapist_by_id(int $id): ?array {
  return q1('SELECT * FROM therapists WHERE id = ?', [$id]);
}

function save_therapist(array $d, ?int $id = null): array {
  $code = strtoupper(trim(clean_name($d['employee_code'] ?? $d['code'] ?? '')));
  $name = trim(clean_name($d['full_name'] ?? $d['name'] ?? ''));
  $phone = trim($d['contact_number'] ?? $d['phone'] ?? '');
  $email = norm_email($d['email'] ?? '');
  $spec = trim(clean_text($d['specialization'] ?? $d['specializations'] ?? 'ALL', 300));
  $empStatus = in_array($d['employment_status'] ?? $d['status'] ?? 'ACTIVE', ['ACTIVE', 'INACTIVE'], true) ? ($d['employment_status'] ?? $d['status']) : 'ACTIVE';
  $availStatus = in_array($d['availability_status'] ?? $d['avail'] ?? 'AVAILABLE', ['AVAILABLE', 'ON_BREAK', 'OFF_DUTY', 'ON_LEAVE', 'INACTIVE'], true) ? ($d['availability_status'] ?? $d['avail']) : 'AVAILABLE';
  $notes = clean_text($d['notes'] ?? '', 500);

  if ($code === '') fail('Please enter an employee code (e.g. TH-001).', 400);
  if ($name === '') fail('Please enter therapist full name.', 400);
  if ($phone !== '') {
    if (!is_phone($phone)) fail('Phone number must contain 11 digits (09…) or 12 digits (639…).', 400);
    $phone = format_phone($phone);
  }

  $dup = q1('SELECT id FROM therapists WHERE employee_code = ?' . ($id ? ' AND id <> ?' : ''), $id ? [$code, $id] : [$code]);
  if ($dup) fail("A therapist with employee code '$code' already exists.", 409);

  if ($id) {
    run('UPDATE therapists SET employee_code=?, full_name=?, contact_number=?, email=?, specialization=?, employment_status=?, availability_status=?, notes=? WHERE id=?',
      [$code, $name, $phone, $email, $spec, $empStatus, $availStatus, $notes, $id]);
    return get_therapist_by_id($id);
  } else {
    run('INSERT INTO therapists (employee_code, full_name, contact_number, email, specialization, employment_status, availability_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [$code, $name, $phone, $email, $spec, $empStatus, $availStatus, $notes]);
    $tid = last_id();
    // Initialize default Mon-Sun 13:00-21:00 schedule
    for ($i = 0; $i <= 6; $i++) {
      run('INSERT INTO therapist_schedules (therapist_id, day_of_week, shift_start, shift_end, is_working_day) VALUES (?, ?, ?, ?, ?)',
        [$tid, $i, '13:00', '21:00', 1]);
    }
    // Default meal break 17:00-17:30
    run('INSERT INTO therapist_breaks (therapist_id, break_date, start_time, end_time, break_type, notes) VALUES (?, NULL, ?, ?, ?, ?)',
      [$tid, '17:00', '17:30', 'Meal Break', 'Daily dinner break']);
    return get_therapist_by_id($tid);
  }
}

function get_therapist_schedule(int $therapistId): array {
  $w = qa('SELECT * FROM therapist_schedules WHERE therapist_id = ? ORDER BY day_of_week ASC', [$therapistId]);
  return [
    'weekly' => $w,
    'shifts' => $w,
    'breaks' => qa('SELECT * FROM therapist_breaks WHERE therapist_id = ? ORDER BY start_time ASC', [$therapistId]),
    'leaves' => qa('SELECT * FROM therapist_leaves WHERE therapist_id = ? ORDER BY start_date DESC', [$therapistId])
  ];
}

function update_therapist_schedule(int $therapistId, array $shifts): void {
  foreach ($shifts as $s) {
    $day = (int)($s['day_of_week'] ?? 0);
    $start = $s['shift_start'] ?? '13:00';
    $end = $s['shift_end'] ?? '21:00';
    $work = !empty($s['is_working_day']) ? 1 : 0;
    run('INSERT INTO therapist_schedules (therapist_id, day_of_week, shift_start, shift_end, is_working_day) 
         VALUES (?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE shift_start=VALUES(shift_start), shift_end=VALUES(shift_end), is_working_day=VALUES(is_working_day)',
         [$therapistId, $day, $start, $end, $work]);
  }
}

function add_therapist_break(int $therapistId, ?string $breakDate, string $start, string $end, string $type = 'Meal Break', string $notes = ''): void {
  run('INSERT INTO therapist_breaks (therapist_id, break_date, start_time, end_time, break_type, notes) VALUES (?, ?, ?, ?, ?, ?)',
    [$therapistId, $breakDate ?: null, $start, $end, $type, $notes]);
}

function add_therapist_leave(int $therapistId, string $startDate, string $endDate, string $type = 'Annual Leave', string $notes = ''): void {
  run('INSERT INTO therapist_leaves (therapist_id, start_date, end_date, leave_type, status, notes) VALUES (?, ?, ?, ?, ?, ?)',
    [$therapistId, $startDate, $endDate, $type, 'APPROVED', $notes]);
}

/**
 * Checks therapist availability for a given date, start time, treatment duration, and service.
 */
function is_therapist_available(int $therapistId, string $date, string $startTime, int $durationMinutes, string $service = '', ?int $excludeBookingId = null): array {
  $th = get_therapist_by_id($therapistId);
  if (!$th) return ['ok' => false, 'error' => 'Therapist not found.', 'code' => 'therapist_not_found'];
  if ($th['employment_status'] !== 'ACTIVE') {
    return ['ok' => false, 'error' => 'Therapist is currently inactive.', 'code' => 'therapist_inactive'];
  }

  $s = hm_to_min($startTime);
  $e = $s + $durationMinutes;

  // 1. Specialization check (if configured and not ALL)
  if ($service !== '' && $th['specialization'] !== 'ALL') {
    $specs = array_map('trim', explode(',', strtolower($th['specialization'])));
    if (!in_array(strtolower(trim($service)), $specs, true)) {
      return ['ok' => false, 'error' => "Therapist {$th['full_name']} is not specialized in '$service'. Specializations: {$th['specialization']}", 'code' => 'specialization_mismatch'];
    }
  }

  // 2. Check Weekly Schedule / Working Day
  $tz = new DateTimeZone(cfg('TIMEZONE') ?: 'Asia/Manila');
  $dt = new DateTimeImmutable($date, $tz);
  $dayOfWeek = (int)$dt->format('w'); // 0=Sun, 1=Mon, ..., 6=Sat

  $shift = q1('SELECT * FROM therapist_schedules WHERE therapist_id = ? AND day_of_week = ?', [$therapistId, $dayOfWeek]);
  if (!$shift || !(int)$shift['is_working_day']) {
    return ['ok' => false, 'error' => "Therapist {$th['full_name']} is not scheduled to work on " . $dt->format('l') . 's.', 'code' => 'off_duty'];
  }

  $shiftStart = hm_to_min($shift['shift_start']);
  $shiftEnd = hm_to_min($shift['shift_end']);
  if ($s < $shiftStart || $e > $shiftEnd) {
    return ['ok' => false, 'error' => "Appointment ({$startTime}–" . min_to_hm($e) . ") is outside {$th['full_name']}'s shift ({$shift['shift_start']}–{$shift['shift_end']}).", 'code' => 'outside_shift'];
  }

  // 3. Check Breaks
  $breaks = qa('SELECT * FROM therapist_breaks WHERE therapist_id = ? AND (break_date IS NULL OR break_date = ?)', [$therapistId, $date]);
  foreach ($breaks as $br) {
    $bStart = hm_to_min($br['start_time']);
    $bEnd = hm_to_min($br['end_time']);
    if (max($s, $bStart) < min($e, $bEnd)) {
      return ['ok' => false, 'error' => "Therapist {$th['full_name']} is on {$br['break_type']} ({$br['start_time']}–{$br['end_time']}).", 'code' => 'on_break'];
    }
  }

  // 4. Check Leaves
  $leaves = qa("SELECT * FROM therapist_leaves WHERE therapist_id = ? AND status = 'APPROVED' AND ? >= start_date AND ? <= end_date", [$therapistId, $date, $date]);
  if (!empty($leaves)) {
    return ['ok' => false, 'error' => "Therapist {$th['full_name']} is on approved leave on {$date}.", 'code' => 'on_leave'];
  }

  // 5. Check Overlapping Appointments
  $sql = "SELECT a.*, b.name, b.service FROM appointment_therapist_assignments a 
          JOIN bookings b ON b.id = a.booking_id 
          WHERE a.therapist_id = ? AND a.assigned_date = ? AND a.status = 'ASSIGNED' 
            AND b.status IN ('PENDING','CONFIRMED','IN_PROGRESS','COMPLETED') AND b.archived = 0";
  $params = [$therapistId, $date];
  if ($excludeBookingId) {
    $sql .= ' AND a.booking_id <> ?';
    $params[] = $excludeBookingId;
  }
  $assigned = qa($sql, $params);

  foreach ($assigned as $as) {
    $asStart = hm_to_min($as['start_time']);
    $asEnd = hm_to_min($as['end_time']);
    if (max($s, $asStart) < min($e, $asEnd)) {
      return ['ok' => false, 'error' => "Therapist {$th['full_name']} is already assigned to another appointment ({$as['start_time']}–{$as['end_time']}).", 'code' => 'therapist_conflict'];
    }
  }

  return ['ok' => true, 'therapist' => $th];
}

/**
 * Returns available therapists qualified for the service and free at date/time.
 */
function get_available_therapists(string $date, string $time, int $durationMinutes, string $service = '', ?int $excludeBookingId = null): array {
  $all = get_all_therapists(true);
  $available = [];
  foreach ($all as $th) {
    $chk = is_therapist_available((int)$th['id'], $date, $time, $durationMinutes, $service, $excludeBookingId);
    if ($chk['ok']) {
      $available[] = $th;
    }
  }
  return $available;
}

/* ============================================================================
   4. APPOINTMENT RESOURCE ASSIGNMENT & CLEANING WORKFLOW
   ============================================================================ */

/**
 * Assigns an appointment to an eligible physical room.
 */
/**
 * Decide which room a new booking gets, entirely on the server. Never substitutes: a room the customer picked is
 * either valid for this guest count and time, or the booking is refused with a clear reason.
 *   ''/'auto'            -> best available room from the recommendation engine
 *   room code / room id  -> exactly that room
 *   category name/code   -> first free room of that category
 */
function resolve_booking_room(string $req, string $service, int $dur, int $guests, string $date, string $time): array {
  $req = trim($req);
  if ($req === '' || strtolower($req) === 'auto') {
    $rec = get_room_recommendations($service, $dur, $guests, $date, $time);
    if (!empty($rec['ok']) && !empty($rec['recommended'])) {
      $rm = get_room_by_id((int)$rec['recommended']['id']);
      if ($rm) return $rm;
    }
    if (!get_all_rooms(true)) fail('The spa has no rooms set up yet, so this reservation cannot be placed. Please contact the spa.', 409, ['code' => 'no_rooms_configured']);
    fail('No suitable room is free for ' . $guests . ' guest' . ($guests === 1 ? '' : 's') . ' at that time. Please choose another time.', 409, ['field' => 'time', 'code' => 'no_room_available']);
  }
  $rm = ctype_digit($req) ? get_room_by_id((int)$req) : get_room_by_code(strtoupper($req));
  if (!$rm) {   // maybe a whole category was chosen
    $map = ['SINGLE' => 'SINGLE', 'SINGLE ROOM MASSAGE' => 'SINGLE', 'EXECUTIVE ROOM' => 'SINGLE', 'COUPLES' => 'COUPLES', 'COUPLES SUITE' => 'COUPLES', 'JUNIOR SUITE' => 'COUPLES',
            'FAMILY' => 'FAMILY', 'FAMILY SPA LOUNGE' => 'FAMILY', 'PREMIER SUITE' => 'FAMILY'];
    $cat = $map[strtoupper($req)] ?? null;
    if (!$cat) fail('The selected room does not exist.', 400, ['field' => 'room', 'code' => 'room_not_found']);
    foreach (get_available_rooms($date, $time, $dur, $guests) as $cand) if ($cand['category_code'] === $cat && $cand['room_status'] === 'AVAILABLE') return $cand;
    fail('No room in that category is free for ' . $guests . ' guest(s) at that time. Please choose another room or time.', 409, ['field' => 'room', 'code' => 'category_full']);
  }
  if ($rm['room_status'] !== 'AVAILABLE') fail("Room {$rm['room_code']} is not open for booking right now (" . strtolower($rm['room_status']) . ').', 409, ['field' => 'room', 'code' => 'room_unavailable']);
  if ($guests < (int)$rm['min_guests'] || $guests > (int)$rm['max_capacity']) {
    fail("Room {$rm['room_code']} fits {$rm['min_guests']}-{$rm['max_capacity']} guest(s); this booking has $guests.", 400, ['field' => 'room', 'code' => 'room_capacity']);
  }
  $chk = is_room_available((int)$rm['id'], $date, $time, $dur);
  if (!$chk['ok']) fail($chk['error'], 409, ['field' => 'room', 'code' => $chk['code']]);
  return $rm;
}

function assign_appointment_room(int $bookingId, int $roomId, string $adminName = 'Admin', string $reason = ''): array {
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) fail('Booking not found.', 404);
  if (!in_array($bk['status'], ['PENDING', 'CONFIRMED'], true)) fail('Rooms can only be changed for pending or confirmed reservations.', 409);
  $dur = service_minutes($bk['service'], $bk['duration']) ?: 60;
  $guests = (int)($bk['guests'] ?? 1);

  $room = get_room_by_id($roomId);
  if (!$room) fail('Room not found.', 404);
  if ($room['room_status'] !== 'AVAILABLE') fail("Room {$room['room_code']} is not open for booking right now (" . strtolower($room['room_status']) . ').', 409, ['code' => 'room_unavailable']);

  // Changing a room the customer already holds is allowed, but only with a written reason (kept in the log).
  $booked = trim((string)($bk['room_id'] ?? ''));
  $isChange = $booked !== '' && $booked !== $room['room_code'];
  $reason = clean_text($reason, 300);
  if ($isChange && mb_strlen($reason) < 5) fail("This booking already has room {$booked}. Enter a reason (at least 5 characters) to move it to {$room['room_code']}.", 400, ['code' => 'reason_required', 'field' => 'reason']);

  // Validate guest capacity
  if ($guests < (int)$room['min_guests'] || $guests > (int)$room['max_capacity']) {
    fail("Room {$room['room_code']} capacity ({$room['min_guests']}–{$room['max_capacity']} guests) does not fit this booking ({$guests} guests).", 400, ['code' => 'room_capacity']);
  }

  $endTime = $cleanEndTime = '';
  with_day_lock($bk['preferred_date'], function () use ($bk, $roomId, $room, $dur, $bookingId, $adminName, $isChange, $reason, $booked, &$endTime, &$cleanEndTime) {
    // availability is re-checked while the day lock is held, so two admins cannot both take the same room
    $chk = is_room_available($roomId, $bk['preferred_date'], $bk['time'], $dur, $bookingId);
    if (!$chk['ok']) fail($chk['error'], 409, ['code' => $chk['code']]);

    $e = hm_to_min($bk['time']) + $dur;
    $endTime = min_to_hm($e);
    $cleanEndTime = min_to_hm($e + buffer_minutes());
    tx(function () use ($bk, $roomId, $room, $bookingId, $adminName, $endTime, $cleanEndTime, $isChange, $reason, $booked) {
      // the previous assignment is kept as history (status RELEASED), never deleted
      run("UPDATE appointment_room_assignments SET status = 'RELEASED' WHERE booking_id = ? AND status = 'ASSIGNED'", [$bookingId]);
      run('INSERT INTO appointment_room_assignments (booking_id, room_id, assigned_date, start_time, end_time, cleaning_end_time, status, assigned_by)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$bookingId, $roomId, $bk['preferred_date'], $bk['time'], $endTime, $cleanEndTime, 'ASSIGNED', $adminName]);
      run('UPDATE bookings SET room_id = ? WHERE id = ?', [$room['room_code'], $bookingId]);
      run('DELETE FROM room_cleaning WHERE booking_id = ? AND status = ?', [$bookingId, 'SCHEDULED']);
      run('INSERT INTO room_cleaning (room_id, booking_id, cleaning_date, start_time, end_time, status, cleaned_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          [$roomId, $bookingId, $bk['preferred_date'], $endTime, $cleanEndTime, 'SCHEDULED', '', "Automated post-treatment sanitization for booking #$bookingId"]);
      admin_log(admin_actor(), $isChange ? 'Room changed' : 'Room assigned', bk_ref($bk),
        ($isChange ? "Moved from $booked to {$room['room_code']} ({$room['room_name']}). Reason: $reason. " : "Assigned room {$room['room_code']} ({$room['room_name']}) ") . "for {$bk['preferred_date']} {$bk['time']}-{$cleanEndTime}", $adminName);
      if ($isChange && !empty($bk['user_id'])) {
        notify($bk['user_id'], "Your treatment room was changed to {$room['room_name']} ({$room['room_code']}). Date and time stay the same. Reason: $reason");
        act($bk['user_id'], 'Room changed: ' . $bk['service']);
      }
    });
  });

  return ['ok' => true, 'room' => $room, 'cleaning_end' => $cleanEndTime, 'changed' => $isChange];
}

/**
 * Assigns an appointment to an eligible therapist.
 */
function assign_appointment_therapist(int $bookingId, int $therapistId, string $adminName = 'Admin'): array {
  $bk = q1('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
  if (!$bk) fail('Booking not found.', 404);
  $dur = service_minutes($bk['service'], $bk['duration']) ?: 60;

  $th = get_therapist_by_id($therapistId);
  if (!$th) fail('Therapist not found.', 404);

  // Validate therapist availability
  $chk = is_therapist_available($therapistId, $bk['preferred_date'], $bk['time'], $dur, $bk['service'], $bookingId);
  if (!$chk['ok']) {
    fail($chk['error'], 409, ['code' => $chk['code']]);
  }

  $s = hm_to_min($bk['time']);
  $e = $s + $dur;
  $endTime = min_to_hm($e);

  run("UPDATE appointment_therapist_assignments SET status = 'RELEASED' WHERE booking_id = ? AND status = 'ASSIGNED'", [$bookingId]);
  run('INSERT INTO appointment_therapist_assignments (booking_id, therapist_id, assigned_date, start_time, end_time, status, assigned_by) 
       VALUES (?, ?, ?, ?, ?, ?, ?)',
       [$bookingId, $therapistId, $bk['preferred_date'], $bk['time'], $endTime, 'ASSIGNED', $adminName]);

  run('UPDATE bookings SET therapist_id = ? WHERE id = ?', [$therapistId, $bookingId]);

  admin_log(admin_actor(), 'Therapist assigned', bk_ref($bk), "Assigned therapist {$th['full_name']} ({$th['employee_code']}) for {$bk['preferred_date']} {$bk['time']}-{$endTime}", $adminName);

  return ['ok' => true, 'therapist' => $th];
}

/**
 * Releases room and therapist assignments when an appointment is cancelled or rejected.
 */
function release_booking_resources(int $bookingId, string $adminName = 'Admin'): void {
  run("UPDATE appointment_room_assignments SET status = 'RELEASED' WHERE booking_id = ? AND status = 'ASSIGNED'", [$bookingId]);
  run("UPDATE appointment_therapist_assignments SET status = 'RELEASED' WHERE booking_id = ? AND status = 'ASSIGNED'", [$bookingId]);
  run("UPDATE room_cleaning SET status = 'COMPLETED', completed_at = NOW(), notes = CONCAT(notes, ' [Cancelled]') WHERE booking_id = ? AND status = 'SCHEDULED'", [$bookingId]);
}

/**
 * Updates cleaning task status.
 * NOTE (Part 12): Completing early does NOT shorten the 30-min buffer for room availability.
 */
function update_cleaning_status(int $cleaningId, string $status, string $cleanedBy = '', string $notes = ''): array {
  $c = q1('SELECT * FROM room_cleaning WHERE id = ?', [$cleaningId]);
  if (!$c) fail('Cleaning record not found.', 404);
  if (!in_array($status, ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED'], true)) fail('Invalid cleaning status.', 400);

  $completedAt = ($status === 'COMPLETED') ? now() : null;
  run('UPDATE room_cleaning SET status = ?, cleaned_by = ?, completed_at = ?, notes = ? WHERE id = ?',
    [$status, clean_name($cleanedBy), $completedAt, clean_text($notes, 300), $cleaningId]);

  return q1('SELECT * FROM room_cleaning WHERE id = ?', [$cleaningId]);
}
