<?php
/* Bella Vista - database migration runner (CLI only).
   Applies the numbered files in database/migrations/ that are not yet recorded in
   the schema_migrations table, in order, and records each one.

     php tools/migrate.php status                 what is applied / pending (changes nothing)
     php tools/migrate.php up --backup-done       apply pending migrations
     php tools/migrate.php baseline --backup-done record 001-007 as applied on a database that
                                                  was created from schema.sql before tracking existed

   Safety: it never drops tables or data by itself, it refuses to change anything unless you
   pass --backup-done (export the database first: docs/BACKUP.md), and every applied file is stored
   with a checksum so later edits to an already-applied file are reported.
   Migration 008 (create a dedicated database user) is an optional manual script and is skipped. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$cfg = require $root . '/php/config.php';
$cmd = $argv[1] ?? 'status';
$backup = in_array('--backup-done', $argv, true);
const MANUAL_ONLY = ['008'];

function say(string $s): void { fwrite(STDOUT, $s . "\n"); }
function die_(string $s, int $c = 1): never { fwrite(STDERR, $s . "\n"); exit($c); }
try {
  $pdo = new PDO("mysql:host={$cfg['DB_HOST']};port={$cfg['DB_PORT']};dbname={$cfg['DB_NAME']};charset=utf8mb4", $cfg['DB_USER'], $cfg['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]);
} catch (Throwable $e) { die_('Cannot connect to the database "' . $cfg['DB_NAME'] . '": ' . $e->getMessage()); }

function table_exists(PDO $p, string $t): bool { $s = $p->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $s->execute([$t]); return (bool)$s->fetch(); }

/* split a .sql file into statements: honours quotes, -- and # and block comments, and DELIMITER lines */
function split_sql(string $sql): array {
  $out = []; $buf = ''; $delim = ';'; $len = strlen($sql); $i = 0; $q = null;
  while ($i < $len) {
    $c = $sql[$i]; $n = $sql[$i + 1] ?? '';
    if ($q === null) {
      if (($buf === '' || preg_match('/\n\s*$/', $buf)) && preg_match('/\G\s*DELIMITER\s+(\S+)[ \t]*\r?\n/Ai', $sql, $m, 0, $i) && trim(substr($buf, strrpos("\n" . $buf, "\n"))) === '') {
        $delim = $m[1]; $i += strlen($m[0]); continue;
      }
      if ($c === '-' && $n === '-' && (($sql[$i + 2] ?? ' ') === ' ' || ($sql[$i + 2] ?? '') === "\n")) { $e = strpos($sql, "\n", $i); $i = $e === false ? $len : $e + 1; continue; }
      if ($c === '#') { $e = strpos($sql, "\n", $i); $i = $e === false ? $len : $e + 1; continue; }
      if ($c === '/' && $n === '*') {
        $e = strpos($sql, '*/', $i + 2); $e = $e === false ? $len : $e + 2; $cm = substr($sql, $i, $e - $i);
        if (preg_match('#^/\*[!M]#', $cm)) { $buf .= $cm; }   // executable comments stay
        $i = $e; continue;
      }
      if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $buf .= $c; $i++; continue; }
      if (substr($sql, $i, strlen($delim)) === $delim) { if (trim($buf) !== '') $out[] = trim($buf); $buf = ''; $i += strlen($delim); continue; }
      $buf .= $c; $i++;
    } else {
      $buf .= $c;
      if ($c === '\\' && $q !== '`') { $buf .= $n; $i += 2; continue; }
      if ($c === $q) { if ($n === $q) { $buf .= $n; $i += 2; continue; } $q = null; }
      $i++;
    }
  }
  if (trim($buf) !== '') $out[] = trim($buf);
  return $out;
}

$files = [];
foreach (glob($root . '/database/migrations/*') as $f) {
  if (!preg_match('/^(\d{3})_.*\.(sql|php)$/', basename($f), $m)) continue;
  $files[$m[1]] = ['version' => $m[1], 'name' => preg_replace('/\.(sql|php)$/', '', basename($f)), 'path' => $f, 'ext' => $m[2], 'sum' => hash_file('sha256', $f)];
}
ksort($files);

$tracked = table_exists($pdo, 'schema_migrations');
$applied = $tracked ? array_column($pdo->query('SELECT version, name, checksum FROM schema_migrations')->fetchAll(), null, 'version') : [];
$hasApp = table_exists($pdo, 'users') || table_exists($pdo, 'bookings');

if ($cmd === 'status') {
  say('Database: ' . $cfg['DB_NAME'] . ($tracked ? '' : '   (no schema_migrations table yet)'));
  if (!$tracked && $hasApp) say("NOTE: this database already has tables but no migration tracking.\n      If it was created from database/schema.sql run:  php tools/migrate.php baseline --backup-done\n      then:  php tools/migrate.php up --backup-done");
  foreach ($files as $v => $f) {
    $state = in_array($v, MANUAL_ONLY, true) ? 'manual (optional, not tracked)' : (isset($applied[$v]) ? 'applied' : 'PENDING');
    if (isset($applied[$v]) && $applied[$v]['checksum'] && $applied[$v]['checksum'] !== $f['sum']) $state .= '  [file changed since it was applied]';
    say(sprintf('  %s  %-52s %s', $v, $f['name'], $state));
  }
  exit(0);
}
if (!in_array($cmd, ['up', 'baseline'], true)) die_("Unknown command '$cmd'. Use: status | up | baseline");
if (!$backup) die_("Refusing to change the database without --backup-done.\nExport a backup first (see docs/BACKUP.md), then repeat the command with --backup-done.");

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(20) NOT NULL, name VARCHAR(150) NOT NULL, checksum CHAR(64) NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, applied_by VARCHAR(60) NOT NULL DEFAULT 'migrate.php', PRIMARY KEY (version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$applied = array_column($pdo->query('SELECT version, name, checksum FROM schema_migrations')->fetchAll(), null, 'version');
$record = $pdo->prepare('INSERT INTO schema_migrations (version, name, checksum, applied_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');

if ($cmd === 'baseline') {
  if (!table_exists($pdo, 'bookings') || !table_exists($pdo, 'users')) die_('This database has no application tables. Import database/schema.sql instead of using baseline.');
  $n = 0;
  foreach ($files as $v => $f) {
    if (in_array($v, MANUAL_ONLY, true) || (int)$v > 7 || isset($applied[$v])) continue;
    $record->execute([$v, $f['name'], $f['sum'], 'baseline']); $n++;
  }
  say("Baseline recorded for $n migration(s) (001-007). Now run:  php tools/migrate.php up --backup-done");
  exit(0);
}

$pending = array_filter($files, fn($f) => !in_array($f['version'], MANUAL_ONLY, true) && !isset($applied[$f['version']]));
if (!$pending) { say('Nothing to apply. The database is up to date.'); exit(0); }
if (!$applied && $hasApp) die_("This database has tables but no migration records. Run  php tools/migrate.php baseline --backup-done  first (only if it was created from schema.sql), or restore from a backup.");
foreach ($pending as $v => $f) {
  say("Applying {$f['name']} ...");
  try {
    if ($f['ext'] === 'php') { $fn = require $f['path']; $fn($pdo, $root); }
    else { foreach (split_sql(file_get_contents($f['path'])) as $stmt) { if (preg_match('/^USE\s/i', $stmt)) continue; $pdo->exec($stmt); } }
    $record->execute([$v, $f['name'], $f['sum'], 'migrate.php']);
    say('  done.');
  } catch (Throwable $e) {
    die_("  FAILED: " . $e->getMessage() . "\n  Migration {$f['name']} was not recorded. DDL statements in MariaDB cannot be rolled back, so check the database\n  (or restore your backup) before running again. The migrations are written to be safe to re-run.");
  }
}
say('All pending migrations applied.');
