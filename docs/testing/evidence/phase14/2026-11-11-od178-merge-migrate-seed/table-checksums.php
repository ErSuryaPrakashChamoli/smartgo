<?php

// OD-171: read-only fingerprint of every table (row count + MySQL CHECKSUM TABLE), and of activity_log's rows up to
// a given id, so new audit rows can be told from changed ones. READ ONLY session; credentials from .env, never printed.
// Usage: php table-checksums.php <database> [activity_log id ceiling]

$env = [];
foreach (file('/home/administrator/Documents/website/smartGrow/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^(DB_[A-Z]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], '"\'');
    }
}

[$database, $ceiling] = [$argv[1], isset($argv[2]) ? (int) $argv[2] : null];
$pdo = new PDO("mysql:host={$env['DB_HOST']};port=".($env['DB_PORT'] ?? 3306).";dbname={$database}", $env['DB_USERNAME'], $env['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY',
]);
$check = $pdo->query('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro')->fetch(PDO::FETCH_ASSOC);
if ($check['db'] !== $database || (int) $check['ro'] !== 1) {
    fwrite(STDERR, "Refusing: not a read-only session on {$database}\n");
    exit(1);
}

$tables = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $tables[$table] = [
        'rows' => (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(),
        'checksum' => (string) $pdo->query("CHECKSUM TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC)['Checksum'],
    ];
}

$activity = $pdo->query('SELECT MAX(id) FROM activity_log')->fetchColumn();
$existingAudit = null;
if ($ceiling !== null) {
    $rows = $pdo->query("SELECT * FROM activity_log WHERE id <= {$ceiling} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $existingAudit = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows))];
}

echo json_encode(['database' => $database, 'read_only' => 1, 'activity_log_max_id' => (int) $activity, 'activity_log_up_to_ceiling' => $existingAudit, 'tables' => $tables], JSON_PRETTY_PRINT), PHP_EOL;
