<?php
/* Runs every PHP test suite and prints one summary.   php tests/run_all.php
   Needs the test database (schema.sql + seed.sql imported). Never run it against a database with real customer data. */
if (PHP_SAPI !== 'cli') exit("CLI only\n");
chdir(dirname(__DIR__));
$suites = ['logic_suite', 'payment_suite', 'booking_suite', 'service_suite', 'security_suite', 'fonts_tool_check'];
$allOk = true; $rows = [];
foreach ($suites as $s) {
  $out = []; exec(escapeshellarg(PHP_BINARY) . ' tests/' . $s . '.php 2>/dev/null', $out, $rc);
  $last = ''; foreach (array_reverse($out) as $l) if (preg_match('/\d+ passed, \d+ failed/', $l)) { $last = trim($l); break; }
  $rows[] = sprintf('%-18s %-28s %s', $s, $last ?: '(no result line)', $rc === 0 ? 'OK' : 'FAILED');
  if ($rc !== 0) $allOk = false;
}
echo implode("\n", $rows), "\n", $allOk ? "\nALL SUITES PASSED\n" : "\nSOMETHING FAILED: run the failing suite on its own to see which check.\n";
echo "Not covered here: tests/http_security_check.py (needs a running web server + Python), browser/responsive checks, phpMyAdmin screens.\n";
exit($allOk ? 0 : 1);
