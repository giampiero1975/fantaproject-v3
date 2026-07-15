<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Provider Management</h1>
            <p class="mt-1 text-sm text-slate-400">Configurazione runtime, priorità, credenziali cifrate e mapping delle fonti dati.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <x-fo-accordion title="Guida operativa" subtitle="Significato dei campi e comportamento del registry provider.">
            <x-slot:badge>
                <span class="rounded-full border border-violet-400/20 bg-violet-400/10 px-3 py-1 text-xs text-violet-200">Ambiente: {{ $environment }}</span>
            </x-slot:badge>

            <div class="space-y-6 text-sm text-slate-300">
                <section>
                    <h3 class="font-semibold text-white">Che cos’è il registry runtime</h3>
                    <p class="mt-2 leading-6 text-slate-400">
                        È l’elenco dei provider che l’applicazione può interrogare durante una procedura. Un provider attivo e dotato del relativo adapter PHP entra nel registry; uno disattivato viene ignorato senza cancellare configurazione, credenziali, mapping o storico.
                    </p>
                </section>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Attivazione</strong><p class="mt-2 leading-5 text-slate-400">Abilita o esclude il provider dalle chiamate runtime. Disattivare è reversibile e non elimina dati.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Priorità</strong><p class="mt-2 leading-5 text-slate-400">Ordina i provider: il valore più basso viene valutato prima. Per questo 10 precede 20. Non è una percentuale né un voto di qualità.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Ruolo</strong><p class="mt-2 leading-5 text-slate-400"><b>Primary</b>: fonte preferita. <b>Fallback</b>: copertura alternativa. <b>Audit</b>: confronto. <b>Statistics</b>: dati statistici specializzati.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Piano contrattuale</strong><p class="mt-2 leading-5 text-slate-400">Registra il livello di accesso acquistato, per esempio Free, Basic, Pro o Enterprise. Serve a interpretare limiti di stagioni, competizioni, endpoint e rate limit e a spiegare perché una capability può risultare indisponibile. È solo informativo: non cambia il contratto sul sito del provider.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Base URL</strong><p class="mt-2 leading-5 text-slate-400">Indirizzo radice delle API usato dal client per costruire le richieste.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Timeout e retry</strong><p class="mt-2 leading-5 text-slate-400">Definiscono attesa totale, connessione iniziale, numero di nuovi tentativi e pausa tra i tentativi.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Credenziali</strong><p class="mt-2 leading-5 text-slate-400">Token e API key sono cifrati con APP_KEY e separati per ambiente.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Mapping competizioni</strong><p class="mt-2 leading-5 text-slate-400">Collega una lega interna all’identificativo esterno del provider, evitando ricerche ambigue per nome.</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800/55 p-4"><strong class="text-white">Adapter applicativo</strong><p class="mt-2 leading-5 text-slate-400">La configurazione DB da sola non basta: ogni provider deve avere un adapter PHP che normalizzi il payload.</p></div>
                </div>
            </div>
        </x-fo-accordion>

        <section class="space-y-5">
            @foreach ($providers as $provider)
                <x-fo-accordion :title="$provider->name" :subtitle="$provider->code">
                    <x-slot:badge>
                        <span class="rounded-full px-2.5 py-1 text-xs {{ $provider->is_enabled ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">{{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}</span>
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>Ruolo: <strong class="text-slate-300">{{ $provider->role ?? 'non configurato' }}</strong></span>
                        <span>Priorità: <strong class="text-slate-300">{{ $provider->priority ?? '—' }}</strong></span>
                        <span>Piano: <strong class="text-slate-300">{{ $provider->plan ?: 'non indicato' }}</strong></span>
                        <span>Mapping: <strong class="text-slate-300">{{ $provider->mappings->count() }}</strong></span>
                    </x-slot:meta>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="text-sm font-semibold text-white">Configurazione runtime</h3><p class="mt-1 text-xs text-slate-500">Le modifiche hanno effetto dalle chiamate successive.</p></div>
                        <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}">@csrf @method('PATCH')<button class="rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-200 hover:bg-white/5">{{ $provider->is_enabled ? 'Disattiva provider' : 'Attiva provider' }}</button></form>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="space-y-1"><span class="text-xs text-slate-400">Nome visualizzato</span><input name="name" value="{{ $provider->name }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Piano contrattuale</span><input name="plan" value="{{ $provider->plan }}" placeholder="Free, Basic, Pro, Enterprise..." class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><span class="block text-[11px] leading-4 text-slate-500">Descrive il piano acquistato e aiuta a interpretare copertura e limiti; non modifica il contratto esterno.</span></label>
                        <label class="space-y-1 md:col-span-2"><span class="text-xs text-slate-400">Base URL API</span><input name="base_url" value="{{ $provider->base_url }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Ruolo operativo</span><select name="role" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white"><option value="primary" @selected($provider->role === 'primary')>Primary — fonte preferita</option><option value="fallback" @selected($provider->role === 'fallback')>Fallback — copertura alternativa</option><option value="audit" @selected($provider->role === 'audit')>Audit — confronto e congruità</option><option value="statistics" @selected($provider->role === 'statistics')>Statistics — dati statistici</option></select></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Priorità di selezione</span><input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><span class="block text-[11px] text-slate-500">Valore minore = valutato prima.</span></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Timeout totale</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Timeout connessione</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Pausa retry (ms)</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <div class="md:col-span-2"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salva configurazione</button></div>
                    </form>

                    <div class="mt-6 grid gap-6 border-t border-white/10 pt-5 xl:grid-cols-2">
                        <section>
                            <h3 class="text-sm font-semibold text-white">Credenziali cifrate</h3>
                            <div class="mt-3 space-y-2 text-sm">@forelse ($provider->credentials as $credential)<div class="flex items-center justify-between rounded-lg bg-black/20 px-3 py-2"><span class="font-mono text-slate-300">{{ $credential->credential_key }}</span><span class="text-xs text-slate-500">•••••••• · {{ $credential->rotated_at ?? 'mai ruotata' }}</span></div>@empty<p class="text-slate-500">Nessuna credenziale per l’ambiente corrente.</p>@endforelse</div>
                            <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-3 grid gap-3">@csrf<input name="credential_key" placeholder="token / api_key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><input type="password" name="credential_value" placeholder="Nuovo valore" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm text-amber-100">Ruota credenziale</button></form>
                        </section>

                        <section data-mapping-section>
                            <div class="flex items-center justify-between gap-3">
                                <div><h3 class="text-sm font-semibold text-white">Mapping competizioni</h3><p class="mt-1 text-xs text-slate-500">Usa l’imbuto per mostrare solo le competizioni selezionate.</p></div>
                                <details class="relative" data-funnel>
                                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl border border-violet-400/25 bg-violet-400/10 text-violet-200 hover:bg-violet-400/20 [&::-webkit-details-marker]:hidden" title="Filtra mapping" aria-label="Filtra mapping">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z"/></svg>
                                    </summary>
                                    <div class="absolute right-0 z-20 mt-2 min-w-64 rounded-xl border border-slate-700 bg-slate-900 p-3 shadow-2xl">
                                        <div class="mb-2 flex items-center justify-between"><span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Competizioni</span><button type="button" data-filter-reset class="text-xs text-violet-300 hover:text-violet-200">Mostra tutte</button></div>
                                        <div class="max-h-64 space-y-2 overflow-auto">
                                            @forelse ($provider->mappings as $mapping)
                                                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-white/5"><input type="checkbox" value="{{ $mapping->league_id }}" data-filter-league class="rounded border-slate-600 bg-slate-950 text-violet-500 focus:ring-violet-400"><span class="text-sm text-slate-300">{{ $mapping->league_name }}</span></label>
                                            @empty
                                                <p class="text-sm text-slate-500">Nessun mapping disponibile.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                            </div>
                            <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-2">Lega interna</th><th class="pb-2">External ID</th><th class="pb-2">Nome esterno</th></tr></thead><tbody class="divide-y divide-white/5">@forelse($provider->mappings as $mapping)<tr data-mapping-row data-league-id="{{ $mapping->league_id }}"><td class="py-2 text-white">{{ $mapping->league_name }}</td><td class="py-2 font-mono text-slate-300">{{ $mapping->external_id }}</td><td class="py-2 text-slate-400">{{ $mapping->external_name }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">Nessun mapping registrato.</td></tr>@endforelse</tbody></table><p data-mapping-empty class="hidden py-4 text-sm text-slate-500">Nessun mapping corrisponde al filtro selezionato.</p></div>
                        </section>
                    </div>
                </x-fo-accordion>
            @endforeach
        </section>

        <x-fo-accordion title="Aggiungi provider" subtitle="Crea catalogo e configurazione DB; l’adapter applicativo resta necessario per l’uso runtime.">
            <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">@csrf<input name="code" placeholder="codice_provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><input name="name" placeholder="Nome provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><input name="base_url" placeholder="https://api.example.com" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><select name="role" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white"><option value="primary">Primary — fonte preferita</option><option value="fallback">Fallback — copertura alternativa</option><option value="audit">Audit — confronto</option><option value="statistics">Statistics — dati statistici</option></select><input type="number" name="priority" value="100" min="1" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><input name="plan" placeholder="Piano contrattuale" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><input name="credential_key" placeholder="credential key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><input type="password" name="credential_value" placeholder="credential value" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><div class="md:col-span-4"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi provider</button></div></form>
        </x-fo-accordion>
    </div>

    <script>
        document.querySelectorAll('[data-mapping-section]').forEach((section) => {
            const checks = Array.from(section.querySelectorAll('[data-filter-league]'));
            const rows = Array.from(section.querySelectorAll('[data-mapping-row]'));
            const empty = section.querySelector('[data-mapping-empty]');
            const reset = section.querySelector('[data-filter-reset]');

            const apply = () => {
                const selected = checks.filter((check) => check.checked).map((check) => check.value);
                let visible = 0;
                rows.forEach((row) => {
                    const show = selected.length === 0 || selected.includes(row.dataset.leagueId);
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                empty?.classList.toggle('hidden', visible !== 0 || rows.length === 0);
            };

            checks.forEach((check) => check.addEventListener('change', apply));
            reset?.addEventListener('click', () => { checks.forEach((check) => check.checked = false); apply(); });
        });
    </script>
</x-app-layout>
