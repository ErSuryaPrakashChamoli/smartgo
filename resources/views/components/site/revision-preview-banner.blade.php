@props(['number', 'state', 'expiresAt'])

<div {{ $attributes->class(['bg-limited-soft text-limited']) }}>
    <p class="container-page flex items-start gap-2 py-2 text-xs font-semibold sm:text-sm">
        <x-heroicon-m-eye class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>Preview of version {{ $number }} ({{ $state }}). Not published: only signed-in staff can open this link, which expires at {{ $expiresAt }}.</span>
    </p>
</div>
