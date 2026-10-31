<?php
// OD-151 read-only CMS check: php cms-readonly.php <expected database>
// Boots the app with a READ ONLY database session (every write fails), array session and cache, and renders, as the
// database's first active Super Admin, the editor of the Virtual Office sample page, the main-menu editor and the
// page list filtered to the sample layout. Reports which editable fields and values appear. Nothing is saved.

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = '/home/administrator/Documents/website/smartGrow';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

config(['database.connections.mysql.options' => [...config('database.connections.mysql.options', []), PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY']]);
DB::purge('mysql');
$database = DB::selectOne('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro');
if ($database->db !== $argv[1] || (int) $database->ro !== 1 || config('session.driver') !== 'array' || config('cache.default') !== 'array') {
    fwrite(STDERR, "Refusing: database {$database->db}, read only {$database->ro}\n");
    exit(1);
}

$kernel = $app->make(HttpKernel::class);
$admin = User::query()->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('name', 'super_admin'))->orderBy('id')->firstOrFail();
auth()->guard('web')->setUser($admin);
$get = function (string $path) use ($kernel): array {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), html_entity_decode((string) $response->getContent(), ENT_QUOTES | ENT_HTML5), $response->exception];
};
$present = fn (string $body, array $texts): array => array_combine($texts, array_map(fn (string $text): bool => str_contains($body, $text), $texts));

$page = Page::query()->where('slug', 'business-solutions/virtual-office')->sole();
[$status, $body, $exception] = $get("/admin/website/pages/{$page->getKey()}/edit");
$result['page_editor'] = ['status' => $status, 'error' => $exception?->getMessage(), 'fields' => $present($body, [
    'Page title', 'Page address', 'Layout', 'Sample content', 'Small heading above the title', 'Introduction', 'Button text', 'Goes to', 'A workspace type',
    'Questions', 'Title in search results', 'Description in search results', 'Allow search engines to index this page', 'Start a new draft',
]), 'values' => $present($body, ['Virtual Office', 'business-solutions/virtual-office', 'Virtual Office (sample) · SmartGro', 'How is a virtual office different from a business address?'])];

$menu = NavigationMenu::query()->where('handle', 'main')->sole();
[$status, $body, $exception] = $get("/admin/website/navigation-menus/{$menu->getKey()}/edit");
$result['menu_editor'] = ['status' => $status, 'error' => $exception?->getMessage(), 'fields' => $present($body, [
    'Label', 'Goes to', 'Short description', 'Icon', 'Workspace type', 'Start a new draft', '· sample content',
]), 'values' => $present($body, ['Solutions', 'Workspaces', 'Business Solutions', 'Virtual Office', 'A sample virtual-office concept for businesses that work remotely but want a professional presence.'])];

[$status, $body, $exception] = $get('/admin/website/pages?tableFilters[template][value]=sample');
$result['pages_list_sample_filter'] = ['status' => $status, 'error' => $exception?->getMessage(), 'shows' => $present($body, ['Virtual Office', 'Dedicated Desks', 'Sample content'])];
$result['database'] = $database->db;
$result['acting_as_super_admin'] = $admin->getKey();
$result['still_read_only'] = (int) DB::selectOne('SELECT @@session.transaction_read_only AS ro')->ro;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
