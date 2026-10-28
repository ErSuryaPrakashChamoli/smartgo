{{-- Shown during maintenance; never reads the database. --}}
<x-site.error-page status="503" title="Back soon" :navigation="App\Services\Content\SiteNavigation::withoutMenus()" :links="false">
    The SmartGro website is being updated. Please try again in a few minutes.
</x-site.error-page>
