<?php

use App\Enums\Access\StaffRole;
use App\Models\CaseStudy;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\Offering;
use App\Models\Page;
use App\Models\Property;
use App\Models\SpaceListing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('fills an empty database with the roles, reference data, dummy staff and sample content, and can run again (OD-166)', function () {
    Storage::fake('local');
    seed(DatabaseSeeder::class);
    seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', 'admin@dummy.test')->sole();
    expect(Role::query()->get()->every(fn (Role $role): bool => $role->permissions()->count() === count(StaffRole::from($role->name)->capabilities())))->toBeTrue()
        ->and(Property::query()->count())->toBe(4)
        ->and(User::query()->where('email', 'like', '%@dummy.test')->count())->toBe(7)
        ->and($admin->hasRole(StaffRole::SuperAdmin))->toBeTrue()
        ->and($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and(Page::query()->where('template', 'sample')->where('status', 'published')->count())->toBe(29 + 1)
        ->and(HomeBanner::query()->published()->count())->toBe(4)
        ->and(HomeHighlight::query()->published()->where('is_sample', true)->count())->toBe(12)
        ->and(SpaceListing::query()->published()->where('is_sample', true)->count())->toBe(4 * 6 * 3)
        ->and(Offering::query()->publiclyListed()->count())->toBe(4 * 7)
        ->and(CaseStudy::query()->published()->count())->toBe(2)
        ->and(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

it('seeds only the roles and reference data in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(Role::query()->count())->toBe(count(StaffRole::cases()))
        ->and(Property::query()->count())->toBe(4)
        ->and(User::query()->exists())->toBeFalse()
        ->and(Page::query()->exists())->toBeFalse()
        ->and(HomeBanner::query()->exists())->toBeFalse()
        ->and(SpaceListing::query()->exists())->toBeFalse()
        ->and(Offering::query()->exists())->toBeFalse()
        ->and(CaseStudy::query()->exists())->toBeFalse();
});
