@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class(['fo-panel overflow-hidden']) }}>
    @if ($title || $description)
        <div class="border-b border-white/10 px-5 py-4 sm:px-6">
            @if ($title)
                <h2 class="text-base font-semibold text-white">{{ $title }}</h2>
            @endif

            @if ($description)
                <p class="mt-1 text-sm text-slate-400">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="p-5 sm:p-6">
        {{ $slot }}
    </div>
</section>
