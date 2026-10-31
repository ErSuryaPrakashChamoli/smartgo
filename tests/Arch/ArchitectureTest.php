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
    ->classes()
    ->toExtend(Model::class);

arch('actions expose a single handle method')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toHaveMethod('handle');

arch('application code does not use eval, extract or unserialize')
    ->expect('App')
    ->not->toUse(['eval', 'extract', 'unserialize']);

arch('only the availability service and the inventory actions read allocations, so no surface computes availability on its own (FR-INV-6)')
    ->expect('App\\Models\\SpaceAllocation')
    ->toOnlyBeUsedIn([
        'App\\Models',
        'App\\Services\\Inventory',
        'App\\Actions\\Inventory',
        'App\\Policies\\SpaceAllocationPolicy',
        'Database\\Factories\\SpaceAllocationFactory',
        'App\\Filament\\Clusters\\Properties\\Resources\\Spaces\\RelationManagers\\AllocationsRelationManager',
    ]);
