@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-100']) }}>
        <div class="font-medium text-red-100">Controlla i dati inseriti.</div>

        <ul class="mt-3 list-inside list-disc text-sm text-red-100">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
