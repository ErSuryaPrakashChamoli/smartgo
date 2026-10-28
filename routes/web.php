<?php

use App\Http\Controllers\Web\DesignPreviewController;
use App\Http\Controllers\Web\EnquiryController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublishedContentController;
use App\Http\Controllers\Web\WorkspaceController;
use App\Http\Middleware\EnsureDesignPreviewIsAvailable;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Locations (OD-17): cities, their sectors, the properties on the website, a page per property and per
// published workspace offering at it, and a comparison of two or three of them (FR-DISC-4). Anything not
// published answers 404. "area" is never a property address (SaveProperty::RESERVED_SLUGS) and "compare"
// never a city address (City::RESERVED_SLUGS), so those routes come first.
Route::controller(LocationController::class)->prefix('locations')->name('locations.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/compare', 'compare')->name('compare');
    Route::get('/{city}', 'city')->where('city', '[a-z0-9-]+')->name('city');
    Route::get('/{city}/area/{area}', 'area')->where(['city' => '[a-z0-9-]+', 'area' => '[a-z0-9-]+'])->name('area');
    Route::get('/{city}/{property}', 'property')->where(['city' => '[a-z0-9-]+', 'property' => '[a-z0-9-]+'])->name('property');
    Route::get('/{city}/{property}/{workspace}', 'offering')->where(['city' => '[a-z0-9-]+', 'property' => '[a-z0-9-]+', 'workspace' => '[a-z0-9-]+'])->name('offering');
});

// Workspace categories (IA §2): a page exists only while a published offering does (BR-PRODCFG-4).
Route::controller(WorkspaceController::class)->prefix('workspaces')->name('workspaces.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/{workspace}', 'category')->where('workspace', '[a-z0-9-]+')->name('category');
    Route::get('/{workspace}/{city}', 'categoryInCity')->where(['workspace' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('city');
});

// Enquiries (FR-BK-1): never a booking. Sending is rate limited per address (AppServiceProvider).
Route::controller(EnquiryController::class)->prefix('enquire')->name('enquiries.')->group(function (): void {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:enquiries')->name('store');
    Route::get('/received', 'received')->name('received');
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
