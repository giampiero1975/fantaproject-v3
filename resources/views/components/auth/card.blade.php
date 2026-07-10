@props([
    'title',
    'description' => null,
])

<div class="w-full max-w-[470px] overflow-hidden rounded-lg border border-white/15 bg-[#071225]/78 shadow-2xl shadow-black/35 backdrop-blur-xl">
    <div class="relative min-h-[260px] overflow-hidden border-b border-white/10 bg-[#08152B] px-6 pb-8 pt-8">
        <div class="absolute -bottom-24 -left-20 h-72 w-72 rounded-full border border-[#2962FF]/35 bg-[#0D1B3D]/80 shadow-[0_0_80px_rgba(41,98,255,0.24)]"></div>

        <div class="relative flex flex-col items-center text-center">
            <a href="/" class="group inline-flex flex-col items-center gap-4" aria-label="Fanta Oracle home">
                <x-fanta-oracle-logo variant="full-dark" size="card" />
                <span class="-mt-2 block max-w-xs text-sm leading-6 text-slate-300">
                    Advanced Predictive Engine for Fantasy Football Analytics
                </span>
            </a>
        </div>
    </div>

    <div class="px-6 py-7 sm:px-8">
        <div class="mb-6 text-center">
            <h1 class="text-xl font-semibold tracking-tight text-white">{{ $title }}</h1>

            @if ($description)
                <p class="mt-2 text-sm text-slate-400">{{ $description }}</p>
            @endif
        </div>

        {{ $slot }}
    </div>
</div>
