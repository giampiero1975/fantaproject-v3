@props([
    'padding' => 'p-5 sm:p-6',
])

<section {{ $attributes->class(['fo-card', $padding]) }}>
    {{ $slot }}
</section>
