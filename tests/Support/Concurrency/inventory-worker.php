<?php

/*
 * Test-only worker for Tests\Support\Concurrency\InventoryHarness.
 *
 * Boots the application, waits for the shared release instant, then records an allocation, wins a deal
 * (allocating its proposed spaces), changes a space's status, takes a seat out of use or activates a
 * price plan. After every read of spaces, seats, allocations, deals, proposed spaces, offerings or price
 * plans it pauses briefly, which widens the race window so a missing lock would show up as a double
 * allocation or as a space taken out of use while allocated. Prints one JSON line.
 */

use App\Actions\Crm\WinDeal;
use App\Actions\Inventory\ChangeSeats;
use App\Actions\Inventory\ChangeSpaceStatus;
use App\Actions\Inventory\RecordAllocation;
use App\Actions\Pricing\ChangePricePlanState;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Deal;
use App\Models\PricePlan;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, 'Refusing to run the harness outside a *_testing database.'.PHP_EOL);
    exit(1);
}

DB::connection()->getPdo();

DB::listen(function (QueryExecuted $query): void {
    if (preg_match('/^\s*select\b.*\bfrom `(spaces|space_seats|space_allocations|offerings|price_plans|deals|deal_space)`/is', $query->sql)) {
        usleep(150_000);
    }
});

$space = Space::query()->find((int) getenv('INVENTORY_SPACE_ID'));
$user = User::query()->findOrFail((int) getenv('INVENTORY_USER_ID'));
$data = json_decode((string) getenv('INVENTORY_DATA'), true, flags: JSON_THROW_ON_ERROR);

$waitMicroseconds = (int) round(((float) getenv('INVENTORY_RELEASE_AT') - microtime(true)) * 1_000_000);

if ($waitMicroseconds > 0) {
    usleep($waitMicroseconds);
}

try {
    $outcome = match (getenv('INVENTORY_MODE')) {
        'allocate' => app(RecordAllocation::class)->handle($user, $space, $data)->agreement_reference,
        'status' => app(ChangeSpaceStatus::class)->handle($user, $space, SpaceStatus::from($data['status']), SpaceStatus::Active, 'Race test')->operational_status->value,
        'win_deal' => app(WinDeal::class)->handle($user, Deal::query()->findOrFail((int) $data['deal_id']), $data['win'])->agreement_reference ?? 'won',
        'activate_plan' => app(ChangePricePlanState::class)->handle($user, PricePlan::query()->findOrFail((int) $data['plan_id']), 'activate')->status->value,
        'deactivate_seat' => app(ChangeSeats::class)->handle($user, $space, 'deactivate', [...$data, 'reason' => 'Race test'])->seats->where('is_active', false)->count().' out of use',
    };
    $message = null;
} catch (AuthorizationException|ValidationException $exception) {
    $outcome = 'refused';
    $message = $exception->getMessage();
}

echo json_encode(['outcome' => $outcome, 'message' => $message]).PHP_EOL;
