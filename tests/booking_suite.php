<?php
/* Bella Vista - booking integrity suite (CLI).   php tests/booking_suite.php
   Needs the `bellavista` database with schema.sql + seed.sql. Creates throw-away customers named "BKTEST ..." and their
   bookings, restores everything it changes and deletes its own rows. Do NOT run against real customer data. */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
chdir(dirname(__DIR__));
define('TEST_RUNNER', true);
require_once 'php/portal.php';
require_once 'php/payments.php';

function user_row(int $id): array { $u = q1('SELECT * FROM users WHERE id=?', [$id]); return ['id' => (int)$u['id'], 'fullName' => $u['full_name'], 'email' => $u['email'], 'emailVerified' => true]; }
function create_booking(array $user, array $p): array {   // returns ['ok'=>bool,'code'=>int,'error'=>string,'booking'=>?]
  try { api_create_booking($user, $p); return ['ok' => false, 'code' => 0, 'error' => 'no response']; }
  catch (RuntimeException $e) {
    $j = json_decode($e->getMessage(), true);
    if ($e->getCode() === 201 && is_array($j)) return ['ok' => true, 'code' => 201, 'booking' => $j['booking']];
    return ['ok' => false, 'code' => $e->getCode(), 'error' => $e->getMessage()];
  }
}
if (($argv[1] ?? '') === 'worker') {   // parallel customer: worker <userId> <date> <time> <service> <duration> <room>
  [, , $uid, $date, $time, $svc, $dur, $room] = $argv;
  $r = create_booking(user_row((int)$uid), base($date, $time, $svc, $dur, 1, $room));
  echo $r['ok'] ? 'OK' : 'REJECTED:' . $r['code']; exit;
}
function base(string $date, string $time, string $svc = 'Body Massage', string $dur = '60', int $guests = 1, string $room = 'auto'): array {
  return ['service' => $svc, 'duration' => $dur, 'guests' => $guests, 'date' => $date, 'time' => $time, 'phone' => '09171234567', 'agreeTerms' => true, 'agreeHealth' => true, 'selectedRoomCode' => $room, 'message' => ''];
}
$pass = 0; $fail = 0;
function check(string $label, $actual, $expected): void {
  global $pass, $fail; $ok = $actual === $expected; $ok ? $pass++ : $fail++;
  printf("  [%s] %-70s got=%s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, json_encode($actual), json_encode($expected));
}
function mkuser(string $n): array {
  run("INSERT INTO users (full_name,email,password_hash,email_verified) VALUES (?,?,?,1)", ["BKTEST $n", strtolower("bktest.$n") . '@gmail.com', 'x']);
  return user_row(last_id());
}
function cleanup(): void {
  $ids = "SELECT id FROM bookings WHERE name LIKE 'BKTEST%'";
  foreach (['transactions', 'appointment_room_assignments', 'appointment_therapist_assignments', 'room_cleaning'] as $t) run("DELETE FROM $t WHERE booking_id IN ($ids)");
  run("DELETE FROM bookings WHERE name LIKE 'BKTEST%'");
  run("DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE full_name LIKE 'BKTEST%')");
  run("DELETE FROM activity WHERE user_id IN (SELECT id FROM users WHERE full_name LIKE 'BKTEST%')");
  run("DELETE FROM users WHERE full_name LIKE 'BKTEST%'");
  run("DELETE FROM room_maintenance WHERE reason LIKE 'BKTEST%'");
  run("DELETE FROM admin_log WHERE detail LIKE '%quieter room%' OR admin_name='Tester'");
  run("UPDATE rooms SET room_status='AVAILABLE' WHERE room_code IN ('SR-1','SR-2','SR-3','CS-1','CS-2','FL-1','FL-2')");
  try { run('DROP TRIGGER IF EXISTS bktest_fail_cleaning'); } catch (Throwable $e) {}
}
$savedSettings = qa('SELECT setting_key, setting_value, description FROM payment_settings');
register_shutdown_function(function () use ($savedSettings) {
  cleanup(); run('DELETE FROM payment_settings');
  foreach ($savedSettings as $r) run('INSERT INTO payment_settings (setting_key, setting_value, description) VALUES (?,?,?)', [$r['setting_key'], $r['setting_value'], $r['description'] ?? '']);
});
foreach (['DOWNPAYMENT_PERCENTAGE' => '25', 'MIN_DOWNPAYMENT_AMOUNT' => '500'] as $k => $v) run('INSERT INTO payment_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$k, $v]);
cleanup();
$D  = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+10 days')->format('Y-m-d');
$D2 = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+11 days')->format('Y-m-d');
$D3 = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+12 days')->format('Y-m-d');
$D4 = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+13 days')->format('Y-m-d');
$u1 = mkuser('a'); $u2 = mkuser('b'); $u3 = mkuser('c');
function bcount(): int { return (int)q1("SELECT COUNT(*) c FROM bookings WHERE name LIKE 'BKTEST%'")['c']; }
function asg(int $bid): array { return qa("SELECT * FROM appointment_room_assignments WHERE booking_id=? ORDER BY id", [$bid]); }
function bid(string $ref): int { return (int)substr($ref, strrpos($ref, '-') + 1); }

echo "1. A booking is saved with its room, cleaning slot and payment figures\n";
$r = create_booking($u1, base($D, '14:00')); check('auto booking accepted (201)', $r['ok'], true);
$b1 = bid($r['booking']['id']); $row = q1('SELECT * FROM bookings WHERE id=?', [$b1]);
check('a room code was stored on the booking', (bool)preg_match('/^SR-\d$/', (string)$row['room_id']), true);
check('one ASSIGNED room assignment 14:00-15:00, cleaning until 15:30', array_map(fn($a) => [$a['status'], $a['start_time'], $a['end_time'], $a['cleaning_end_time']], asg($b1)), [['ASSIGNED', '14:00', '15:00', '15:30']]);
check('a SCHEDULED cleaning task 15:00-15:30 exists', (int)q1("SELECT COUNT(*) c FROM room_cleaning WHERE booking_id=? AND status='SCHEDULED' AND start_time='15:00' AND end_time='15:30'", [$b1])['c'], 1);
check('amount 800, downpayment 500, payment UNPAID from the start', [(float)$row['amount'], (float)$row['downpayment_required'], $row['payment_status']], [800.0, 500.0, 'UNPAID']);

echo "2. Room selection is validated on the server\n";
$r = create_booking($u2, base($D, '16:00', 'Foot Massage', '30', 1, 'SR-3')); check('specific free room SR-3 -> exactly SR-3', [$r['ok'], q1('SELECT room_id FROM bookings WHERE id=?', [bid($r['booking']['id'] ?? 'x-0')])['room_id'] ?? null], [true, 'SR-3']);
$n = bcount(); $r = create_booking($u3, base($D, '16:00', 'Body Massage', '60', 1, 'SR-3'));
check('same room, overlapping time -> refused 409', [$r['ok'], $r['code']], [false, 409]); check('...no booking row was left behind', bcount(), $n);
$r = create_booking($u3, base($D, '16:30', 'Foot Massage', '30', 1, 'SR-3')); check('inside the treatment/cleaning window of SR-3 -> refused 409', [$r['ok'], $r['code']], [false, 409]);
$r = create_booking($u3, base($D, '17:00', 'Foot Massage', '30', 1, 'SR-3')); check('starting exactly when the 30-min cleaning buffer ends (17:00) -> accepted', $r['ok'], true);
$r = create_booking($u3, base($D2, '14:00', 'Body Massage', '60', 3, 'SR-1')); check('3 guests into a 1-guest room -> refused 400', [$r['ok'], $r['code']], [false, 400]);
$r = create_booking($u3, base($D2, '14:00', 'Body Massage', '60', 1, 'CS-1')); check('1 guest into a couples suite -> refused 400', [$r['ok'], $r['code']], [false, 400]);
$n = bcount(); $r = create_booking($u3, base($D2, '15:00', 'Body Massage', '60', 1, 'ZZ-9')); check('unknown room ZZ-9 -> refused 400, not silently replaced', [$r['ok'], $r['code'], bcount()], [false, 400, $n]);
run("UPDATE rooms SET room_status='MAINTENANCE' WHERE room_code='SR-2'");
$r = create_booking($u3, base($D2, '15:00', 'Body Massage', '60', 1, 'SR-2')); check('room under MAINTENANCE -> refused 409', [$r['ok'], $r['code']], [false, 409]);
$r = create_booking($u3, base($D2, '17:00', 'Body Massage', '60', 1, 'auto')); check('auto-selection never picks a MAINTENANCE room', $r['ok'] && q1('SELECT room_id FROM bookings WHERE id=?', [bid($r['booking']['id'])])['room_id'] !== 'SR-2', true);
run("UPDATE rooms SET room_status='INACTIVE' WHERE room_code='SR-2'");
$r = create_booking($u2, base($D3, '15:00', 'Body Massage', '60', 1, 'SR-2')); check('INACTIVE room -> refused 409', [$r['ok'], $r['code']], [false, 409]);
run("UPDATE rooms SET room_status='AVAILABLE' WHERE room_code='SR-2'");
run("INSERT INTO room_maintenance (room_id,start_date,start_time,end_date,end_time,reason,status) SELECT id,?,'18:00',?,'20:00','BKTEST repair','SCHEDULED' FROM rooms WHERE room_code='SR-2'", [$D3, $D3]);
$r = create_booking($u2, base($D3, '18:30', 'Body Massage', '60', 1, 'SR-2')); check('room inside a scheduled maintenance window -> refused 409', [$r['ok'], $r['code']], [false, 409]);
$r = create_booking($u2, base($D3, '14:00', 'Body Massage', '60', 1, 'SR-2')); check('same room before the maintenance window -> accepted', $r['ok'], true);
$r = create_booking($u3, base($D3, '14:00', 'Foot Massage', '30', 1, 'Single Room Massage')); check('a category name picks a free room of that category', $r['ok'] && (bool)preg_match('/^SR-/', q1('SELECT room_id FROM bookings WHERE id=?', [bid($r['booking']['id'])])['room_id']), true);
$r = create_booking($u1, base($D3, '14:00', 'Body Scrub', '45', 2, 'auto')); check('2 guests on auto -> a couples suite', $r['ok'] && (bool)preg_match('/^CS-/', q1('SELECT room_id FROM bookings WHERE id=?', [bid($r['booking']['id'])])['room_id']), true);

echo "3. When a required write fails nothing is kept (transaction)\n";
run("CREATE TRIGGER bktest_fail_cleaning BEFORE INSERT ON room_cleaning FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='simulated failure'");
$n = bcount(); $na = (int)q1('SELECT COUNT(*) c FROM appointment_room_assignments')['c'];
$r = create_booking($u1, base($D4, '14:00')); check('cleaning insert fails -> the request is NOT reported as success', $r['ok'], false);
check('...no booking row remains', bcount(), $n); check('...no room assignment row remains', (int)q1('SELECT COUNT(*) c FROM appointment_room_assignments')['c'], $na);
run('DROP TRIGGER bktest_fail_cleaning');
$r = create_booking($u1, base($D4, '14:00')); check('after the fault is removed the same booking succeeds', $r['ok'], true);

echo "4. Parallel customers cannot double-book a room\n";
$us = []; foreach (['p1','p2','p3','p4','p5'] as $k) $us[] = mkuser($k)['id'];
$D5 = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+14 days')->format('Y-m-d');
$procs = []; $pipes = [];
foreach ($us as $i => $uid) $procs[$i] = proc_open([PHP_BINARY, __FILE__, 'worker', (string)$uid, $D5, '15:00', ['Body Massage','Foot Massage','Body Scrub','Facial Treatment','Hair and Scalp'][$i], ['60','30','45','45','30'][$i], 'SR-1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
$res = []; foreach ($procs as $i => $pr) { $res[] = trim(stream_get_contents($pipes[$i][1])); proc_close($pr); }
check('5 customers want SR-1 at 15:00 at once -> exactly 1 accepted', count(array_filter($res, fn($x) => $x === 'OK')), 1);
check('...exactly 1 ASSIGNED row for SR-1 on that day', (int)q1("SELECT COUNT(*) c FROM appointment_room_assignments a JOIN rooms r ON r.id=a.room_id WHERE r.room_code='SR-1' AND a.assigned_date=? AND a.status='ASSIGNED'", [$D5])['c'], 1);
$ov = qa("SELECT a.id FROM appointment_room_assignments a JOIN appointment_room_assignments b ON a.room_id=b.room_id AND a.assigned_date=b.assigned_date AND a.id<b.id AND a.status='ASSIGNED' AND b.status='ASSIGNED' WHERE a.start_time < b.cleaning_end_time AND b.start_time < a.cleaning_end_time");
check('...and no two active assignments overlap anywhere', count($ov), 0);

echo "5. Admin room change: allowed with a reason, history kept, customer told\n";
$ra = create_booking($u1, base($D2, '19:00', 'Foot Massage', '30', 1, 'SR-1')); $bk = bid($ra['booking']['id']);
$fails = function (callable $f, int $code, string $label) { try { $f(); check($label, 'accepted', $code); } catch (RuntimeException $e) { check($label, $e->getCode(), $code); } };
$id = fn(string $c) => (int)q1('SELECT id FROM rooms WHERE room_code=?', [$c])['id'];
$fails(fn() => assign_appointment_room($bk, $id('SR-3'), 'Tester'), 400, 'changing the customer room without a reason -> 400');
$logBefore = (int)q1("SELECT COUNT(*) c FROM admin_log WHERE action='Room changed'")['c'];
$res = assign_appointment_room($bk, $id('SR-3'), 'Tester', 'Customer asked for the quieter room'); check('change with a reason succeeds', $res['changed'], true);
check('booking now shows SR-3', q1('SELECT room_id FROM bookings WHERE id=?', [$bk])['room_id'], 'SR-3');
check('history: old SR-1 assignment kept as RELEASED, new SR-3 ASSIGNED', array_map(fn($a) => $a['status'], asg($bk)), ['RELEASED', 'ASSIGNED']);
check('exactly one SCHEDULED cleaning task, on the new room', array_map(fn($c) => (int)$c['room_id'], qa("SELECT room_id FROM room_cleaning WHERE booking_id=? AND status='SCHEDULED'", [$bk])), [$id('SR-3')]);
check('activity log records the change with its reason', (int)q1("SELECT COUNT(*) c FROM admin_log WHERE action='Room changed'")['c'] - $logBefore, 1);
check('...and the log text contains the reason and both rooms', (bool)q1("SELECT 1 x FROM admin_log WHERE action='Room changed' AND detail LIKE '%SR-1%SR-3%quieter room%' ORDER BY id DESC LIMIT 1"), true);
check('customer received a notification', (int)q1("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND text LIKE '%room was changed%'", [$u1['id']])['c'], 1);
$fails(fn() => assign_appointment_room($bk, $id('CS-1'), 'Tester', 'Needs a bigger room'), 400, 'admin cannot move 1 guest into a couples suite (capacity) -> 400');
$other = create_booking($u2, base($D2, '19:00', 'Body Scrub', '45', 1, 'SR-2'));
$fails(fn() => assign_appointment_room($bk, $id('SR-2'), 'Tester', 'Swap please'), 409, 'admin cannot move onto a room that is taken at that time -> 409');
run("UPDATE rooms SET room_status='MAINTENANCE' WHERE room_code='SR-3'");
$fails(fn() => assign_appointment_room($bk, $id('SR-3'), 'Tester', 'again'), 409, 'admin cannot assign a MAINTENANCE room -> 409');
run("UPDATE rooms SET room_status='AVAILABLE' WHERE room_code='SR-3'");
set_status($bk, 'CANCELLED', 'x', 'Customer');
$fails(fn() => assign_appointment_room($bk, $id('SR-1'), 'Tester', 'Cancelled one'), 409, 'a cancelled booking cannot be given a room -> 409');
check('cancelling released the room and the cleaning task', [(int)q1("SELECT COUNT(*) c FROM appointment_room_assignments WHERE booking_id=? AND status='ASSIGNED'", [$bk])['c'], (int)q1("SELECT COUNT(*) c FROM room_cleaning WHERE booking_id=? AND status='SCHEDULED'", [$bk])['c']], [0, 0]);

echo "6. Room references stay consistent (bookings.room_id = code, assignments = numeric id)\n";
$mm = qa("SELECT b.id FROM bookings b JOIN appointment_room_assignments a ON a.booking_id=b.id AND a.status='ASSIGNED' JOIN rooms r ON r.id=a.room_id WHERE b.archived=0 AND b.status IN ('PENDING','CONFIRMED','IN_PROGRESS') AND (b.room_id IS NULL OR b.room_id <> r.room_code)");
check('every active booking with an assignment names the same room in both places', count($mm), 0);

echo "7. Admin edit of date/time moves the room, cleaning slot (move_booking_resources)\n";
$mv = create_booking($u1, base($D4, '14:00', 'Body Massage', '60', 1, 'SR-1'));
check('booking for the move test created', $mv['ok'], true);
$mvId = (int)substr($mv['booking']['id'], strrpos($mv['booking']['id'], '-') + 1);
$bkRow = q1('SELECT * FROM bookings WHERE id=?', [$mvId]);
tx(fn() => move_booking_resources($bkRow, $D4, '17:00'));
$ra = q1("SELECT assigned_date, start_time FROM appointment_room_assignments WHERE booking_id=? AND status='ASSIGNED'", [$mvId]);
check('room assignment follows the new time', [$ra['assigned_date'], substr($ra['start_time'], 0, 5)], [$D4, '17:00']);
$rc = q1('SELECT cleaning_date, start_time FROM room_cleaning WHERE booking_id=?', [$mvId]);
check('cleaning slot follows (starts when treatment ends, 18:00)', [$rc['cleaning_date'], substr($rc['start_time'], 0, 5)], [$D4, '18:00']);

echo "8. Simulated reminders\n";
$soon = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+3 hours');
run("INSERT INTO bookings (user_id,name,email,service,preferred_date,time,duration,guests,amount,status) VALUES (?,?,?,?,?,?,?,?,?,'CONFIRMED')", [$u2['id'], 'BKTEST rem', 'bktest.b@gmail.com', 'Body Massage', $soon->format('Y-m-d'), $soon->format('H:i'), '60', 1, 800]);
$r1 = send_booking_reminders(24);
check('first run sends a reminder for the booking in 3 hours', $r1['sent'] >= 1, true);
$n1 = (int)q1("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND text LIKE 'Reminder:%'", [$u2['id']])['c'];
$r2 = send_booking_reminders(24);
$n2 = (int)q1("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND text LIKE 'Reminder:%'", [$u2['id']])['c'];
check('second run does not remind again', [$n1, $n2], [1, 1]);
run('UPDATE users SET prefs=? WHERE id=?', [json_encode(['bookingReminders' => false]), $u3['id']]);
run("INSERT INTO bookings (user_id,name,email,service,preferred_date,time,duration,guests,amount,status) VALUES (?,?,?,?,?,?,?,?,?,'CONFIRMED')", [$u3['id'], 'BKTEST rem2', 'bktest.c@gmail.com', 'Body Massage', $soon->format('Y-m-d'), $soon->format('H:i'), '60', 1, 800]);
send_booking_reminders(24);
check('customer with reminders off gets none', (int)q1("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND text LIKE 'Reminder:%'", [$u3['id']])['c'], 0);

echo "9. Phone number: saved on the first booking, kept on later ones\n";
$u9 = mkuser('phone'); $dd = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+40 days')->format('Y-m-d');
check('new customer has no saved phone', (string)q1('SELECT phone FROM users WHERE id=?', [$u9['id']])['phone'], '');
$p1 = create_booking($u9, base($dd, '14:00')); check('first booking created', $p1['ok'], true);
check('first booking saves its number to the profile', (string)q1('SELECT phone FROM users WHERE id=?', [$u9['id']])['phone'], '0917 123 4567');
$p2 = create_booking($u9, ['phone' => '09189998888'] + base($dd, '17:00')); check('second booking with another number created', $p2['ok'], true);
check('profile number is not overwritten by the other number', (string)q1('SELECT phone FROM users WHERE id=?', [$u9['id']])['phone'], '0917 123 4567');
check('...but that booking keeps the number typed for it', (string)q1('SELECT phone FROM bookings WHERE id=?', [(int)substr($p2['booking']['id'], strrpos($p2['booking']['id'], '-') + 1)])['phone'], '0918 999 8888');

echo "10. Last booking pre-fill and default-number change\n";
$lastQ = function (array $u) { try { api_last_booking($u); return null; } catch (RuntimeException $e) { return json_decode($e->getMessage(), true)['last'] ?? null; } };
$ub = mkuser('last'); check('customer with no booking has no last booking', $lastQ($ub), null);
$ld = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+45 days')->format('Y-m-d');
$b1 = create_booking($ub, ['message' => 'Allergic to nuts'] + base($ld, '14:00', 'Body Massage', '90')); check('booking created', $b1['ok'], true);
$lb = $lastQ($ub); check('last booking returns treatment, length and note', [$lb['service'] ?? null, $lb['duration'] ?? null, $lb['message'] ?? null], ['Body Massage', '90', 'Allergic to nuts']);
$setPhone = function (array $u, string $p) { try { portal_set_phone($u, ['phone' => $p]); return 200; } catch (RuntimeException $e) { return $e->getCode(); } };
check('invalid number is refused', $setPhone($ub, '123'), 400);
$setPhone($ub, '09189998888'); check('valid number becomes the default', (string)q1('SELECT phone FROM users WHERE id=?', [$ub['id']])['phone'], '0918 999 8888');

echo "11. Cancelling a paid booking: cancelled first, then refunded\n";
$uc = mkuser('cancel'); $cd = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify('+50 days')->format('Y-m-d');
$c1 = create_booking($uc, base($cd, '14:00')); check('booking created', $c1['ok'], true);
$cid = (int)substr($c1['booking']['id'], strrpos($c1['booking']['id'], '-') + 1);
try { process_demo_payment($cid, 'GCASH_DEMO', 'DOWNPAYMENT'); $blocked = false; } catch (RuntimeException $e) { $blocked = $e->getCode() === 409; }
check('a PENDING booking cannot be paid yet', $blocked, true);
run("UPDATE bookings SET status='CONFIRMED' WHERE id=?", [$cid]);
process_demo_payment($cid, 'GCASH_DEMO', 'DOWNPAYMENT');
try { portal_cancel($uc, $c1['booking']['id'], ['reason' => 'BKTEST cancel']); } catch (RuntimeException $e) {}
check('booking is CANCELLED', q1('SELECT status FROM bookings WHERE id=?', [$cid])['status'], 'CANCELLED');
$nc = q1("SELECT MIN(id) i FROM notifications WHERE user_id=? AND text LIKE '%cancelled%'", [$uc['id']])['i'];
$nr = q1("SELECT MIN(id) i FROM notifications WHERE user_id=? AND text LIKE '%refund%'", [$uc['id']])['i'];
check('"cancelled" notification comes before the refund one', $nc !== null && $nr !== null && (int)$nc < (int)$nr, true);
check('refund recorded', (int)q1("SELECT COUNT(*) c FROM transactions WHERE booking_id=? AND transaction_type='REFUND'", [$cid])['c'], 1);

printf("\n%d passed, %d failed\n", $pass, $fail); exit($fail ? 1 : 0);
