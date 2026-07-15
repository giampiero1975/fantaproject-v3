@props([
    'title',
    'subtitle' => null,
    'open' => false,
])

<details
    {{ $attributes->class(['group overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]']) }}
    @if ($open) open @endif
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 transition hover:bg-white/[0.04] focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/60 [&::-webkit-details-marker]:hidden">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="truncate text-base font-semibold text-white">{{ $title }}</h2>
                @isset($badge)
                    {{ $badge }}
                @endisset
            </div>

            @if ($subtitle)
                <p class="mt-1 text-sm text-slate-400">{{ $subtitle }}</p>
            @endif

            @isset($meta)
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                    {{ $meta }}
                </div>
            @endisset
        </div>

        <svg
            class="size-5 shrink-0 text-slate-400 transition-transform duration-200 group-open:rotate-180"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.512a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
        </svg>
    </summary>

    <div class="border-t border-white/10 px-5 py-5">
        {{ $slot }}
    </div>
</details>
