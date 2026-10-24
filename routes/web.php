<?php

use App\Http\Controllers\Web\DesignPreviewController;
use App\Http\Middleware\EnsureDesignPreviewIsAvailable;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(EnsureDesignPreviewIsAvailable::class)
    ->prefix('design')
    ->name('design.')
    ->controller(DesignPreviewController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/foundations', 'foundations')->name('foundations');
        Route::get('/components', 'components')->name('components');
        Route::get('/home', 'home')->name('home');
        Route::get('/locations', 'locations')->name('locations');
        Route::get('/locations/{city}', 'city')->name('city');
        Route::get('/locations/{city}/{property}', 'property')->name('property');
        Route::get('/sample-property', 'sampleProperty')->name('sample-property');
        Route::get('/workspaces', 'workspaces')->name('workspaces');
        Route::get('/workspaces/{category}', 'workspace')->name('workspace');
        Route::get('/enterprise', 'enterprise')->name('enterprise');
        Route::get('/flows/{flow}', 'flow')->name('flow');
    });
