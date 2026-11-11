<?php

// OD-168: read-only state of a database (row counts, migrations, staff, recent writes), in a READ ONLY session.
// Usage: php state.php <database> [since, local time "Y-m-d H:i:s"]. Credentials come from the project's .env, never printed.

$env = [];
foreach (file('/home/administrator/Documents/website/smartGrow/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^(DB_[A-Z]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], '"\'');
    }
}

[$database, $since] = [$argv[1], $argv[2] ?? null];
$pdo = new PDO("mysql:host={$env['DB_HOST']};port=".($env['DB_PORT'] ?? 3306).";dbname={$database}", $env['DB_USERNAME'], $env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY',
]);
$check = $pdo->query('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro, NOW() AS now')->fetch(PDO::FETCH_ASSOC);
if ($check['db'] !== $database || (int) $check['ro'] !== 1) {
    fwrite(STDERR, "Refusing: not a read-only session on {$database}\n");
    exit(1);
}

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$rows = [];
foreach ($tables as $table) {
    $rows[$table] = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

$result = ['db' => $check['db'], 'ro' => (int) $check['ro'], 'checked_at' => $check['now'], 'tables' => count($tables), 'rows' => $rows];
if (in_array('migrations', $tables, true)) {
    $result['migrations'] = $pdo->query('SELECT COUNT(*) AS count, MAX(batch) AS last_batch FROM migrations')->fetch(PDO::FETCH_ASSOC);
}
if (in_array('users', $tables, true)) {
    $result['users'] = $pdo->query('SELECT id, email, is_active, created_at FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}
if ($since !== null) {
    // Recent writes, by the tables' own timestamps (activity_log.created_at is UTC; the rest local).
    $recent = [];
    foreach ($tables as $table) {
        $columns = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        $stamp = in_array('updated_at', $columns, true) ? 'updated_at' : (in_array('created_at', $columns, true) ? 'created_at' : null);
        if ($stamp !== null && $table !== 'activity_log') {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$stamp}` > ".$pdo->quote($since))->fetchColumn();
            if ($count > 0) {
                $recent[$table] = $count;
            }
        }
    }
    $result['written_after_'.$since] = $recent;
    if (in_array('activity_log', $tables, true)) {
        $utc = (new DateTime($since, new DateTimeZone('Asia/Kolkata')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $result['activity_after'] = $pdo->query('SELECT description, causer_id, COUNT(*) AS n, MIN(created_at) AS first_utc, MAX(created_at) AS last_utc FROM activity_log WHERE created_at > '.$pdo->quote($utc).' GROUP BY description, causer_id ORDER BY first_utc')->fetchAll(PDO::FETCH_ASSOC);
    }
    if (in_array('sessions', $tables, true)) {
        $result['sessions_after'] = $pdo->query('SELECT user_id, COUNT(*) AS n, FROM_UNIXTIME(MAX(last_activity)) AS last FROM sessions WHERE last_activity > UNIX_TIMESTAMP('.$pdo->quote($since).') GROUP BY user_id')->fetchAll(PDO::FETCH_ASSOC);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
