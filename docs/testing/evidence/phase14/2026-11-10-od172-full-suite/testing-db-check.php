<?php
// Read-only: when the test database's migrations table was last created (each part's migrate:fresh), by connecting to smartgro_testing only.
$env = [];
foreach (file('/home/administrator/Documents/website/smartGrow/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^(DB_[A-Z]+)=(.*)$/', $line, $m)) { $env[$m[1]] = trim($m[2], '"\''); }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port=".($env['DB_PORT'] ?? 3306).';dbname=smartgro_testing', $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY']);
$row = $pdo->query("SELECT DATABASE() AS db, (SELECT create_time FROM information_schema.tables WHERE table_schema = 'smartgro_testing' AND table_name = 'migrations') AS migrations_created, (SELECT COUNT(*) FROM migrations) AS migrations, NOW() AS now")->fetch(PDO::FETCH_ASSOC);
echo json_encode($row), PHP_EOL;
