<?php
/* Bella Vista - service, duration and package rules (CLI).   php tests/service_suite.php
   Needs schema.sql + seed.sql. Creates "BKTEST" customers/bookings and a few throw-away services in the content table; the
   original content rows and every created record are restored/removed afterwards. Not for databases with real customer data. */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
chdir(dirname(__DIR__));
define('TEST_RUNNER', true);
require_once 'php/portal.php';
require_once 'php/payments.php';
$pass = 0; $fail = 0;
function check(string $label, $actual, $expected): void { global $pass, $fail; $ok = $actual === $expected || (is_numeric($actual) && is_numeric($expected) && abs($actual - $expected) < 0.005); $ok ? $pass++ : $fail++; printf("  [%s] %-72s got=%s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, json_encode($actual), json_encode($expected)); }
function attempt(array $user, array $p): array { try { api_create_booking($user, $p); return [0, '']; } catch (RuntimeException $e) { $j = json_decode($e->getMessage(), true); return [$e->getCode(), is_array($j) ? ($j['booking']['id'] ?? '') : $e->getMessage()]; } }
function payload(string $date, string $time, string $svc, $dur, int $guests = 1, array $more = []): array { return $more + ['service' => $svc, 'duration' => $dur, 'guests' => $guests, 'date' => $date, 'time' => $time, 'phone' => '09171234567', 'agreeTerms' => true, 'agreeHealth' => true, 'selectedRoomCode' => 'auto', 'message' => '']; }
function put_services(array $items): void { run('INSERT INTO content(content_key,json) VALUES(?,?) ON DUPLICATE KEY UPDATE json=VALUES(json)', ['services', json_encode($items, JSON_UNESCAPED_UNICODE)]); service_admin_overrides(true); }
$orig = q1("SELECT json FROM content WHERE content_key='services'");
run("INSERT INTO users (full_name,email,password_hash,email_verified) VALUES ('BKTEST svc','bktest.svc@gmail.com','x',1)"); $uid = last_id();
$user = ['id' => $uid, 'fullName' => 'BKTEST svc', 'email' => 'bktest.svc@gmail.com', 'emailVerified' => true];
register_shutdown_function(function () use ($orig, $uid) {
  if ($orig) run("INSERT INTO content(content_key,json) VALUES('services',?) ON DUPLICATE KEY UPDATE json=VALUES(json)", [$orig['json']]); else run("DELETE FROM content WHERE content_key='services'");
  foreach (['transactions', 'appointment_room_assignments', 'room_cleaning'] as $t) run("DELETE FROM $t WHERE booking_id IN (SELECT id FROM bookings WHERE name LIKE 'BKTEST%')");
  run("DELETE FROM bookings WHERE name LIKE 'BKTEST%'"); run('DELETE FROM notifications WHERE user_id=?', [$uid]); run('DELETE FROM activity WHERE user_id=?', [$uid]); run('DELETE FROM users WHERE id=?', [$uid]);
});
$tz = new DateTimeZone(cfg('TIMEZONE')); $day = fn(int $n) => (new DateTime('now', $tz))->modify("+$n days")->format('Y-m-d');
put_services([]);

echo "1. Duration must be one of the configured options (no 70-minute booking)\n";
foreach (['70' => 400, '75' => 400, '59' => 400, '91' => 400, '0' => 400, '-60' => 400, '60.5' => 400, '60 ' => 0, 'abc' => 400, '' => 400, '1e2' => 400] as $d => $expected) {
  [$c] = attempt($user, payload($day(20 + abs((int)crc32("$d")) % 30), '14:00', 'Body Massage', $d));
  check("Body Massage duration '" . $d . "' -> " . ($expected === 0 ? 'accepted' : 'refused 400'), $c === 201 ? 0 : $c, $expected);
}
[$c] = attempt($user, payload($day(60), '14:00', 'Body Massage', '90')); check('Body Massage 90 (listed option) accepted', $c, 201);
[$c] = attempt($user, payload($day(61), '14:00', 'Body Massage', 75)); check('numeric 75 refused (between 60 and 90)', $c, 400);
check('availability endpoint helper rejects 70 too', validate_service_duration('Body Massage', '70', false)['ok'], false);
check('...but still accepts the real options 60 and 90', [validate_service_duration('Body Massage', '60', false)['ok'], validate_service_duration('Body Massage', '90', false)['ok']], [true, true]);
check('prices unchanged: Body Massage 60 / 90, Foot 30 / 45', [get_service_price('Body Massage', 60), get_service_price('Body Massage', 90), get_service_price('Foot Massage', 30), get_service_price('Foot Massage', 45)], [800.0, 1100.0, 450.0, 600.0]);
check('price x guests (Body Scrub 45, 2 guests = 1600)', get_service_price('Body Scrub', 45, 2), 1600.0);
check('unknown service has NO price (no silent 800)', get_service_price('Mystery Ritual', 60), 0.0);

echo "2. Services added in the admin dashboard are bookable (server decides)\n";
[$c] = attempt($user, payload($day(30), '14:00', 'Hot Stone Therapy', 60)); check('before the admin adds it: refused', $c, 400);
put_services([['name' => 'Hot Stone Therapy', 'category' => 'Massage', 'desc' => 'x', 'durations' => '60, 120', 'prices' => '1200, 2200', 'active' => 'Yes']]);
check('configured options read from the dashboard', service_duration_config('Hot Stone Therapy')['options'], [60, 120]);
[$c, $ref] = attempt($user, payload($day(30), '14:00', 'Hot Stone Therapy', 60)); check('admin-added service, 60 min: accepted', $c, 201);
check('...priced from the dashboard (PHP 1200)', (float)q1('SELECT amount a FROM bookings WHERE id=?', [(int)substr($ref, strrpos($ref, '-') + 1)])['a'], 1200.0);
[$c] = attempt($user, payload($day(31), '14:00', 'Hot Stone Therapy', 90)); check('...90 min is not one of its options: refused', $c, 400);
[$c, $ref] = attempt($user, payload($day(32), '14:00', 'Hot Stone Therapy', 120, 2)); check('...120 min x 2 guests accepted at 2 x 2200 = 4400', [$c, (float)q1('SELECT amount a FROM bookings WHERE id=?', [(int)substr($ref, strrpos($ref, '-') + 1)])['a']], [201, 4400.0]);
put_services([['name' => 'Hot Stone Therapy', 'category' => 'Massage', 'desc' => 'x']]);
[$c] = attempt($user, payload($day(33), '14:00', 'Hot Stone Therapy', 60)); check('a service with no durations/prices configured is refused', $c, 400);
put_services([['name' => 'Hot Stone Therapy', 'durations' => '60', 'prices' => '1200', 'active' => 'No']]);
[$c, $msg] = attempt($user, payload($day(33), '14:00', 'Hot Stone Therapy', 60)); check('an inactive service is refused', [$c, str_contains($msg, 'not available')], [400, true]);
put_services([['name' => 'Body Massage', 'durations' => '60', 'prices' => '850', 'active' => 'Yes']]);
check('admin can change a built-in service (Body Massage 60 -> 850, 90 removed)', [get_service_price('Body Massage', 60), validate_service_duration('Body Massage', '90', false)['ok']], [850.0, false]);
put_services([['name' => 'Facial Treatment', 'active' => 'No']]);
[$c] = attempt($user, payload($day(34), '14:00', 'Facial Treatment', 45)); check('a built-in service can be switched off', $c, 400);
put_services([]);
[$c] = attempt($user, payload($day(35), '14:00', 'Facial Treatment', 45)); check('...and is bookable again when removed from the overrides', $c, 201);
foreach (['Other Spa Treatments', 'Not sure yet'] as $n) { [$c] = attempt($user, payload($day(36), '14:00', $n, 60)); check("'$n' still needs management configuration", $c, 400); }

echo "3. Dashboard validation of service entries\n";
check('valid entry passes', content_check('services', [['name' => 'A', 'durations' => '60, 90', 'prices' => '800, 1100', 'active' => 'Yes']]), null);
foreach ([['durations' => '60, 90', 'prices' => '800'], ['durations' => '60', 'prices' => '0'], ['durations' => '10', 'prices' => '5'], ['durations' => '60, 60', 'prices' => '1, 2'], ['durations' => 'abc', 'prices' => '1'], ['durations' => '60', 'prices' => '1', 'active' => 'maybe']] as $i => $bad)
  check("invalid entry #" . ($i + 1) . ' is rejected with a message', is_string(content_check('services', [['name' => 'A'] + $bad])), true);
check('public overlay carries durations, prices and active', (function () { put_services([['name' => 'Z', 'category' => 'c', 'desc' => 'd', 'durations' => '45', 'prices' => '700', 'active' => 'No']]); $s = content_overlay()['services'][0]; return [$s['durations'], $s['prices'], $s['active']]; })(), [[45], [45 => 700.0], false]);
put_services([]);

echo "4. Packages are linked to bookings and priced by PACKAGE_PRICING_RELATIONSHIP\n";
$pk = get_packages(true); check('three active seeded packages', count($pk), 3);
$row = fn(string $ref) => q1('SELECT * FROM bookings WHERE id=?', [(int)substr($ref, strrpos($ref, '-') + 1)]);
$pid = fn(string $code) => (int)q1('SELECT id FROM packages WHERE package_code=?', [$code])['id'];
$setPkg = fn(string $code, string $st) => run('UPDATE packages SET package_status=? WHERE package_code=?', [$st, $code]);
$u2 = (function () { run("INSERT INTO users (full_name,email,password_hash,email_verified) VALUES ('BKTEST svc2','bktest.svc2@gmail.com','x',1)"); $id = last_id(); return ['id' => $id, 'fullName' => 'BKTEST svc2', 'email' => 'bktest.svc2@gmail.com', 'emailVerified' => true]; })();
register_shutdown_function(function () use ($u2) { run("DELETE FROM transactions WHERE booking_id IN (SELECT id FROM bookings WHERE user_id=?)", [$u2['id']]); run("DELETE FROM appointment_room_assignments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id=?)", [$u2['id']]); run("DELETE FROM room_cleaning WHERE booking_id IN (SELECT id FROM bookings WHERE user_id=?)", [$u2['id']]); run('DELETE FROM bookings WHERE user_id=?', [$u2['id']]); run('DELETE FROM notifications WHERE user_id=?', [$u2['id']]); run('DELETE FROM activity WHERE user_id=?', [$u2['id']]); run('DELETE FROM users WHERE id=?', [$u2['id']]); run("UPDATE packages SET package_status='ACTIVE'"); });
[$c, $ref] = attempt($u2, payload($day(75), '14:00', 'Body Massage', 60)); check('no package sent (INDEPENDENT): the room type\'s package is linked automatically', [$c, (int)$row($ref)['package_id']], [201, $pid('PKG-SR')]);
check('...amount unchanged (treatment price only)', (float)$row($ref)['amount'], 800.0);
[$c, $ref] = attempt($u2, payload($day(76), '14:00', 'Body Massage', 60, 1, ['packageCode' => 'PKG-SR'])); check('explicit package code accepted and saved', [$c, (int)$row($ref)['package_id']], [201, $pid('PKG-SR')]);
[$c, $ref] = attempt($u2, payload($day(77), '14:00', 'Body Massage', 60, 1, ['packageId' => (string)$pid('PKG-SR')])); check('package given as numeric id accepted', [$c, (int)$row($ref)['package_id']], [201, $pid('PKG-SR')]);
[$c, $ref] = attempt($u2, payload($day(78), '14:00', 'Body Scrub', 45, 2, ['packageCode' => 'PKG-CS'])); check('couples package for 2 guests -> couples suite room + package', [$c, (int)$row($ref)['package_id'], (bool)preg_match('/^CS-/', (string)$row($ref)['room_id'])], [201, $pid('PKG-CS'), true]);
[$c] = attempt($u2, payload($day(79), '14:00', 'Body Massage', 60, 1, ['packageCode' => 'PKG-CS'])); check('couples package on a 1-guest (single room) booking -> refused 400', $c, 400);
[$c] = attempt($u2, payload($day(79), '15:00', 'Body Massage', 60, 1, ['packageCode' => 'PKG-NOPE'])); check('unknown package -> refused 400', $c, 400);
$setPkg('PKG-SR', 'INACTIVE');
[$c] = attempt($u2, payload($day(80), '14:00', 'Body Massage', 60, 1, ['packageCode' => 'PKG-SR'])); check('deactivated package chosen explicitly -> refused 409', $c, 409);
[$c, $ref] = attempt($u2, payload($day(81), '14:00', 'Body Massage', 60)); check('deactivated package is not auto-linked (booking still works, no package)', [$c, $row($ref)['package_id']], [201, null]);
$old = $row($ref); check('existing bookings keep their package history when it is deactivated', (int)q1('SELECT COUNT(*) c FROM bookings WHERE user_id=? AND package_id=?', [$u2['id'], $pid('PKG-SR')])['c'] >= 3, true);
$setPkg('PKG-SR', 'ACTIVE');
run("UPDATE bookings SET status='CANCELLED' WHERE user_id=?", [$u2['id']]);   // stay under the 'maximum open bookings' rule
cfg_test_override('PACKAGE_PRICING_RELATIONSHIP', 'ADDITIVE');
[$c, $ref] = attempt($u2, payload($day(82), '14:00', 'Body Massage', 60)); check('ADDITIVE, no package chosen: nothing added', [$c, (float)$row($ref)['amount'], $row($ref)['package_id']], [201, 800.0, null]);
[$c, $ref] = attempt($u2, payload($day(83), '14:00', 'Body Massage', 60, 1, ['packageCode' => 'PKG-SR'])); check('ADDITIVE + package: 800 treatment + 800 package = 1600', [$c, (float)$row($ref)['amount']], [201, 1600.0]);
[$c, $ref] = attempt($u2, payload($day(84), '14:00', 'Body Massage', 60, 5, ['packageCode' => 'PKG-FL'])); check('ADDITIVE family, 5 guests: 5x800 + (4000 + 1x750) = 8750', [$c, (float)$row($ref)['amount']], [201, 8750.0]);
check('...downpayment is computed from that total (25% = 2187.50)', (float)$row($ref)['downpayment_required'], 2187.5);
cfg_test_override('PACKAGE_PRICING_RELATIONSHIP', 'INCLUSIVE');
[$c, $ref] = attempt($u2, payload($day(85), '14:00', 'Body Massage', 60)); check('INCLUSIVE: treatment price covers the package (amount 800, package linked)', [$c, (float)$row($ref)['amount'], (int)$row($ref)['package_id']], [201, 800.0, $pid('PKG-SR')]);
$sh = shape_booking(q1('SELECT b.*, pk.name AS package_name, pk.package_code FROM bookings b LEFT JOIN packages pk ON pk.id=b.package_id WHERE b.id=?', [$row($ref)['id']]));
check('customer/admin booking data names the package', [$sh['packageName'], $sh['packageCode']], ['Single Room Massage', 'PKG-SR']);

echo "Quote and stored booking agree (all package modes, 1 and 3 guests)\n";
function quote_of(array $q): array { try { api_booking_quote($q); return []; } catch (RuntimeException $e) { return json_decode($e->getMessage(), true)['quote'] ?? []; } }
$pi = 0;
foreach (['INDEPENDENT', 'INCLUSIVE', 'ADDITIVE'] as $mode) {
  cfg_test_override('PACKAGE_PRICING_RELATIONSHIP', $mode);
  foreach ([['SR-1', 1, 'PKG-SR'], ['auto', 1, 'PKG-SR']] as [$room, $g, $pk]) {
    $q = ['service' => 'Body Massage', 'duration' => 60, 'guests' => $g, 'roomCode' => $room, 'packageCode' => $pk];
    $quote = quote_of($q);
    [$c, $ref] = attempt($user, payload($day(100 + $pi++), '14:00', 'Body Massage', 60, $g, ['selectedRoomCode' => $room, 'packageCode' => $pk]));
    $stored = $c === 201 ? q1('SELECT amount, downpayment_required FROM bookings WHERE id=?', [$row($ref)['id']]) : null;
    check("$mode room=$room: quote total = stored amount", [$c, $quote['totalAmount'] ?? null], [201, $stored ? (float)$stored['amount'] : null]);
    check("$mode room=$room: quote downpayment = stored", $quote['downpaymentRequired'] ?? null, $stored ? (float)$stored['downpayment_required'] : null);
  }
}
check('downpayment never below minimum unless total is lower', required_downpayment(300.0) <= 300.0 && required_downpayment(1000.0) >= min(1000.0, get_min_downpayment()), true);

printf("\n%d passed, %d failed\n", $pass, $fail); exit($fail ? 1 : 0);
