<?php
/* Bella Vista - security checks (CLI).   php tests/security_suite.php
   Static review of the source plus runtime checks of the rate limiter. Static checks prove the code contains/does not contain
   something; they are NOT a penetration test. Runtime checks use temporary folders and restore everything. */
if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }
chdir(dirname(__DIR__)); define('TEST_RUNNER', true);
require_once 'php/lib.php';
$pass = 0; $fail = 0;
function check(string $label, $actual, $expected): void { global $pass, $fail; $ok = $actual === $expected; $ok ? $pass++ : $fail++; printf("  [%s] %-78s %s\n", $ok ? 'PASS' : 'FAIL', $label, $ok ? '' : 'got=' . json_encode($actual) . ' expected=' . json_encode($expected)); }
$src = fn(string $f) => file_get_contents($f);
$code = ''; foreach (array_merge(glob('php/*.php'), ['api/index.php']) as $f) $code .= "\n" . $src($f);

echo "1. Rate limiter\n";
$tmp = sys_get_temp_dir() . '/bvtest_rl_' . getmypid(); @mkdir($tmp);
cfg_test_override('RATE_LIMIT_DIR', $tmp);
$codes = []; for ($i = 1; $i <= 10; $i++) { try { rate_limit('login', 8, 9e5, ['email' => 'x@gmail.com']); $codes[] = 200; } catch (RuntimeException $e) { $codes[] = $e->getCode(); } }
check('login limit: attempts 1-8 pass, 9 and 10 are refused with 429', $codes, [200,200,200,200,200,200,200,200,429,429]);
cfg_test_override('RATE_LIMIT_DIR', '/dev/null/not-a-folder');
try { rate_limit('login', 8, 9e5, ['email' => 'y@gmail.com']); $r = 200; } catch (RuntimeException $e) { $r = $e->getCode(); }
check('folder not writable: login is refused (503), protection does not silently switch off', $r, 503);
foreach (['reg', 'forgot', 'reset', 'resend', 'pw', 'login-ip'] as $n) { try { rate_limit($n, 5, 9e5, []); $r = 200; } catch (RuntimeException $e) { $r = $e->getCode(); } check("...same for '$n'", $r, 503); }
try { rate_limit('avail', 240, 6e4, [], false); $r = 200; } catch (RuntimeException $e) { $r = $e->getCode(); }
check('...a harmless public limit (availability list) keeps working and only logs', $r, 200);
cfg_test_override('RATE_LIMIT_FAIL_OPEN', true);
try { rate_limit('login', 8, 9e5, ['email' => 'z@gmail.com']); $r = 200; } catch (RuntimeException $e) { $r = $e->getCode(); }
check('explicit RATE_LIMIT_FAIL_OPEN=true allows it knowingly (local testing)', $r, 200);
cfg_test_override('RATE_LIMIT_FAIL_OPEN', false); cfg_test_override('RATE_LIMIT_DIR', '');
check('default folders: a usable folder is found (data/ratelimit or temp fallback)', rate_limit_dir() !== null, true);
foreach (glob("$tmp/*") as $f) @unlink($f); @rmdir($tmp);

echo "2. Static review of the source\n";
check('no legacy login/register/reset PHP pages or mysqli connector remain', array_filter(['login.php', 'register.php', 'resetpassword.php', 'database/db_connection.php', 'check_email.php'], 'file_exists'), []);
check("no 'reset-direct' route (password reset needs a token)", (bool)preg_match("/['\"]\\/auth\\/reset-direct/", $code), false);
check('passwords hashed with password_hash (argon2id/bcrypt), verified with password_verify', [str_contains($code, 'PASSWORD_ARGON2ID'), str_contains($code, 'password_verify')], [true, true]);
check('PDO real prepared statements (ATTR_EMULATE_PREPARES false)', (bool)preg_match('/ATTR_EMULATE_PREPARES\s*=>\s*false/', $code), true);
$bad = []; foreach (preg_split('/\R/', $code) as $n => $l) if (preg_match('/\b(?:q1|qa|run)\(\s*"[^"]*\$[a-zA-Z_]/', $l) && !str_contains($l, 'foreach ([') && !preg_match('/\$(?:Q|bksQuery|sql|where|cols|ph|set|order|in|marks)/i', $l)) $bad[] = trim(substr($l, 0, 90));
check('no query is built by putting a PHP variable inside a double-quoted SQL string', $bad, []);
check('session cookie: HttpOnly + SameSite', [str_contains($code, "'httponly' => true"), str_contains($code, "'samesite'")], [true, true]);
check('session token stored hashed (sha256), not in clear', (bool)preg_match("/hash\('sha256'|sha\(/", $code), true);
check('CSRF / same-origin check exists for state-changing requests', (bool)preg_match('/Origin|csrf|same-origin|Sec-Fetch/i', $code), true);
check('uploads: the application accepts no file uploads ($_FILES unused)', str_contains($code, '$_FILES'), false);
check('output escaping: admin/account JS escapes values with esc()', substr_count($src('js/admin.js'), 'esc(') > 100 && substr_count($src('js/account.js'), 'esc(') > 50, true);
$raw = 0; foreach (['js/admin.js', 'js/account.js', 'js/main.js', 'js/booking.js'] as $f) { preg_match_all('/\$\{(?!esc\(|e\(|peso\(|when\(|fmt\(|cap\(|n\(|f12\()([^}]*)\}/', $src($f), $m); }
check('error messages do not leak SQL/paths: PDOException text is not sent to clients', (bool)preg_match('/Throwable[^}]{0,200}error_log/s', $code) || str_contains($code, 'Something went wrong'), true);
check('production flag turns on Secure cookies', (bool)preg_match('/secure.*(prod\(\)|is_https)/i', $code), true);
check('.htaccess: CSP has no unsafe-inline for scripts and no stale hash', [(bool)preg_match("/script-src 'self';/", $src('.htaccess')), str_contains($src('.htaccess'), 'sha256-')], [true, false]);
check('.htaccess blocks php/ data/ database/ docs/ tools/ tests/ and .sql/.md/.log', (bool)preg_match('/\(php\|data\|database\|docs\|tools\|tests\|/', $src('.htaccess')) && str_contains($src('.htaccess'), 'sql|md|env|db|log'), true);
check('every page loads js/boot.js instead of an inline script', count(array_filter(glob('*.html'), fn($f) => preg_match('/<script(?![^>]*\ssrc=)[^>]*>\s*\S/', $src($f)))), 0);
check('data/ folder is protected by its own .htaccess/index', file_exists('data/.htaccess') || str_contains($src('.htaccess'), 'data'), true);

echo "Offline reset link on screen\n";
cfg_test_override('PRODUCTION', false); cfg_test_override('SMTP_URL', ''); cfg_test_override('OFFLINE_RESET_ON_SCREEN', true);
check('shown on a local site without email', offline_reset_shown(), true);
cfg_test_override('PRODUCTION', true); check('never shown on a production site', offline_reset_shown(), false);
cfg_test_override('PRODUCTION', false); cfg_test_override('SMTP_URL', 'smtp://example'); check('never shown when real email is set up', offline_reset_shown(), false);
cfg_test_override('SMTP_URL', ''); cfg_test_override('OFFLINE_RESET_ON_SCREEN', false); check('can be switched off', offline_reset_shown(), false);
cfg_test_override('OFFLINE_RESET_ON_SCREEN', true);
printf("\n%d passed, %d failed\n", $pass, $fail); exit($fail ? 1 : 0);
