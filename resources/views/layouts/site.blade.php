@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'preview' => false,
    'homeHref' => '/',
    'categories' => [],
    'properties' => [],
])

<!DOCTYPE html>
<html lang="en-IN" class="scroll-pt-24">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f7f7f2">
        <title>{{ $title ? $title.' · SmartGro' : 'SmartGro · Work smart. Grow together.' }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @endif

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh pb-24 lg:pb-0">
        <a href="#content" class="sr-only z-50 rounded-lg bg-forest px-4 py-3 font-semibold text-paper focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

        @if ($preview)
            <x-site.preview-banner />
        @endif

        <x-site.header :home-href="$homeHref" :categories="$categories" :properties="$properties" />

        <main id="content">
            {{ $slot }}
        </main>

        <x-site.footer :categories="$categories" :properties="$properties" />
        <x-site.mobile-action-bar />
    </body>
</html>
