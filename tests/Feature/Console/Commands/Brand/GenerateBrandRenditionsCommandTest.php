<?php

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('writes the renditions to the requested directory and lists them', function () {
    $output = storage_path('framework/testing/brand-command');
    File::deleteDirectory($output);

    artisan('brand:renditions', ['--output' => $output])
        ->expectsOutputToContain('favicon.ico')
        ->assertSuccessful();

    expect(File::exists("{$output}/favicon.ico"))->toBeTrue()
        ->and(File::exists("{$output}/brand/smartgro-logo-reversed.webp"))->toBeTrue();

    File::deleteDirectory($output);
});
