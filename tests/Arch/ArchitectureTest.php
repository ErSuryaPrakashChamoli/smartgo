<?php

use Illuminate\Database\Eloquent\Model;

arch('debugging helpers are not left in application code')
    ->expect(['dd', 'dump', 'ddd', 'var_dump', 'print_r', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('application code reads configuration, not environment variables')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('enums live in App\Enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('actions expose a single handle method')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toHaveMethod('handle');

arch('application code does not use eval, extract or unserialize')
    ->expect('App')
    ->not->toUse(['eval', 'extract', 'unserialize']);
