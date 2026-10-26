<?php

use App\Http\Controllers\Web\DesignPreviewController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublishedContentController;
use App\Http\Middleware\EnsureDesignPreviewIsAvailable;
use App\Models\MediaAsset;
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

// Insights, customer stories and FAQs (FR-CMS-7): published items only.
Route::controller(PublishedContentController::class)->group(function (): void {
    Route::get('/insights', 'insights')->name('insights.index');
    Route::get('/insights/{slug}', 'insight')->where('slug', '[a-z0-9-]+')->name('insights.show');
    Route::get('/customers', 'customers')->name('customers.index');
    Route::get('/customers/{slug}', 'customer')->where('slug', '[a-z0-9-]+')->name('customers.show');
    Route::get('/faqs', 'faqs')->name('faqs');
});

// Image renditions, shown to visitors only when published content uses them (BR-MED-3).
Route::get('/media/{mediaAsset}/{rendition}', MediaController::class)
    ->whereNumber('mediaAsset')
    ->whereIn('rendition', array_keys(MediaAsset::RENDITIONS))
    ->name('media.show');

// Published pages and live redirects, for paths no other route claims (BR-CMS-6).
Route::fallback(PageController::class);
