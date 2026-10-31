<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/*
 * Phase 14 review (OD-134): no model may be open to mass assignment. Each model in app/Models either
 * guards every attribute (Laravel's default) or lists the attributes a form may fill; none is
 * unguarded. Staff accounts list exactly the fields the Super Admin's staff editor fills (name, email,
 * password, whether the account is active), so a form can never set property access, two-step login
 * secrets or recovery codes; roles are never attributes.
 */
it('keeps every model guarded or limited to listed attributes, never sensitive ones', function () {
    $models = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.Str::before(basename($file), '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract());

    expect($models)->not->toBeEmpty()
        ->and(Model::isUnguarded())->toBeFalse();

    foreach ($models as $class) {
        $model = new $class;

        expect($model->getGuarded() === ['*'] || $model->getFillable() !== [])->toBeTrue($class.' is open to mass assignment')
            ->and(array_values(array_intersect($model->getFillable(), ['id', 'remember_token', 'has_all_property_access', 'has_email_authentication', 'app_authentication_secret', 'app_authentication_recovery_codes'])))->toBe([], $class.' lets a form fill a protected attribute');
    }

    expect((new User)->getFillable())->toBe(['name', 'email', 'password', 'is_active']);
});
