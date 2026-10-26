<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, InteractsWithEmailAuthentication, LogsActivity, Notifiable;

    /**
     * Mirrors the column default so a newly created user is active before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Filament's own pages change two-step login, so those changes are audited from the model.
     * Only which methods are enabled is recorded, never a secret or a recovery code.
     */
    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if (! $user->wasChanged(['app_authentication_secret', 'app_authentication_recovery_codes', 'has_email_authentication'])) {
                return;
            }

            activity('access')
                ->performedOn($user)
                ->causedBy(auth()->user() ?? $user)
                ->event('two_factor_changed')
                ->withProperties([
                    'app_authentication' => filled($user->getAppAuthenticationSecret()),
                    'email_authentication' => $user->hasEmailAuthentication(),
                    'recovery_codes_remaining' => count($user->getAppAuthenticationRecoveryCodes() ?? []),
                ])
                ->log('Two-step login settings changed');
        });
    }

    /**
     * Entering the back office needs the explicit backoffice.access permission (OD-74), assigned to a
     * role the user holds or granted to them as an exception within a staff role's scope (OD-68), so
     * neither a role alone nor a permission without a staff role lets anyone in. Filament re-checks
     * this on every request, including Livewire requests, so deactivation or revocation applies at once.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->hasAnyRole(StaffRole::cases())
            && $this->can(Capability::AccessBackoffice);
    }

    /**
     * Audit changes to identity and access fields; passwords and two-factor secrets are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('access');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
