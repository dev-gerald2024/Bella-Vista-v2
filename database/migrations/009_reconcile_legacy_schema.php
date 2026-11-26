<?php
/* Migration 009 - bring a database that was built from migrations 001-007 up to the structure
   the application and database/schema.sql actually use (Phase 6).

   Why: the numbered SQL files 001/004/006 describe an older shape (for example content.key_name,
   sessions.created_at as DATETIME, therapist_leaves.leave_date). A database created from schema.sql
   already has the current shape and is left untouched by this file.

   Safety rules:
   - additive first: missing bookings columns are added, narrow columns are widened, nothing is shrunk
   - a table whose shape is wrong is NOT dropped: it is renamed to <table>_pre009 (kept as a backup),
     the correct table is created from database/schema.sql and the old rows are copied across
   - sessions and tokens are short-lived (login sessions, password-reset links): they are not copied,
     so people sign in again and request a new reset link
   - safe to run more than once: a table that is already correct is skipped
   Run through  php tools/migrate.php up --backup-done  (export a backup first). */
return function (PDO $pdo, string $root): void {
  $col = function (string $t, string $c) use ($pdo): ?array {
    $s = $pdo->prepare('SELECT data_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $s->execute([$t, $c]); return $s->fetch() ?: null;
  };
  $exists = function (string $t) use ($pdo): bool {
    $s = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?'); $s->execute([$t]); return (bool)$s->fetch();
  };
  $schema = file_get_contents($root . '/database/schema.sql');
  $create = function (string $t) use ($schema): string {
    if (!preg_match('/CREATE TABLE IF NOT EXISTS `' . $t . '` \(.*?\n\) ENGINE=[^;]*;/s', $schema, $m)) throw new RuntimeException("schema.sql has no definition for $t");
    return $m[0];
  };

  /* 1. bookings: columns the application reads and writes */
  if ($exists('bookings')) {
    $pdo->exec("ALTER TABLE bookings
      ADD COLUMN IF NOT EXISTS email VARCHAR(254) NOT NULL DEFAULT '' AFTER name,
      ADD COLUMN IF NOT EXISTS message TEXT NULL,
      ADD COLUMN IF NOT EXISTS therapist_id INT UNSIGNED NULL,
      ADD COLUMN IF NOT EXISTS package_id INT UNSIGNED NULL,
      ADD KEY IF NOT EXISTS idx_bookings_therapist (therapist_id),
      ADD KEY IF NOT EXISTS idx_bookings_package (package_id)");
    $pdo->exec("ALTER TABLE bookings MODIFY service VARCHAR(150) NOT NULL, MODIFY duration VARCHAR(30) NOT NULL DEFAULT ''");
    if ($col('bookings', 'notes')) $pdo->exec("UPDATE bookings SET message = notes WHERE message IS NULL AND notes <> ''");
    if ($col('favorites', 'item_key')) $pdo->exec('ALTER TABLE favorites MODIFY item_key VARCHAR(190) NOT NULL');
  }

  /* 2. tables with the wrong shape: rebuild from schema.sql, keep the old table as <name>_pre009 */
  $copy = [   // table => [needs-rebuild test, target column => source expression, or null = do not copy rows]
    'content'          => [fn() => !$col('content', 'content_key'),            ['content_key' => 'key_name', 'json' => 'val', 'updated_at' => 'updated_at']],
    'favorites'        => [fn() => (bool)$col('favorites', 'id'),              ['user_id' => 'user_id', 'item_key' => 'item_key']],
    'sessions'         => [fn() => ($col('sessions', 'created_at')['data_type'] ?? 'bigint') !== 'bigint', null],
    'tokens'           => [fn() => ($col('tokens', 'expires_at')['data_type'] ?? 'bigint') !== 'bigint',   null],
    'therapist_leaves' => [fn() => !$col('therapist_leaves', 'start_date'),    ['id' => 'id', 'therapist_id' => 'therapist_id', 'start_date' => 'leave_date', 'end_date' => 'leave_date', 'leave_type' => 'leave_type', 'notes' => 'notes', 'created_at' => 'created_at']],
    'therapist_breaks' => [fn() => !$col('therapist_breaks', 'break_type'),    ['id' => 'id', 'therapist_id' => 'therapist_id', 'break_date' => 'break_date', 'start_time' => 'start_time', 'end_time' => 'end_time', 'break_type' => 'reason', 'created_at' => 'created_at']],
    'room_maintenance' => [fn() => !$col('room_maintenance', 'start_time'),    ['id' => 'id', 'room_id' => 'room_id', 'start_date' => 'start_date', 'end_date' => 'end_date', 'reason' => 'reason', 'status' => 'status', 'created_by' => 'scheduled_by', 'created_at' => 'created_at']],
    'room_cleaning'    => [fn() => !$col('room_cleaning', 'status'),           ['id' => 'id', 'room_id' => 'room_id', 'booking_id' => 'booking_id', 'cleaning_date' => 'cleaning_date', 'start_time' => 'start_time', 'end_time' => 'end_time', 'status' => 'cleaning_status', 'cleaned_by' => 'cleaned_by', 'notes' => 'notes', 'created_at' => 'created_at']],
  ];
  foreach ($copy as $t => [$needs, $map]) {
    if (!$exists($t) || !$needs()) continue;
    $old = $t . '_pre009'; $i = 2; while ($exists($old)) $old = $t . '_pre009_' . $i++;
    $fks = $pdo->prepare("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name=? AND constraint_type='FOREIGN KEY'");
    $fks->execute([$t]);
    foreach ($fks->fetchAll(PDO::FETCH_COLUMN) as $fk) $pdo->exec("ALTER TABLE `$t` DROP FOREIGN KEY `$fk`");   // constraint names are unique per database
    $pdo->exec("RENAME TABLE `$t` TO `$old`");
    $pdo->exec($create($t));
    if ($map) {
      $dst = implode(',', array_map(fn($c) => "`$c`", array_keys($map)));
      $src = implode(',', array_map(fn($c) => "`$c`", array_values($map)));
      $pdo->exec("INSERT IGNORE INTO `$t` ($dst) SELECT $src FROM `$old`");
    }
    fwrite(STDOUT, "  rebuilt $t (old rows kept in $old)\n");
  }
};
