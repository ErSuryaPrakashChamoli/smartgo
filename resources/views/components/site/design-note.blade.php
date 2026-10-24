@props(['title' => 'Design note'])

{{-- Preview-only annotation explaining behaviour to reviewers; never part of a production page. --}}
<aside {{ $attributes->class(['rounded-xl border border-dashed border-info/50 bg-info-soft/60 p-4 text-sm text-info']) }}>
    <p class="flex items-center gap-1.5 font-semibold">
        <x-heroicon-m-pencil-square class="size-4" aria-hidden="true" />
        {{ $title }}
    </p>
    <div class="mt-1 text-ink">{{ $slot }}</div>
</aside>
