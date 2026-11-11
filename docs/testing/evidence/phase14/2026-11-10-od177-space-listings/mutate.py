"""OD-177 mutation checks, run only in the isolated copy (scratchpad/mutant) against smartgro_od177_testing.

Each mutation replaces one decision, runs the test that should catch it, then restores the file byte for byte.
"""
import os
import shutil
import subprocess

S = '/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/ca7ac759-3e82-49da-a28c-4d4b424b87f3/scratchpad'
M = S + '/mutant'
ENV = {**os.environ, 'APP_CONFIG_CACHE': S + '/none-config.php', 'APP_ROUTES_CACHE': S + '/none-routes.php', 'APP_EVENTS_CACHE': S + '/none-events.php'}

MUTATIONS = [
    ('M1 a closed location still shows its spaces', 'app/Models/SpaceListing.php',
     "        $query->published()->whereHas('property', fn (Builder $property) => $property->where(fn (Builder $state) => $state",
     "        $query->published(); return; $query->whereHas('property', fn (Builder $property) => $property->where(fn (Builder $state) => $state",
     'tests/Feature/Http/Controllers/Web/SpaceListingTest.php', 'has no page for a space'),
    ('M2 switching on ignores a version already in progress', 'app/Filament/Clusters/Website/Resources/SpaceListings/Tables/SpaceListingsTable.php',
     '        if ($inProgress !== null) {',
     '        if (false) {',
     'tests/Feature/Filament/Clusters/Website/Resources/SpaceListingScreensTest.php', 'switches a space back on'),
    ('M3 the seeder switches a space back on', 'database/seeders/SpaceListingSeeder.php',
     "|| $content->getAttribute('status') === 'unpublished'",
     '',
     'tests/Feature/Database/SpaceListingSeederTest.php', 'leaves a space someone switched off'),
    ('M4 a space type is accepted at the top of the menu', 'app/Services/Content/NavigationPayload.php',
     "            'allowed' => [...self::LINKS, self::GROUP],",
     "            'allowed' => [...self::LINKS, self::GROUP, self::SPACE_TYPE],",
     'tests/Feature/Http/Controllers/Web/SpaceListingTest.php', 'accepts a space type only'),
    ('M5 a space type with no shown space still gets a tab', 'app/Services/Content/SiteNavigation.php',
     "                if ($spaces !== [] && filled($item['label'] ?? null)) {",
     "                if (filled($item['label'] ?? null)) {",
     'tests/Feature/Http/Controllers/Web/SpaceListingTest.php', 'opens Spaces as a tab per space type'),
    ('M6 the enquiry form guesses a workspace type for a shared space type', 'app/Services/Website/SpaceListingPages.php',
     '        $product = $products->count() === 1 ? $products->first() : null;',
     '        $product = $products->first();',
     'tests/Feature/Http/Controllers/Web/SpaceListingTest.php', 'without choosing a workspace type'),
]

for name, path, old, new, test, test_filter in MUTATIONS:
    target = os.path.join(M, path)
    backup = target + '.orig'
    shutil.copy2(target, backup)
    source = open(target).read()
    assert source.count(old) == 1, f'{name}: anchor not found once'
    open(target, 'w').write(source.replace(old, new))
    run = subprocess.run(['php', 'artisan', 'test', '--compact', test, '--filter', test_filter], cwd=M, env=ENV, capture_output=True, text=True, timeout=900)
    shutil.copy2(backup, target)
    os.remove(backup)
    restored = subprocess.run(['cmp', target, os.path.join('/home/administrator/Documents/website/smartGrow', path)], capture_output=True).returncode == 0
    caught = run.returncode != 0
    print(f"{name}: {'CAUGHT' if caught else 'MISSED'} (exit {run.returncode}); restored identical to the real file: {restored}")
    if not caught:
        print(run.stdout[-1500:])
