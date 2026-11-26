<?php
/* Bella Vista - logic checks that need NO database (CLI).   php tests/logic_suite.php
   Service price parser, and a static guard that every financial write sequence runs inside tx(). */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
chdir(dirname(__DIR__));
define('TEST_RUNNER', true);
require_once 'php/schedule.php';
$pass = 0; $fail = 0;
function check(string $label, $actual, $expected): void { global $pass, $fail; $ok = $actual === $expected; $ok ? $pass++ : $fail++; printf("  [%s] %-60s got=%s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, json_encode($actual), json_encode($expected)); }

echo "Service price parser\n";
check('"800, 1,100" with 60, 90 (thousands comma)', parse_service_options('60, 90', '800, 1,100'), [[60, 90], [60 => 800.0, 90 => 1100.0]]);
check('"800, 1100"', parse_service_options('60, 90', '800, 1100'), [[60, 90], [60 => 800.0, 90 => 1100.0]]);
check('"800,900" without space is two prices', parse_service_options('60,90', '800,900'), [[60, 90], [60 => 800.0, 90 => 900.0]]);
check('single "1,100" is one price', parse_service_options('60', '1,100'), [[60], [60 => 1100.0]]);
check('peso signs and semicolons', parse_service_options('60, 90', '₱800; ₱1,100'), [[60, 90], [60 => 800.0, 90 => 1100.0]]);
check('count mismatch refused', parse_service_options('60, 90', '800'), null);
check('duration under 15 refused', parse_service_options('10', '800'), null);
check('duplicate duration refused', parse_service_options('60, 60', '800, 900'), null);
check('zero price refused', parse_service_options('60', '0'), null);
check('text price refused', parse_service_options('60', 'abc'), null);

echo "Financial writes are transactional (static)\n";
$src = file_get_contents('php/payments.php');
foreach (['process_demo_payment', 'process_demo_refund', 'forfeit_demo_downpayment', 'admin_confirm_cash_payment'] as $fn) {
  $a = strpos($src, "function $fn("); $b = strpos($src, "\nfunction ", $a + 10); $body = substr($src, $a, ($b ?: strlen($src)) - $a);
  check("$fn takes booking_lock", str_contains($body, 'booking_lock('), true);
  check("$fn wraps writes in tx()", str_contains($body, 'tx(function'), true);
}
$js = file_get_contents('js/booking.js');
check('booking.js has no hard-coded 25% downpayment', str_contains($js, '* 0.25') || str_contains($js, '25% demo'), false);
check('booking.js confirms with the server quote', str_contains($js, 'api/quote'), true);

echo "Every page loads the scripts its JS depends on\n";
foreach (glob('*.html') as $h) {
  $html = file_get_contents($h);
  if (preg_match('#js/(booking|account|admin)\.js#', $html)) check("$h loads js/phone.js (phone helpers)", str_contains($html, 'js/phone.js'), true);
}
echo "Admin name, resource sync and reminders (static)\n";
$all = file_get_contents('php/portal.php') . file_get_contents('api/index.php');
check('no admin name is read from the admin id as an array', str_contains($all, 'is_array($admin)'), false);
$p = file_get_contents('php/portal.php');
$a = strpos($p, 'function admin_save_booking('); $body = substr($p, $a, 2500);
check('admin booking edit calls move_booking_resources', str_contains($body, 'move_booking_resources('), true);
check('reschedule approval uses the same helper', str_contains(substr($p, strpos($p, 'function admin_decide_reschedule')), 'move_booking_resources('), true);
check('reminders endpoint exists', str_contains(file_get_contents('api/index.php'), '/admin/reminders/run'), true);
check('admin dashboard has the reminders button', str_contains(file_get_contents('js/admin.js'), 'data-a="remind"'), true);
check('portal hides the on-the-fly reminder when a saved one exists', str_contains(file_get_contents('js/account.js'), 'startsWith("Reminder:")'), true);
echo "Text encoding\n";
$moj = [];
foreach (array_merge(glob('*.html'), glob('js/*.js'), glob('php/*.php'), glob('css/*.css')) as $f) if (preg_match('/â€|â‚|Ã[©¡±³]|Â·/u', file_get_contents($f))) $moj[] = $f;
check('no garbled characters (UTF-8 saved twice) in pages, scripts or styles', $moj, []);
check('service dialog passes the treatment to the booking form', str_contains(file_get_contents('js/main.js'), '"?service="'), true);
check('booking form prefills the saved phone', str_contains(file_get_contents('js/booking.js'), 'prefillPhone'), true);
check('first booking saves phone to profile', str_contains(file_get_contents('php/portal.php'), "UPDATE users SET phone=? WHERE id=? AND (phone IS NULL OR phone='')"), true);
check('booking form asks before changing the default number', str_contains(file_get_contents('js/booking.js'), 'Use this number from now on?'), true);
check('booking form pre-fills the last booking', str_contains(file_get_contents('js/booking.js'), 'api/bookings/last'), true);
check('routes exist: /bookings/last and /portal/phone', str_contains(file_get_contents('api/index.php'), "'/bookings/last'") && str_contains(file_get_contents('api/index.php'), "'/portal/phone'"), true);
check('no email-only password reset route exists', str_contains(file_get_contents('api/index.php'), 'reset-direct') && !preg_match('/path === .\/auth\/reset-direct/', file_get_contents('api/index.php')), true);
$pc = file_get_contents('php/portal.php'); $pc = substr($pc, strpos($pc, 'function portal_cancel'), 3000);
check('customer cancel: booking cancelled before the refund is made', strpos($pc, "set_status((int)\$row['id'], 'CANCELLED'") < strpos($pc, 'process_demo_refund('), true);
check('server refuses payment for PENDING bookings', str_contains(file_get_contents('php/payments.php'), 'awaiting_confirmation'), true);
check('portal shows Pay only for CONFIRMED bookings', str_contains(file_get_contents('js/account.js'), 'outstanding > 0 && b.status === "CONFIRMED"'), true);
check('booking confirmation no longer shows a downpayment row', !str_contains(file_get_contents('js/booking.js'), '"Downpayment"') && !str_contains(file_get_contents('js/booking.js'), 'dpLabel'), true);
printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail ? 1 : 0);
