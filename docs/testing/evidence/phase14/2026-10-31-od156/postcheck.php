<?php

// Spaces activation pre- and post-check, READ ONLY: php postcheck.php <database> [<since, e.g. "2026-11-01 10:00:00" UTC>]
// Reports the activation migration, space types, category–type links (A-28 highlighted), permissions against
// the code's 48, role permission counts, audit entries since the given time, and the permission cache entry.
// Credentials are read from .env and never printed. Run from the project root.
$env = [];
foreach (file('.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], "\"'");
    }
}
[$database, $since] = [$argv[1] ?? '', $argv[2] ?? '1970-01-01 00:00:00'];
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$database}", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->exec('START TRANSACTION READ ONLY');
$all = fn (string $sql, array $args = []): array => (function () use ($pdo, $sql, $args): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($args);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
})();
$hasPivot = (bool) $all("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_space_type'")[0]['n'];
$codePermissions = ['allocations.manage', 'allocations.view', 'inventory.import', 'inventory.import.approve', 'inventory.manage', 'leads.reports.view', 'offers.manage', 'pricing.approve', 'pricing.manage', 'pricing.view', 'tours.manage', 'tours.view', 'twin_models.manage', 'twin_models.view'];
$permissions = array_column($all('SELECT name FROM permissions'), 'name');
$links = $hasPivot ? $all('SELECT t.`key` type, p.`key` category FROM product_space_type x JOIN space_types t ON t.id = x.space_type_id JOIN products p ON p.id = x.product_id ORDER BY t.sort_order, p.sort_order') : [];
$isLink = fn (string $type, string $category): bool => in_array(['type' => $type, 'category' => $category], $links, true);
echo json_encode([
    'database' => $all('SELECT DATABASE() d, @@session.transaction_read_only ro')[0],
    'migrations' => $all('SELECT COUNT(*) total, MAX(batch) latest_batch FROM migrations')[0] + ['activation_migration_ran' => (bool) $all("SELECT COUNT(*) n FROM migrations WHERE migration = '2026_10_31_064952_create_product_space_type_table'")[0]['n']],
    'product_space_type_table' => $hasPivot,
    'space_types' => array_column($all('SELECT `key` FROM space_types ORDER BY sort_order'), 'key'),
    'links' => array_map(fn (array $link): string => "{$link['type']} -> {$link['category']}", $links),
    'a28' => [
        '1 flexible seating zone -> dedicated desks' => $isLink('desk_zone_flexible', 'dedicated_desk') ? 'ENABLED' : 'not enabled',
        '2 conference or training room -> meeting rooms' => $isLink('conference_training_room', 'meeting_room') ? 'ENABLED' : 'not enabled',
    ],
    'spaces' => (int) ($all("SELECT COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'spaces'")[0]['n'] ? $all('SELECT COUNT(*) n FROM spaces')[0]['n'] : 0),
    'permissions' => ['total' => count($permissions), 'of_the_14_added_by_access_sync_present' => count(array_intersect($codePermissions, $permissions))],
    'role_permission_counts' => array_column($all('SELECT r.name, COUNT(rp.permission_id) n FROM roles r LEFT JOIN role_has_permissions rp ON rp.role_id = r.id GROUP BY r.name ORDER BY r.name'), 'n', 'name'),
    'audit_since' => [$since => array_column($all("SELECT CONCAT(log_name, ' / ', event) e, COUNT(*) n FROM activity_log WHERE created_at >= ? AND event IN ('created', 'deleted', 'role_capabilities_changed', 'space_types_changed') AND log_name IN ('inventory', 'access', 'properties') GROUP BY log_name, event", [$since]), 'n', 'e')],
    'permission_cache_entry' => (bool) $all("SELECT COUNT(*) n FROM cache WHERE `key` LIKE '%spatie.permission.cache%'")[0]['n'],
], JSON_PRETTY_PRINT), PHP_EOL;
$pdo->exec('ROLLBACK');
