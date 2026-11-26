<?php
/* Bella Vista - payment regression suite (CLI).  Replaces the removed Phase 1 phase1_payment_check.php.

     php tests/payment_suite.php

   Needs the `bellavista` database with database/schema.sql and database/seed.sql imported (config in php/config.php).
   It writes throw-away bookings named "TEST-PAY", restores every payment setting it changes and removes its own rows
   again, so it is safe to run on a test database. Do NOT run it against a database holding real customer data.
   Prints PASS / FAIL per check and exits 1 when anything fails. */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
chdir(dirname(__DIR__));
define('TEST_RUNNER', true);                      // fail() throws instead of exiting
require_once 'php/portal.php';
require_once 'php/payments.php';

/* parallel worker mode: one process = one customer double-clicking "Pay" */
if (($argv[1] ?? '') === 'worker') {
  usleep((int)$argv[3]);
  try { $r = process_demo_payment((int)$argv[2], 'GCASH_DEMO', 'DOWNPAYMENT'); echo 'OK:' . $r['status']; }
  catch (Throwable $e) { echo 'REJECTED:' . $e->getCode(); }
  exit;
}

$pass = 0; $fail = 0; $notes = [];
function check(string $label, $actual, $expected): void {
  global $pass, $fail;
  $ok = ($actual === $expected) || (is_numeric($actual) && is_numeric($expected) && abs((float)$actual - (float)$expected) < 0.005);
  $ok ? $pass++ : $fail++;
  printf("  [%s] %-66s got=%s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, json_encode($actual), json_encode($expected));
}
/* the call must be refused with the given HTTP code (and optionally a message fragment) */
function refused(string $label, callable $fn, int $code, string $frag = ''): void {
  global $pass, $fail;
  try { $fn(); $ok = false; $got = 'accepted'; }
  catch (RuntimeException $e) { $ok = $e->getCode() === $code && ($frag === '' || stripos($e->getMessage(), $frag) !== false); $got = $e->getCode() . ' ' . substr($e->getMessage(), 0, 60); }
  $ok ? $pass++ : $fail++;
  printf("  [%s] %-66s got=%s expected=%d %s\n", $ok ? 'PASS' : 'FAIL', $label, json_encode($got), $code, $frag);
}
function setting(string $k, ?string $v): void {
  if ($v === null) run('DELETE FROM payment_settings WHERE setting_key = ?', [$k]);
  else run('INSERT INTO payment_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$k, $v]);
}
function mk(float $amount, string $hoursAhead = '+30 days', string $status = 'CONFIRMED'): int {
  $d = (new DateTime('now', new DateTimeZone(cfg('TIMEZONE'))))->modify($hoursAhead);
  run("INSERT INTO bookings (name, email, service, preferred_date, time, duration, guests, amount, status) VALUES ('TEST-PAY','t@gmail.com','Body Massage',?,?,'60',1,?,?)",
      [$d->format('Y-m-d'), $d->format('H:i'), $amount, $status]);
  return last_id();
}
function sum(int $id): array { return calculate_booking_payment_summary($id); }
function pstat(int $id): string { return (string)q1('SELECT payment_status s FROM bookings WHERE id=?', [$id])['s']; }
function txn_count(int $id, string $status): int { return (int)q1('SELECT COUNT(*) c FROM transactions WHERE booking_id=? AND status=?', [$id, $status])['c']; }

$saved = qa('SELECT setting_key, setting_value, description FROM payment_settings');
function cleanup(): void {
  run("DELETE FROM transactions WHERE booking_id IN (SELECT id FROM bookings WHERE name='TEST-PAY')");
  run("DELETE FROM appointment_room_assignments WHERE booking_id IN (SELECT id FROM bookings WHERE name='TEST-PAY')");
  run("DELETE FROM bookings WHERE name='TEST-PAY'");
}
register_shutdown_function(function () use ($saved) {
  cleanup(); run('DELETE FROM payment_settings');
  foreach ($saved as $r) run('INSERT INTO payment_settings (setting_key, setting_value, description) VALUES (?,?,?)', [$r['setting_key'], $r['setting_value'], $r['description'] ?? '']);
});
cleanup();

echo "1. Seed data present\n";
check('room categories', (int)q1('SELECT COUNT(*) c FROM room_categories')['c'], 3);
check('physical rooms',  (int)q1('SELECT COUNT(*) c FROM rooms')['c'], 7);
check('packages',        (int)q1('SELECT COUNT(*) c FROM packages')['c'], 3);
check('therapists',      (int)q1('SELECT COUNT(*) c FROM therapists')['c'], 4);

echo "2. Downpayment = min(total, max(minimum, total x percentage))   [minimum 500, 25%]\n";
setting('DOWNPAYMENT_PERCENTAGE', '25'); setting('MIN_DOWNPAYMENT_AMOUNT', '500');
foreach ([[800, 500], [1100, 500], [4000, 1000], [300, 300], [500, 500], [2000, 500], [2001, 500.25], [1999.99, 500], [4001.01, 1000.25]] as [$t, $e])
  check("PHP $t -> $e", sum(mk($t))['downpaymentRequired'], (float)$e);
setting('MIN_DOWNPAYMENT_AMOUNT', '700'); check('admin minimum 700 is honoured (PHP 800)', sum(mk(800))['downpaymentRequired'], 700.0);
setting('DOWNPAYMENT_PERCENTAGE', '50'); check('admin percentage 50 (PHP 4000)', sum(mk(4000))['downpaymentRequired'], 2000.0);
setting('DOWNPAYMENT_PERCENTAGE', null); setting('MIN_DOWNPAYMENT_AMOUNT', null); check('no stored settings -> defaults 25% / 500 (PHP 800)', sum(mk(800))['downpaymentRequired'], 500.0);
setting('DOWNPAYMENT_PERCENTAGE', '25'); setting('MIN_DOWNPAYMENT_AMOUNT', '500');

echo "3. Full payment setting (server enforced)\n";
setting('ALLOW_FULL_PAYMENT', '0'); $b = mk(800);
check('ALLOW_FULL_PAYMENT=0 -> full_payment_allowed()', full_payment_allowed(), false);
check('summary reports allowFullPayment=false', sum($b)['allowFullPayment'], false);
refused('FULL_PAYMENT is refused when disabled', fn() => process_demo_payment($b, 'GCASH_DEMO', 'FULL_PAYMENT'), 403, 'Full payment');
check('no transaction was created by the refused attempt', (int)q1('SELECT COUNT(*) c FROM transactions WHERE booking_id=?', [$b])['c'], 0);
check('DOWNPAYMENT still works when full payment is off', process_demo_payment($b, 'GCASH_DEMO', 'DOWNPAYMENT')['status'], 'PAID');
setting('ALLOW_FULL_PAYMENT', '1'); $b2 = mk(800);
check('ALLOW_FULL_PAYMENT=1 -> FULL_PAYMENT accepted', process_demo_payment($b2, 'MAYA_DEMO', 'FULL_PAYMENT')['status'], 'PAID');
check('...and the booking is PAID', pstat($b2), 'PAID');
setting('ALLOW_FULL_PAYMENT', null); check('missing setting row -> default allowed', full_payment_allowed(), true);

echo "4. Late-cancellation forfeiture\n";
setting('FORFEIT_LATE_CANCELLATION', '1');
$late = mk(800, '+10 hours'); process_demo_payment($late, 'GCASH_DEMO', 'DOWNPAYMENT'); set_status($late, 'CANCELLED', 'test', 'Customer');
check('paid downpayment, cancelled <24h, setting ON -> FORFEITED', pstat($late), 'FORFEITED');
check('...the downpayment transaction is FORFEITED', txn_count($late, 'FORFEITED'), 1);
check('...history kept (no transaction deleted)', (int)q1('SELECT COUNT(*) c FROM transactions WHERE booking_id=?', [$late])['c'], 1);
$logs = (int)q1("SELECT COUNT(*) c FROM admin_log WHERE action='Downpayment forfeited' AND detail LIKE '%policy%'")['c'];
check('calling forfeit again changes nothing (no duplicate)', forfeit_demo_downpayment($late, 'again', 'Test'), false);
check('...and writes no second log entry', (int)q1("SELECT COUNT(*) c FROM admin_log WHERE action='Downpayment forfeited' AND detail LIKE '%policy%'")['c'], $logs);
setting('FORFEIT_LATE_CANCELLATION', '0');
$off = mk(800, '+10 hours'); process_demo_payment($off, 'GCASH_DEMO', 'DOWNPAYMENT'); set_status($off, 'CANCELLED', 'test', 'Customer');
check('same cancellation, setting OFF -> NOT forfeited', pstat($off), 'PARTIALLY_PAID');
check('...the downpayment transaction stays PAID', txn_count($off, 'PAID'), 1);
setting('FORFEIT_LATE_CANCELLATION', '1');
$early = mk(800, '+3 days'); process_demo_payment($early, 'GCASH_DEMO', 'DOWNPAYMENT'); set_status($early, 'CANCELLED', 'test', 'Customer');
check('cancelled with >24h notice -> NOT forfeited', pstat($early), 'PARTIALLY_PAID');
$unpaid = mk(800, '+10 hours'); set_status($unpaid, 'CANCELLED', 'test', 'Customer');
check('UNPAID booking cancelled <24h -> stays UNPAID (nothing to forfeit)', pstat($unpaid), 'UNPAID');
$full = mk(800, '+10 hours'); process_demo_payment($full, 'GCASH_DEMO', 'FULL_PAYMENT'); set_status($full, 'CANCELLED', 'test', 'Customer');
check('fully-paid booking cancelled <24h: status matches its transactions', pstat($full), sum($full)['paymentStatus']);
check('...a FULL_PAYMENT is not silently relabelled FORFEITED', txn_count($full, 'FORFEITED'), 0);
// timing must follow Asia/Manila whatever the PHP server timezone is
$tz = mk(800, '+23 hours'); process_demo_payment($tz, 'GCASH_DEMO', 'DOWNPAYMENT');
$prev = date_default_timezone_get(); date_default_timezone_set('America/Los_Angeles'); set_status($tz, 'CANCELLED', 'test', 'Customer'); date_default_timezone_set($prev);
check('23h notice is judged in Manila time even if PHP runs in another timezone', pstat($tz), 'FORFEITED');
$tz2 = mk(800, '+25 hours'); process_demo_payment($tz2, 'GCASH_DEMO', 'DOWNPAYMENT');
date_default_timezone_set('Pacific/Auckland'); set_status($tz2, 'CANCELLED', 'test', 'Customer'); date_default_timezone_set($prev);
check('25h notice is NOT late even if PHP runs in another timezone', pstat($tz2), 'PARTIALLY_PAID');

echo "5. Duplicate and invalid payments\n";
$d = mk(800); check('first DOWNPAYMENT accepted', process_demo_payment($d, 'GCASH_DEMO', 'DOWNPAYMENT')['status'], 'PAID');
refused('second DOWNPAYMENT refused (already satisfied)', fn() => process_demo_payment($d, 'GCASH_DEMO', 'DOWNPAYMENT'), 400, 'already been satisfied');
check('client-supplied amount is ignored (server charges 300 balance)', process_demo_payment($d, ['paymentMethod' => 'GCASH_DEMO', 'paymentType' => 'BALANCE_PAYMENT', 'amount' => 1])['transaction']['amount'], 300.0);
refused('paying a fully paid booking again is refused', fn() => process_demo_payment($d, 'GCASH_DEMO', 'BALANCE_PAYMENT'), 400, 'paid in full');
refused('invalid payment method refused',  fn() => process_demo_payment(mk(800), 'BITCOIN', 'DOWNPAYMENT'), 400, 'method');
refused('invalid payment type refused',    fn() => process_demo_payment(mk(800), 'GCASH_DEMO', 'FREE'), 400, 'type');
refused('unknown booking refused',         fn() => process_demo_payment(99999999, 'GCASH_DEMO', 'DOWNPAYMENT'), 404);
$pn = mk(800, '+30 days', 'PENDING'); refused('cannot pay while the booking is still PENDING', fn() => process_demo_payment($pn, 'GCASH_DEMO', 'DOWNPAYMENT'), 409, 'confirms');
$c = mk(800, '+30 days', 'CANCELLED'); refused('cannot pay for a cancelled booking', fn() => process_demo_payment($c, 'GCASH_DEMO', 'DOWNPAYMENT'), 409, 'cancel');
$r = mk(800, '+30 days', 'REJECTED');  refused('cannot pay for a rejected booking',  fn() => process_demo_payment($r, 'GCASH_DEMO', 'DOWNPAYMENT'), 409);
// two customers: ownership
run("INSERT IGNORE INTO users (id, full_name, email, password_hash) VALUES (900001,'Owner','owner.test@gmail.com','x'),(900002,'Other','other.test@gmail.com','x')");
$o = mk(800); run('UPDATE bookings SET user_id=900001 WHERE id=?', [$o]);
refused("another customer cannot pay someone else's booking", fn() => process_demo_payment($o, 'GCASH_DEMO', 'DOWNPAYMENT', null, 900002), 403);
check('the owner can pay', process_demo_payment($o, 'GCASH_DEMO', 'DOWNPAYMENT', null, 900001)['status'], 'PAID');
// parallel double-click: 6 processes at once, exactly one may succeed
$p = mk(800); $procs = [];
for ($i = 0; $i < 6; $i++) { $procs[] = proc_open([PHP_BINARY, __FILE__, 'worker', (string)$p, '0'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]); }
$res = []; foreach ($procs as $i => $pr) { $res[] = trim(stream_get_contents($pipes[$i][1])); proc_close($pr); }
check('6 simultaneous DOWNPAYMENT requests -> exactly 1 PAID transaction', txn_count($p, 'PAID'), 1);
check('...and the other 5 were rejected', count(array_filter($res, fn($x) => str_starts_with($x, 'REJECTED'))), 5);
check('...booking is PARTIALLY_PAID, paid amount = 500', (float)q1('SELECT paid_amount a FROM bookings WHERE id=?', [$p])['a'], 500.0);

echo "6. Payment status transitions, cash, refunds\n";
$t = mk(1100); check('new booking is UNPAID', sum($t)['paymentStatus'], 'UNPAID');
process_demo_payment($t, 'GCASH_DEMO', 'DOWNPAYMENT'); check('after downpayment -> PARTIALLY_PAID', pstat($t), 'PARTIALLY_PAID');
check('remaining balance = 600', sum($t)['remainingBalance'], 600.0);
process_demo_payment($t, 'MAYA_DEMO', 'BALANCE_PAYMENT'); check('after balance -> PAID', pstat($t), 'PAID');
refused('refund larger than net paid refused', fn() => process_demo_refund($t, 5000, 'x', 'Test'), 400, 'cannot exceed');
refused('refund of zero refused',              fn() => process_demo_refund($t, 0, 'x', 'Test'), 400);
refused('negative refund refused',             fn() => process_demo_refund($t, -50, 'x', 'Test'), 400);
$ref = process_demo_refund($t, 200, 'partial', 'Test');
check('partial refund succeeds and reports PARTIALLY_REFUNDED', $ref['paymentSummary']['paymentStatus'], 'PARTIALLY_REFUNDED');
check('...booking row holds a valid stored status (PARTIALLY_PAID)', pstat($t), 'PARTIALLY_PAID');
process_demo_refund($t, 900, 'rest', 'Test'); check('refund of the rest -> REFUNDED', pstat($t), 'REFUNDED');
$cash = mk(800); $cr = process_demo_payment($cash, 'CASH_AT_SPA', 'DOWNPAYMENT');
check('cash at spa creates a PENDING record', $cr['status'], 'PENDING'); check('...money not counted yet (UNPAID)', pstat($cash), 'UNPAID');
admin_confirm_cash_payment((int)$cr['transaction']['numericId'], 'Test');
refused('confirming the same cash payment twice is refused', fn() => admin_confirm_cash_payment((int)$cr['transaction']['numericId'], 'Test'), 404);
check('admin confirms cash -> PARTIALLY_PAID', pstat($cash), 'PARTIALLY_PAID');
$f = mk(800); $fr = process_demo_payment($f, 'GCASH_DEMO', 'DOWNPAYMENT', null, null, 'FAILURE');
check('simulated failure: FAILED, nothing counted', [$fr['status'], pstat($f)], ['FAILED', 'UNPAID']);

echo "Atomicity: a failure in the LAST write (audit log) must leave nothing half-done\n";
function inject_failure(bool $on): void {
  run('DROP TRIGGER IF EXISTS bv_test_fail_admin_log');
  if ($on) run("CREATE TRIGGER bv_test_fail_admin_log BEFORE INSERT ON admin_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected failure'");
}
register_shutdown_function(fn() => inject_failure(false));
$a = mk(1000); inject_failure(true);
try { process_demo_payment($a, 'GCASH_DEMO', 'DOWNPAYMENT'); $thrown = false; } catch (Throwable $e) { $thrown = true; }
check('payment: mid-operation failure is reported', $thrown, true);
inject_failure(false);
check('payment: no PAID transaction left behind', txn_count($a, 'PAID'), 0);
check('payment: booking still UNPAID', pstat($a), 'UNPAID');
$a2 = mk(1000); process_demo_payment($a2, 'GCASH_DEMO', 'DOWNPAYMENT'); inject_failure(true);
try { process_demo_refund($a2, 100, 'x', 'Test'); $thrown = false; } catch (Throwable $e) { $thrown = true; }
inject_failure(false);
check('refund: mid-operation failure is reported', $thrown, true);
check('refund: no REFUND row left behind', (int)q1("SELECT COUNT(*) c FROM transactions WHERE booking_id=? AND transaction_type='REFUND'", [$a2])['c'], 0);
$a3 = mk(800); $cr3 = process_demo_payment($a3, 'CASH_AT_SPA', 'DOWNPAYMENT'); inject_failure(true);
try { admin_confirm_cash_payment((int)$cr3['transaction']['numericId'], 'Test'); $thrown = false; } catch (Throwable $e) { $thrown = true; }
inject_failure(false);
check('cash confirm: mid-operation failure is reported', $thrown, true);
check('cash confirm: payment still PENDING (not half-confirmed)', txn_count($a3, 'PENDING'), 1);
$a4 = mk(1000); process_demo_payment($a4, 'GCASH_DEMO', 'DOWNPAYMENT'); inject_failure(true);
try { forfeit_demo_downpayment($a4, 'late', 'Test'); $thrown = false; } catch (Throwable $e) { $thrown = true; }
inject_failure(false);
check('forfeit: mid-operation failure is reported', $thrown, true);
check('forfeit: downpayment still PAID, not FORFEITED', [txn_count($a4, 'PAID'), txn_count($a4, 'FORFEITED')], [1, 0]);
$q = required_downpayment(1000.0); check('required_downpayment(1000) = max(500, 25%) = 500', $q, 500.0);
check('required_downpayment(300) is capped at the total', required_downpayment(300.0), 300.0);
check('required_downpayment(4000) = 25% = 1000', required_downpayment(4000.0), 1000.0);

echo "Storage engine: tx() rollback only works on InnoDB\n";
$bad = qa("SELECT table_name n, engine e FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine <> 'InnoDB'");
check('every table is InnoDB', array_column($bad, 'n'), []);

printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
