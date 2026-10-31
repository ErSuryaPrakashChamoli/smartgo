<?php
// OD-151: what `access:sync` would change on smart_go, computed without running it. The code side comes from the
// enums; the database side from a READ ONLY PDO session on smart_go. Credentials from .env, never printed.
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
require '/home/administrator/Documents/website/smartGrow/vendor/autoload.php';
$env = [];
foreach (file('/home/administrator/Documents/website/smartGrow/.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $line, $m)) { $env[$m[1]] = trim($m[2], "\"'"); }
}
$pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname=smart_go", $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->exec('START TRANSACTION READ ONLY');
$stored = $pdo->query('SELECT name FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
$code = array_map(fn (Capability $c): string => $c->value, Capability::cases());
$out = ['permissions_to_create' => array_values(array_diff($code, $stored)), 'permissions_only_in_database' => array_values(array_diff($stored, $code)),
    'roles_to_create' => array_values(array_diff(array_map(fn (StaffRole $r): string => $r->value, StaffRole::cases()), $pdo->query('SELECT name FROM roles')->fetchAll(PDO::FETCH_COLUMN)))];
foreach (StaffRole::cases() as $role) {
    $statement = $pdo->prepare('SELECT p.name FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.name = ?');
    $statement->execute([$role->value]);
    $has = $statement->fetchAll(PDO::FETCH_COLUMN);
    $should = array_map(fn (Capability $c): string => $c->value, $role->capabilities());
    $out['roles'][$role->value] = ['now' => count($has), 'after' => count($should), 'add' => array_values(array_diff($should, $has)), 'remove' => array_values(array_diff($has, $should))];
}
$out['users_holding_each_role'] = $pdo->query("SELECT r.name, COUNT(*) users, SUM(u.is_active) active FROM model_has_roles m JOIN roles r ON r.id = m.role_id JOIN users u ON u.id = m.model_id GROUP BY r.name")->fetchAll(PDO::FETCH_ASSOC);
$out['read_only'] = (int) $pdo->query('SELECT @@session.transaction_read_only')->fetchColumn();
$pdo->exec('ROLLBACK');
echo json_encode($out, JSON_PRETTY_PRINT), PHP_EOL;
