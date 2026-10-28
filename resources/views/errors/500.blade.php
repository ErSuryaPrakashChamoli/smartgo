{{-- Never reads the database: it may be why the page failed. --}}
<x-site.error-page status="500" title="Something went wrong" :navigation="App\Services\Content\SiteNavigation::withoutMenus()" :links="false">
    The website could not show this page. Please try again in a few minutes.
</x-site.error-page>
