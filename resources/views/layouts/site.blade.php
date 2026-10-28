@props([
    'title' => null,
    'description' => null,
    'noindex' => false,
    'preview' => false,
    'homeHref' => '/',
    'categories' => [],
    'properties' => [],
    'metaTitle' => null,
    'canonical' => null,
    'robots' => null,
    'navigation' => null,
    'shareImage' => null,
    'mobileActions' => null,
    'mobileContact' => null,
])

@php
    // The phone action bar (FR-DISC-6): a page may give its own buttons and contact details, such as a
    // location's enquiry link and phone number; otherwise the published header buttons and main details.
    $barActions = $mobileActions ?? $navigation['actions'] ?? null;
    $barContact = $mobileContact ?? $navigation['contact'] ?? [];
    $showsActionBar = $navigation === null || $barActions !== [] || collect($barContact)->whereIn('label', ['Phone', 'WhatsApp'])->isNotEmpty();
@endphp

<!DOCTYPE html>
{{-- Scroll padding keeps a focused or linked element clear of the sticky header and, on phones, of the action bar (WCAG 2.4.11). --}}
<html lang="en-IN" @class(['scroll-pt-24', 'scroll-pb-24 lg:scroll-pb-0' => $showsActionBar])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f7f7f2">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
        <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
        <title>{{ $metaTitle ?? ($title ? $title.' · SmartGro' : 'SmartGro · Work smart. Grow together.') }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @elseif ($robots)
            <meta name="robots" content="{{ $robots }}">
        @endif
        @if ($canonical && ! $noindex)
            <link rel="canonical" href="{{ $canonical }}">
            <meta property="og:type" content="website">
            <meta property="og:url" content="{{ $canonical }}">
            <meta property="og:title" content="{{ $metaTitle ?? ($title ? $title.' · SmartGro' : 'SmartGro') }}">
            @if ($description)
                <meta property="og:description" content="{{ $description }}">
            @endif
            @if ($shareImage)
                <meta property="og:image" content="{{ $shareImage }}">
                <meta name="twitter:card" content="summary_large_image">
            @endif
        @endif

        {{ $head ?? '' }}

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class(['min-h-dvh', 'pb-24 lg:pb-0' => $showsActionBar])>
        <a href="#content" class="sr-only z-50 rounded-lg bg-forest px-4 py-3 font-semibold text-paper focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Skip to content</a>

        @if ($preview)
            <x-site.preview-banner />
        @endif

        {{ $banner ?? '' }}

        <x-site.header :home-href="$homeHref" :categories="$categories" :properties="$properties" :navigation="$navigation" />

        <main id="content">
            {{ $slot }}
        </main>

        <x-site.footer :categories="$categories" :properties="$properties" :navigation="$navigation" />
        @if ($showsActionBar)
            <x-site.mobile-action-bar :actions="$navigation === null ? null : $barActions" :contact="$barContact" />
        @endif
    </body>
</html>
