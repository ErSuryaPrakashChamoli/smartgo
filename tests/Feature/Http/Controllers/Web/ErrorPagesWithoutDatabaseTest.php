<?php

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

/*
 * No RefreshDatabase here: these tests point the connection at a port where nothing listens, to show
 * that the server error and maintenance pages render while the database is unreachable.
 */
beforeEach(function () {
    config(['database.connections.mysql.port' => 1, 'app.debug' => false, 'logging.default' => 'null']);
    DB::purge('mysql');
});

it('shows the server error page while the database is unreachable', function () {
    Route::get('/test-only/failure', fn () => throw new RuntimeException('Internal detail'));

    expect(fn () => DB::select('select 1'))->toThrow(Exception::class);

    get('/test-only/failure')
        ->assertStatus(500)
        ->assertSee('Something went wrong</h1>', false)
        ->assertDontSee('Internal detail');
});

it('shows the maintenance page while the database is unreachable', function () {
    app()->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        public function data(): array
        {
            return [];
        }
    });

    get('/')->assertStatus(503)->assertSee('Back soon</h1>', false);
});
