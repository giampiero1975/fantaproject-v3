@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="fo-eyebrow">{{ $eyebrow }}</p>
        @endif

        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ $title }}</h1>

        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400 sm:text-base">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
