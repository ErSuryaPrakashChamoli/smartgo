<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Services\Content\SiteNavigation;
use App\Services\Twin\TwinModelLimits;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewInstance;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Exception traces never record call arguments, whatever the server's php.ini says, so details a
        // visitor typed (an enquiry's name, email or phone) cannot reach the logs through a stack trace.
        ini_set('zend.exception_ignore_args', '1');

        $this->app->bind(TwinModelLimits::class, fn (): TwinModelLimits => TwinModelLimits::fromConfig(config('twin.limits')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        // Error pages a visitor can reach show the published menus, or none if they cannot be read; the
        // server error and maintenance pages never read them (resources/views/errors).
        View::composer(['errors::403', 'errors::404', 'errors::410', 'errors::419', 'errors::429'], function (ViewInstance $view): void {
            $view->with('navigation', rescue(fn (): array => app(SiteNavigation::class)->present(), fn (): array => SiteNavigation::withoutMenus(), report: false));
        });

        // Enquiry form: a few submissions per minute and per hour from one address (SECURITY_MODEL,
        // "Public forms"); SubmitEnquiry also limits submissions per email or phone number.
        RateLimiter::for('enquiries', fn (Request $request): array => [
            Limit::perMinute(3)->by('enquiries:minute:'.$request->ip()),
            Limit::perHour(10)->by('enquiries:hour:'.$request->ip()),
        ]);

        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->numbers();

            // The breached-password check calls an external service, so it only runs in production.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
