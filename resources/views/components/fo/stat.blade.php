@props([
    'label',
    'value',
    'hint' => null,
    'icon' => 'chart-bar-square',
    'tone' => 'purple',
])

@php
    $tones = [
        'purple' => 'from-oracle-600/25 to-oracle-600/5 text-oracle-300 ring-oracle-500/20',
        'blue' => 'from-blue-500/25 to-blue-500/5 text-blue-300 ring-blue-500/20',
        'green' => 'from-emerald-500/25 to-emerald-500/5 text-emerald-300 ring-emerald-500/20',
        'amber' => 'from-amber-500/25 to-amber-500/5 text-amber-300 ring-amber-500/20',
    ];

    $toneClasses = $tones[$tone] ?? $tones['purple'];
@endphp

<article {{ $attributes->class(['fo-card p-5']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="fo-eyebrow">{{ $label }}</p>
            <p class="mt-3 text-3xl font-semibold tracking-tight text-white">{{ $value }}</p>

            @if ($hint)
                <p class="mt-2 text-sm text-slate-400">{{ $hint }}</p>
            @endif
        </div>

        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ring-1 {{ $toneClasses }}">
            <flux:icon :name="$icon" class="size-5" />
        </div>
    </div>
</article>
