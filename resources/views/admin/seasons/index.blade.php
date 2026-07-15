<x-app-layout>
    <x-slot name="header">
        <x-fo-page-header
            title="Gestione Stagioni"
            subtitle="Step 1 · discovery provider-aware, timeline storica e sincronizzazione controllata"
        />
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <x-fo-card class="xl:col-span-2">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Analizza timeline</h2>
                        <p class="mt-1 text-sm text-slate-400">
                            Il sistema risolve automaticamente la lega interna dai mapping provider, scopre la stagione corrente e costruisce la timeline con il fallback storico configurato.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.seasons.analyze') }}" class="grid gap-4 md:grid-cols-3">
                        @csrf

                        <label class="space-y-2">
                            <span class="text-sm font-medium text-slate-300">Codice Football-Data</span>
                            <input
                                type="text"
                                name="competition"
                                value="{{ old('competition', 'SA') }}"
                                class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white focus:border-violet-400 focus:ring-violet-400"
                                required
                            >
                        </label>

                        <label class="space-y-2">
                            <span class="text-sm font-medium text-slate-300">ID API-Football</span>
                            <input
                                type="number"
                                name="api_league_id"
                                value="{{ old('api_league_id', 135) }}"
                                min="1"
                                class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white focus:border-violet-400 focus:ring-violet-400"
                                required
                            >
                        </label>

                        <label class="space-y-2">
                            <span class="text-sm font-medium text-slate-300">History fallback</span>
                            <input
                                type="number"
                                name="history"
                                value="{{ old('history', $historyFallback) }}"
                                min="0"
                                max="20"
                                class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white focus:border-violet-400 focus:ring-violet-400"
                            >
                            <span class="block text-xs text-slate-500">Default `.env`: {{ $historyFallback }}</span>
                        </label>

                        <div class="md:col-span-3 flex flex-wrap items-center gap-3">
                            <button type="submit" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-400">
                                Analizza senza scrivere
                            </button>
                            <span class="text-xs text-slate-500">Dry-run obbligatorio prima dell'applicazione.</span>
                        </div>
                    </form>
                </div>
            </x-fo-card>

            <x-fo-card>
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Stato Step 1</h2>
                        <p class="mt-1 text-sm text-slate-400">Backend e UI condividono lo stesso motore `season:sync`.</p>
                    </div>

                    <dl class="space-y-3 text-sm">
                        @foreach ([
                            'Provider registry' => 'Pronto',
                            'Capability detection' => 'Pronto',
                            'Fallback API' => 'Pronto',
                            'Timeline e date' => 'Pronto',
                            'Dry-run' => 'Pronto',
                            'Apply controllato' => 'Pronto',
                        ] as $label => $status)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ $label }}</dt>
                                <dd class="font-medium text-emerald-300">{{ $status }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </x-fo-card>
        </div>

        @if ($lastReport)
            <x-fo-card>
                <div class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Ultimo report</h2>
                            <p class="mt-1 text-sm text-slate-400">
                                Modalità: {{ $lastMode === 'apply' ? 'APPLY' : 'DRY-RUN' }} · Exit code: {{ $lastExitCode }}
                            </p>
                        </div>

                        @if ($lastMode !== 'apply' && (int) $lastExitCode === 0)
                            <form method="POST" action="{{ route('admin.seasons.apply') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <input type="hidden" name="competition" value="{{ old('competition', 'SA') }}">
                                <input type="hidden" name="api_league_id" value="{{ old('api_league_id', 135) }}">
                                <input type="hidden" name="history" value="{{ old('history', $historyFallback) }}">

                                <label class="space-y-2">
                                    <span class="block text-xs font-medium text-amber-200">Digita APPLICA</span>
                                    <input
                                        type="text"
                                        name="confirmation"
                                        autocomplete="off"
                                        class="w-36 rounded-xl border border-amber-400/20 bg-black/20 px-3 py-2 text-white focus:border-amber-300 focus:ring-amber-300"
                                        required
                                    >
                                </label>

                                <button type="submit" class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100 hover:bg-amber-400/20 focus:outline-none focus:ring-2 focus:ring-amber-300">
                                    Applica sincronizzazione
                                </button>
                            </form>
                        @endif
                    </div>

                    <pre class="max-h-[34rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastReport }}</pre>
                </div>
            </x-fo-card>
        @endif
    </div>
</x-app-layout>
