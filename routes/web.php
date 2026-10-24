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
    });
