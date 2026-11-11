<?php
// OD-176 read-only check of the profile editors (photo picker) as the Super Admin: php editor-smoke.php <database>
use App\Models\OfferingProfile;
use App\Models\PropertyProfile;
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
$db = DB::selectOne('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro');
if ($db->db !== $argv[1] || (int) $db->ro !== 1 || config('session.driver') !== 'array') { fwrite(STDERR, "Refusing\n"); exit(1); }
$kernel = $app->make(HttpKernel::class);
auth()->guard('web')->setUser(User::query()->where('email', 'admin@dummy.test')->sole());
$out = ['sample_content' => config('backoffice.sample_content')];
$paths = PropertyProfile::query()->pluck('id')->map(fn ($id) => "/admin/properties/website-profiles/{$id}/edit")->all();
$paths = [...$paths, ...OfferingProfile::query()->limit(3)->pluck('offering_id')->map(fn ($id) => "/admin/properties/workspace-offerings/{$id}/edit")->all()];
foreach ($paths as $path) {
    $request = Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $body = (string) $response->getContent();
    $out[$path] = ['status' => $response->getStatusCode(), 'stock_option' => substr_count($body, 'stock photo, sample content only'), 'error' => ($response->exception ?? null) ? mb_substr($response->exception->getMessage(), 0, 200) : null];
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
