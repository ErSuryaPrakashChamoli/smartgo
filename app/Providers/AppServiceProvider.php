<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->numbers();

            // The breached-password check calls an external service, so it only runs in production.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
