<?php

namespace Database\Seeders;

use App\Actions\Access\SyncUserProperties;
use App\Actions\Access\SyncUserRoles;
use App\Enums\Access\StaffRole;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dummy staff accounts for local databases (OD-165), so one person can try every role and get their
 * own edits approved: content needs an approver who did not write it (BR-CMS-2a, OD-73). One account
 * per role, and two Marketing Managers; the Super Admin one lets a freshly seeded database be managed
 * at all (OD-166). They are created through the same actions
 * as Settings → Users (roles and properties audited); the two roles limited to assigned properties
 * get every property. All share one password, new at each run and printed once: run the seeder again
 * for a new one. Existing accounts keep their roles, properties and active state. Refused in production.
 */
class DummyStaffSeeder extends Seeder
{
    public const string DOMAIN = 'dummy.test';

    /**
     * @var array<string, array{name: string, role: StaffRole}>
     */
    public const array ACCOUNTS = [
        'admin' => ['name' => 'Dummy Super Admin', 'role' => StaffRole::SuperAdmin],
        'marketing' => ['name' => 'Dummy Marketing Manager', 'role' => StaffRole::MarketingManager],
        'marketing2' => ['name' => 'Dummy Marketing Manager (approver)', 'role' => StaffRole::MarketingManager],
        'operations' => ['name' => 'Dummy Operations Manager', 'role' => StaffRole::OperationsManager],
        'property' => ['name' => 'Dummy Property Manager', 'role' => StaffRole::PropertyManager],
        'sales' => ['name' => 'Dummy Sales/CRM Executive', 'role' => StaffRole::SalesCrmExecutive],
        'finance' => ['name' => 'Dummy Finance', 'role' => StaffRole::Finance],
    ];

    public static function email(string $key): string
    {
        return "{$key}@".self::DOMAIN;
    }

    public function run(SyncUserRoles $syncUserRoles, SyncUserProperties $syncUserProperties): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Dummy staff accounts are for local databases only.');
        }

        $password = Str::password(20, symbols: false);
        $propertyIds = Property::query()->orderBy('id')->pluck('id')->all();

        foreach (self::ACCOUNTS as $key => $account) {
            $user = User::query()->where('email', self::email($key))->first();

            if ($user !== null) {
                $user->forceFill(['password' => $password])->save();

                continue;
            }

            $user = User::create(['name' => $account['name'], 'email' => self::email($key), 'password' => $password, 'is_active' => true]);
            $syncUserRoles->handle($user, [$account['role']]);

            if ($account['role']->usesPropertyAssignments()) {
                $syncUserProperties->handle($user, $propertyIds);
            }
        }

        $this->command?->info('Dummy staff accounts: '.implode(', ', array_map(self::email(...), array_keys(self::ACCOUNTS))));
        $this->command?->warn("Their shared password (shown only now): {$password}");
    }
}
