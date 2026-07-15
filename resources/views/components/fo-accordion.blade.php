@props([
    'title',
    'subtitle' => null,
    'open' => false,
])

<details
    {{ $attributes->class([
        'group overflow-hidden rounded-2xl border border-slate-700/80 bg-slate-900/70 shadow-[0_10px_30px_rgba(0,0,0,0.28)] ring-1 ring-white/[0.03] transition',
        'open:border-violet-400/35 open:bg-slate-900/95 open:shadow-[0_14px_36px_rgba(46,16,101,0.20)]',
    ]) }}
    @if ($open) open @endif
>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 bg-slate-800/55 px-5 py-4 transition hover:bg-slate-800/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400/60 group-open:bg-violet-950/25 [&::-webkit-details-marker]:hidden">
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

        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-white/10 bg-black/20">
            <svg
                class="size-5 text-slate-300 transition-transform duration-200 group-open:rotate-180"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.512a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </span>
    </summary>

    <div class="border-t border-violet-400/15 bg-slate-950/35 px-5 py-5">
        {{ $slot }}
    </div>
</details>
