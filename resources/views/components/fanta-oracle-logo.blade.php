@props([
    'variant' => 'full-dark',
    'size' => 'md',
])

@php
    $assets = [
        'full-main' => 'brand/fantaoracle-icon-full-main.png',
        'full-dark' => 'brand/fantaoracle-icon-full-dark.png',
        'full-corporate' => 'brand/fantaoracle-icon-full-corporate.png',
        'symbol' => 'brand/fantaoracle-icon-full.png',
        'symbol-square' => 'brand/fantaoracle-icon-full-square.png',
        'symbol-dark' => 'brand/browser-tab-icon.png',
    ];

    $path = $assets[$variant] ?? $assets['full-dark'];
    $exists = file_exists(public_path($path));

    $classes = match ($size) {
        'nav' => 'h-10 w-auto max-w-[190px]',
        'card' => 'h-44 w-auto max-w-[340px]',
        'hero' => 'h-80 w-auto max-w-[380px]',
        'icon-lg' => 'h-72 w-72 object-contain',
        'icon-md' => 'h-24 w-24 object-contain',
        default => 'h-14 w-auto',
    };
@endphp

@if ($exists)
    <img
        {{ $attributes->merge(['class' => $classes . ' object-contain']) }}
        src="{{ asset($path) }}"
        alt="Fanta Oracle"
    >
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 text-white']) }}>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#7B2CFF]/70 bg-[#0D1B3D]">
            <span class="h-4 w-4 rounded-full border-2 border-[#7B2CFF]"></span>
        </span>
        @if (str_starts_with($variant, 'full'))
            <span class="text-2xl font-semibold tracking-tight">
                Fanta<span class="bg-gradient-to-r from-[#7B2CFF] to-[#2962FF] bg-clip-text text-transparent">Oracle</span>
            </span>
        @endif
    </span>
@endif
