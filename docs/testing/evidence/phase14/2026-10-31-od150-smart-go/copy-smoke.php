<?php
// OD-150 rehearsal smoke test on the RESTORED COPY only (smartgro_restore_check): publishes a main-menu version
// with descriptions and icons through the real workflow, then reads them back from the new columns and the
// rendered header. Refuses any other database.

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\User;
use App\Services\Content\NavigationPayload;
use App\Services\Content\SiteNavigation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

$root = '/home/administrator/Documents/website/smartGrow';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'smartgro_restore_check') {
    fwrite(STDERR, "Refusing: only the restored copy\n");
    exit(1);
}

$owner = User::query()->where('name', 'Owner')->sole();
// A second person is needed to approve (BR-CMS-2a); on the copy only, the deactivated helper approver is reactivated.
$approver = User::query()->where('name', 'Marketing approver (local helper)')->sole();
$approver->forceFill(['is_active' => true])->save();

$menu = NavigationMenu::query()->where('handle', 'main')->sole();
$result = ['database' => DB::selectOne('SELECT DATABASE() AS db')->db, 'offers_details' => $menu->offersDetails(), 'has_details' => NavigationItem::hasDetails()];
$payload = $menu->toRevisionPayload();
$result['payload_before'] = $payload;
foreach ($payload['items'] as &$item) {
    foreach ($item['children'] ?? [] as $index => $child) {
        $item['children'][$index] = [...$child, 'description' => 'Rehearsal description '.$index.'.', 'icon' => 'map-pin'];
    }
}
unset($item);
$payload['items'] = array_values(array_filter($payload['items'], fn (array $item): bool => ! str_starts_with((string) $item['label'], 'Rehearsal')));
$payload['items'][] = ['label' => 'Rehearsal group', 'type' => 'group', 'children' => [
    ['label' => 'Rehearsal category', 'type' => 'group', 'description' => 'Rehearsal category description.', 'icon' => 'briefcase', 'children' => [
        ['label' => 'All workspace types', 'type' => 'section', 'section' => 'workspace_types', 'description' => 'Rehearsal description shown.', 'icon' => 'squares-2x2'],
    ]],
]];

$validated = app(NavigationPayload::class)->validate($menu, $payload, forSubmission: true);
$draft = app(CreateDraft::class)->handle($menu, $owner, $payload);
app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $owner), $approver), $owner);

$result['stored'] = NavigationItem::query()->where('navigation_menu_id', $menu->id)->orderBy('parent_id')->orderBy('sort_order')
    ->get(['id', 'parent_id', 'label', 'description', 'icon'])->toArray();
$result['next_draft_payload_keeps_details'] = $menu->fresh()->toRevisionPayload()['items'][1]['children'][0] ?? null;
$navigation = app(SiteNavigation::class)->present();
$result['presented_children'] = collect(collect($navigation['main'])->firstWhere('label', 'Rehearsal group')['children'] ?? [])->map(fn (array $child): array => ['label' => $child['label'], 'description' => $child['description'] ?? null, 'icon' => $child['icon'] ?? null, 'links' => array_map(fn (array $link): array => [$link['label'], $link['description'], $link['icon']], $child['children'])])->all();
$result['rendered_has_description'] = str_contains(Blade::render('<x-site.navigation.bar :items="$items" :actions="$actions" />', ['items' => $navigation['main'], 'actions' => $navigation['actions']]), 'Rehearsal description shown.');
$result['main_labels'] = array_column($navigation['main'], 'label');
$result['validated_child'] = $validated['items'][1]['children'][0];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
