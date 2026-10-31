<?php
// OD-153 rehearsal, TEST DATABASE ONLY: one temporary active user per staff role (and one deactivated Super
// Admin), then, in a READ ONLY session, the back-office screens each may open and what policies allow.
use App\Enums\Access\StaffRole;
use App\Models\Product;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceType;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require '/home/administrator/Documents/website/smartGrow/vendor/autoload.php';
$app = require '/home/administrator/Documents/website/smartGrow/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'smartgro_testing') {
    fwrite(STDERR, "Refusing: test database only\n");
    exit(1);
}

$users = [];
foreach (StaffRole::cases() as $role) {
    $users[$role->value] = User::query()->where('email', 'rehearsal-'.$role->value.'@example.test')->first() ?? User::factory()->withRole($role)->create(['name' => 'Rehearsal '.$role->value, 'email' => 'rehearsal-'.$role->value.'@example.test']);
}
$users['super_admin (deactivated)'] = User::query()->where('email', 'rehearsal-deactivated@example.test')->first() ?? User::factory()->withRole(StaffRole::SuperAdmin)->create(['name' => 'Rehearsal deactivated', 'email' => 'rehearsal-deactivated@example.test', 'is_active' => false]);

config(['database.connections.mysql.options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'], 'session.driver' => 'array', 'cache.default' => 'array']);
DB::purge('mysql');
$kernel = $app->make(HttpKernel::class);
$paths = [
    'spaces' => '/admin/properties/spaces', 'space types' => '/admin/properties/space-types', 'new space type' => '/admin/properties/space-types/create',
    'workspace categories' => '/admin/properties/workspace-categories', 'edit a category' => '/admin/properties/workspace-categories/'.Product::query()->value('id').'/edit',
    'properties' => '/admin/properties/properties', '3D models' => '/admin/website/twin-models', 'price plans' => '/admin/properties/price-plans',
    'offers' => '/admin/properties/offers', 'inventory imports' => '/admin/properties/inventory-imports', 'tours' => '/admin/sales/tours',
    'enquiries report' => '/admin/reports/enquiries-and-tours', 'pages (CMS)' => '/admin/website/pages',
];
$property = Property::query()->where('code', 'A-70')->sole();
$result = ['database' => DB::selectOne('SELECT DATABASE() AS db, @@session.transaction_read_only AS ro')];
foreach ($users as $label => $user) {
    auth()->guard('web')->setUser($user);
    $row = ['panel' => $user->canAccessPanel(filament()->getPanel('admin'))];
    foreach ($paths as $name => $path) {
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $row['screens'][$name] = $response->getStatusCode().($response->exception && $response->getStatusCode() >= 500 ? ' '.class_basename($response->exception).': '.$response->exception->getMessage() : '');
    }
    $row['may'] = [
        'create a space type' => $user->can('create', SpaceType::class),
        'change a category' => $user->can('update', Product::query()->first()),
        'add spaces at A-70' => $user->hasCapabilityForProperty(\App\Enums\Access\Capability::ManageInventory, $property),
        'see spaces list' => $user->can('viewAny', Space::class),
    ];
    $result['roles'][$label] = $row;
    auth()->guard('web')->forgetUser();
}
$result['still_read_only'] = (int) DB::selectOne('SELECT @@session.transaction_read_only AS ro')->ro;
echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
