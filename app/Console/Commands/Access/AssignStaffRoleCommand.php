<?php

namespace App\Console\Commands\Access;

use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:assign-role {email : Email address of an existing user} {role : One of the staff role keys, e.g. super_admin}')]
#[Description('Give an existing user a staff role (used to set up the first Super Admin)')]
class AssignStaffRoleCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $role = StaffRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('Unknown role. Valid roles: '.implode(', ', StaffRole::values()).'.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error('No user exists with that email address.');

            return self::FAILURE;
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);

            activity('access')
                ->performedOn($user)
                ->event('roles_changed')
                ->withProperties(['added' => $role->value, 'via' => 'console'])
                ->log('Staff role assigned from the console');
        }

        $this->components->info("{$user->email} now has the {$role->label()} role.");

        return self::SUCCESS;
    }
}
