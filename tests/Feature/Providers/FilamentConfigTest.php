<?php

use Filament\Facades\Filament;
use Illuminate\Support\Arr;

/**
 * `config/filament.php` (OD-160) changes one setting: where Filament keeps its component cache, so the
 * browser kit can point it at a folder that does not exist (`FILAMENT_CACHE_PATH`).
 */
it('keeps the component cache in Filament\'s default folder, and every other Filament setting as the package sets it', function () {
    $package = require base_path('vendor/filament/support/config/filament.php');

    expect(config('filament.cache_path'))->toBe(base_path('bootstrap/cache/filament'))
        ->and(Filament::getPanel('admin')->getComponentCachePath())->toBe(base_path('bootstrap/cache/filament/panels/admin.php'))
        ->and(Arr::except(config('filament'), 'cache_path'))->toEqual(Arr::except($package, 'cache_path'));
});

it('takes the folder from FILAMENT_CACHE_PATH, as the browser kit sets it', function () {
    $_ENV['FILAMENT_CACHE_PATH'] = $_SERVER['FILAMENT_CACHE_PATH'] = '/nonexistent/filament-cache';

    try {
        $config = require config_path('filament.php');
    } finally {
        unset($_ENV['FILAMENT_CACHE_PATH'], $_SERVER['FILAMENT_CACHE_PATH']);
    }

    expect($config)->toBe(['cache_path' => '/nonexistent/filament-cache'])
        ->and((require config_path('filament.php'))['cache_path'])->toBe(base_path('bootstrap/cache/filament'));
});
