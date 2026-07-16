<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Gestione Stagioni</h1>
            <p class="mt-1 text-sm text-slate-400">Step 1 · selezione della competizione interna, provider registrati e sincronizzazione controllata.</p>
        </div>
    </x-slot>

    <div class="space-y-6" data-season-management>
        @if (session('status'))<div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Guida Step 1</h2>
                    <p class="mt-1 text-sm text-slate-400">Usa questa procedura all’avvio del progetto e a ogni cambio stagione. Il dry-run non scrive; Apply aggiorna timeline, date, current e mapping stagionali.</p>
                </div>
                <a href="{{ route('admin.providers.index') }}" class="rounded-xl border border-violet-400/30 bg-violet-400/10 px-4 py-2 text-sm text-violet-100">Gestisci provider</a>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm">
                <div><strong class="text-white">1. Seleziona</strong><p class="mt-1 text-slate-400">Scegli la lega interna; gli ID esterni sono letti dai mapping DB.</p></div>
                <div><strong class="text-white">2. Analizza</strong><p class="mt-1 text-slate-400">Scopre current e current + history fallback.</p></div>
                <div><strong class="text-white">3. Verifica</strong><p class="mt-1 text-slate-400">Controlla provider, date, azioni e coverage.</p></div>
                <div><strong class="text-white">4. Applica</strong><p class="mt-1 text-slate-400">Scrive solo dopo conferma esplicita APPLICA.</p></div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 xl:col-span-2">
                <h2 class="text-lg font-semibold text-white">Analizza timeline</h2>
                <p class="mt-1 text-sm text-slate-400">Nessun codice SA/SB o ID 135/136 da digitare: vengono risolti dal registry.</p>

                <form method="POST" action="{{ route('admin.seasons.analyze') }}" class="mt-5 grid gap-4 md:grid-cols-3">
                    @csrf
                    <label class="space-y-2 md:col-span-2">
                        <span class="text-sm font-medium text-slate-300">Competizione interna</span>
                        <select name="league_id" data-season-league-select class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                            <option value="">Seleziona...</option>
                            @foreach($leagues as $league)
                                <option value="{{ $league->id }}" data-country-id="{{ $league->country_id }}" @selected((string) old('league_id', $lastParameters['league_id'] ?? '') === (string) $league->id)>{{ $league->country_name ? $league->country_name.' · ' : '' }}{{ $league->name }} · ID interno {{ $league->id }}</option>
                            @endforeach
                        </select>
                        <span data-season-filter-empty class="hidden block text-xs text-amber-300">Nessuna competizione disponibile per la nazione selezionata.</span>
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-300">Override history</span>
                        <input type="number" name="history" value="{{ old('history', $lastParameters['history'] ?? '') }}" min="0" max="20" placeholder="Default {{ $historyFallback }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                        <span class="block text-xs text-slate-500">Vuoto = configurazione corrente {{ $historyFallback }}. Non modifica la configurazione.</span>
                    </label>
                    <div class="md:col-span-3"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Analizza senza scrivere</button></div>
                </form>
            </section>

            <aside class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <h2 class="text-lg font-semibold text-white">Configurazione attiva</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">History default</dt><dd class="text-white">{{ $historyFallback }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Fonte provider</dt><dd class="text-emerald-300">Database</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Credenziali</dt><dd class="text-emerald-300">Cifrate</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Scrittura automatica</dt><dd class="text-slate-300">No</dd></div>
                </dl>
            </aside>
        </div>

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Registry competizioni e provider</h2>
                    <p class="mt-1 text-sm text-slate-400">Questi valori sono informativi e provengono dal database.</p>
                </div>
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra per nazione" aria-label="Filtra competizioni per nazione">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z" /></svg>
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 w-64 rounded-xl bg-slate-900 p-3 shadow-xl ring-1 ring-white/10">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nazione</label>
                        <select data-season-country-filter class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2 text-sm text-white ring-1 ring-white/10">
                            <option value="">Tutte le nazioni</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->country_id }}">{{ $country->country_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </details>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500"><tr><th class="pb-3">Nazione</th><th class="pb-3">Competizione</th><th class="pb-3">Provider</th><th class="pb-3">Mapping</th><th class="pb-3">Ruolo</th><th class="pb-3">Stato</th><th class="pb-3">Piano</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($leagues as $league)
                            @foreach($league->providers as $provider)
                                <tr data-season-registry-row data-country-id="{{ $league->country_id }}"><td class="py-3 text-slate-400">{{ $league->country_name ?? '—' }}</td><td class="py-3 text-white">{{ $league->name }}</td><td class="py-3 text-slate-300">{{ $provider->provider_name }}</td><td class="py-3 font-mono text-slate-300">{{ $provider->external_id }}</td><td class="py-3 text-slate-400">{{ $provider->role ?? '—' }}</td><td class="py-3 {{ $provider->is_enabled ? 'text-emerald-300' : 'text-slate-500' }}">{{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}</td><td class="py-3 text-slate-400">{{ $provider->plan ?? '—' }}</td></tr>
                            @endforeach
                        @endforeach
                        <tr data-season-registry-empty class="hidden"><td colspan="7" class="py-5 text-center text-slate-500">Nessuna competizione per la nazione selezionata.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if($lastReport)
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><h2 class="text-lg font-semibold text-white">Ultimo report</h2><p class="mt-1 text-sm text-slate-400">{{ $lastMode === 'apply' ? 'APPLY' : 'DRY-RUN' }} · Exit code {{ $lastExitCode }} · Lega interna {{ $lastParameters['league_id'] ?? '—' }}</p></div>
                    @if($lastMode !== 'apply' && (int)$lastExitCode === 0)
                        <form method="POST" action="{{ route('admin.seasons.apply') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="league_id" value="{{ $lastParameters['league_id'] ?? '' }}">
                            <input type="hidden" name="history" value="{{ $lastParameters['history'] ?? '' }}">
                            <label class="space-y-1"><span class="block text-xs text-amber-200">Digita APPLICA</span><input name="confirmation" class="w-36 rounded-xl border border-amber-400/20 bg-black/20 px-3 py-2 text-white" required></label>
                            <button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100">Applica sincronizzazione</button>
                        </form>
                    @endif
                </div>

                @if($lastParameters['providers'] ?? false)
                    <div class="mt-5 grid gap-3 md:grid-cols-2">@foreach($lastParameters['providers'] as $provider)<div class="rounded-xl border border-white/10 bg-black/20 p-4"><div class="flex justify-between gap-3"><strong class="text-white">{{ $provider['name'] }}</strong><span class="font-mono text-slate-300">{{ $provider['external_id'] }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $provider['role'] ?? '—' }} · priorità {{ $provider['priority'] ?? '—' }} · {{ !empty($provider['is_enabled']) ? 'attivo' : 'disattivato' }}</p></div>@endforeach</div>
                @endif

                @if($lastReportData && !empty($lastReportData['timeline']))
                    <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Stagione</th><th class="pb-3">Current</th><th class="pb-3">Inizio</th><th class="pb-3">Fine</th><th class="pb-3">Azione</th><th class="pb-3">Provider</th></tr></thead><tbody class="divide-y divide-white/5">@foreach($lastReportData['timeline'] as $row)<tr><td class="py-3 text-white">{{ $row['label'] }}</td><td class="py-3 {{ $row['is_current'] ? 'text-emerald-300' : 'text-slate-400' }}">{{ $row['is_current'] ? 'Sì' : 'No' }}</td><td class="py-3 text-slate-300">{{ $row['start_date'] ?? '—' }}</td><td class="py-3 text-slate-300">{{ $row['end_date'] ?? '—' }}</td><td class="py-3 font-medium text-violet-200">{{ $row['action'] }}</td><td class="py-3 text-slate-300">{{ collect($row['providers'] ?? [])->where('available', true)->count() }}</td></tr>@endforeach</tbody></table></div>
                @endif

                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastReport }}</pre></details>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-season-management]');
            if (!root) return;

            const filter = root.querySelector('[data-season-country-filter]');
            const leagueSelect = root.querySelector('[data-season-league-select]');
            const leagueOptions = Array.from(leagueSelect?.querySelectorAll('option[data-country-id]') ?? []);
            const registryRows = Array.from(root.querySelectorAll('[data-season-registry-row]'));
            const leagueEmpty = root.querySelector('[data-season-filter-empty]');
            const registryEmpty = root.querySelector('[data-season-registry-empty]');

            const applyFilter = () => {
                const countryId = filter?.value ?? '';
                let visibleLeagues = 0;
                let visibleRows = 0;

                leagueOptions.forEach((option) => {
                    const show = countryId === '' || option.dataset.countryId === countryId;
                    option.hidden = !show;
                    option.disabled = !show;
                    if (show) visibleLeagues++;
                });

                if (leagueSelect?.selectedOptions[0]?.disabled) {
                    leagueSelect.value = '';
                }

                registryRows.forEach((row) => {
                    const show = countryId === '' || row.dataset.countryId === countryId;
                    row.classList.toggle('hidden', !show);
                    if (show) visibleRows++;
                });

                leagueEmpty?.classList.toggle('hidden', visibleLeagues !== 0);
                registryEmpty?.classList.toggle('hidden', visibleRows !== 0);
            };

            filter?.addEventListener('change', applyFilter);
            applyFilter();
        })();
    </script>
</x-app-layout>