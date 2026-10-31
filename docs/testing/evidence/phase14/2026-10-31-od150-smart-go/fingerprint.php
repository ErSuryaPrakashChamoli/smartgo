<?php
// Read-only fingerprint of a database: php fingerprint.php <database> [<earlier fingerprint.json>]
// For each table: exact row count, a hash over every row of the given columns (the table's current columns,
// or the columns recorded in the earlier fingerprint, so data that existed before can be compared after
// columns are added), and its column definitions. Credentials from .env, never printed.
$env = [];
foreach (file('/home/administrator/Documents/website/smartGrow/.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) { $env[$m[1]] = trim($m[2], "\"'"); }
}
[$database, $earlierFile] = [$argv[1], $argv[2] ?? null];
$earlier = $earlierFile ? json_decode(file_get_contents($earlierFile), true) : null;
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database}", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->exec('SET SESSION group_concat_max_len = 1073741824');
$pdo->exec('START TRANSACTION READ ONLY');
$q = fn (string $sql): array => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$out = ['database' => $q('SELECT DATABASE() d')[0]['d'], 'read_only' => (int) $q('SELECT @@session.transaction_read_only r')[0]['r'], 'at' => $q('SELECT NOW() n')[0]['n'], 'tables' => []];
foreach ($q("SELECT table_name t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY 1") as ['t' => $table]) {
    $columns = $q("SELECT column_name name, column_type type, is_nullable nullable, column_default `default` FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '{$table}' ORDER BY ordinal_position");
    $hashed = $earlier['tables'][$table]['hashed_columns'] ?? array_column($columns, 'name');
    $row = 'CONCAT_WS(\'|\', '.implode(', ', array_map(fn (string $c): string => "COALESCE(CAST(`{$c}` AS CHAR), '\\0')", $hashed)).')';
    $out['tables'][$table] = [
        'rows' => (int) $q("SELECT COUNT(*) n FROM `{$table}`")[0]['n'],
        'hash' => $q("SELECT MD5(COALESCE(GROUP_CONCAT(h ORDER BY h SEPARATOR ''), '')) h FROM (SELECT MD5({$row}) h FROM `{$table}`) x")[0]['h'],
        'hashed_columns' => $hashed,
        'columns' => $columns,
        'indexes' => array_column($q("SELECT DISTINCT index_name i FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '{$table}' ORDER BY 1"), 'i'),
        'foreign_keys' => array_column($q("SELECT constraint_name c FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = '{$table}' ORDER BY 1"), 'c'),
    ];
}
$pdo->exec('ROLLBACK');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
