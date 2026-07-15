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

        <x-fo-accordion
            title="Guida operativa"
            subtitle="Significato dei campi e comportamento del registry provider."
            :open="true"
        >
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
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Attivazione</strong>
                        <p class="mt-2 leading-5 text-slate-400">Abilita o esclude il provider dalle chiamate runtime. Disattivare è reversibile e non elimina dati.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Priorità</strong>
                        <p class="mt-2 leading-5 text-slate-400">Numero usato per ordinare i provider: il valore più basso viene valutato prima. Per questo Football Data usa 10 e API-Football 20. Non è una percentuale né un punteggio di qualità.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Ruolo</strong>
                        <p class="mt-2 leading-5 text-slate-400"><b>Primary</b>: fonte preferita. <b>Fallback</b>: copre ciò che manca. <b>Audit</b>: usato per confronto. <b>Statistics</b>: fonte specializzata per dati statistici.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Piano</strong>
                        <p class="mt-2 leading-5 text-slate-400">Etichetta amministrativa del contratto o livello di accesso, ad esempio Free, Basic o Enterprise. Serve a spiegare limiti di copertura; non modifica automaticamente il contratto del provider.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Base URL</strong>
                        <p class="mt-2 leading-5 text-slate-400">Indirizzo radice delle API. Viene usato dal client per costruire tutte le richieste del provider.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Timeout e retry</strong>
                        <p class="mt-2 leading-5 text-slate-400">Timeout limita l’attesa totale; connect timeout limita la connessione iniziale; retry e pausa definiscono quanti tentativi ripetere e dopo quanti millisecondi.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Credenziali</strong>
                        <p class="mt-2 leading-5 text-slate-400">Token e API key sono cifrati con APP_KEY e separati per ambiente. La rotazione sostituisce il valore corrente senza mostrarlo in chiaro.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Mapping competizioni</strong>
                        <p class="mt-2 leading-5 text-slate-400">Collega una lega interna all’identificativo usato dal provider, evitando ricerche ambigue per nome.</p>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                        <strong class="text-white">Adapter applicativo</strong>
                        <p class="mt-2 leading-5 text-slate-400">La configurazione DB da sola non basta: ogni nuovo provider deve avere un adapter PHP che traduca il suo payload nel formato canonico.</p>
                    </div>
                </div>
            </div>
        </x-fo-accordion>

        <section class="space-y-4">
            @foreach ($providers as $provider)
                <x-fo-accordion
                    :title="$provider->name"
                    :subtitle="$provider->code"
                    :open="$loop->first"
                >
                    <x-slot:badge>
                        <span class="rounded-full px-2.5 py-1 text-xs {{ $provider->is_enabled ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                            {{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}
                        </span>
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>Ruolo: <strong class="text-slate-300">{{ $provider->role ?? 'non configurato' }}</strong></span>
                        <span>Priorità: <strong class="text-slate-300">{{ $provider->priority ?? '—' }}</strong></span>
                        <span>Piano: <strong class="text-slate-300">{{ $provider->plan ?: 'non indicato' }}</strong></span>
                        <span>Mapping: <strong class="text-slate-300">{{ $provider->mappings->count() }}</strong></span>
                    </x-slot:meta>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-white">Configurazione runtime</h3>
                            <p class="mt-1 text-xs text-slate-500">Le modifiche hanno effetto dalle chiamate successive. La priorità più bassa viene valutata prima.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}">
                            @csrf @method('PATCH')
                            <button class="rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-200 hover:bg-white/5">
                                {{ $provider->is_enabled ? 'Disattiva provider' : 'Attiva provider' }}
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="space-y-1"><span class="text-xs text-slate-400">Nome visualizzato</span><input name="name" value="{{ $provider->name }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Piano contrattuale</span><input name="plan" value="{{ $provider->plan }}" placeholder="Free, Basic, Enterprise..." class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><span class="block text-[11px] text-slate-500">Etichetta informativa; non cambia il piano sul sito del provider.</span></label>
                        <label class="space-y-1 md:col-span-2"><span class="text-xs text-slate-400">Base URL API</span><input name="base_url" value="{{ $provider->base_url }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1">
                            <span class="text-xs text-slate-400">Ruolo operativo</span>
                            <select name="role" class="w-full rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white">
                                <option value="primary" @selected($provider->role === 'primary')>Primary — fonte preferita</option>
                                <option value="fallback" @selected($provider->role === 'fallback')>Fallback — copertura alternativa</option>
                                <option value="audit" @selected($provider->role === 'audit')>Audit — confronto e congruità</option>
                                <option value="statistics" @selected($provider->role === 'statistics')>Statistics — dati statistici</option>
                            </select>
                        </label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Priorità di selezione</span><input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"><span class="block text-[11px] text-slate-500">Valore minore = valutato prima. Esempio: 10 precede 20.</span></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Timeout totale (secondi)</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Timeout connessione (secondi)</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Numero tentativi retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Pausa tra retry (ms)</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <div class="md:col-span-2"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salva configurazione</button></div>
                    </form>

                    <div class="mt-6 grid gap-6 border-t border-white/10 pt-5 xl:grid-cols-2">
                        <section>
                            <h3 class="text-sm font-semibold text-white">Credenziali cifrate</h3>
                            <p class="mt-1 text-xs text-slate-500">Valide soltanto per l’ambiente {{ $environment }}.</p>
                            <div class="mt-3 space-y-2 text-sm">
                                @forelse ($provider->credentials as $credential)
                                    <div class="flex items-center justify-between rounded-lg bg-black/20 px-3 py-2"><span class="font-mono text-slate-300">{{ $credential->credential_key }}</span><span class="text-xs text-slate-500">•••••••• · {{ $credential->rotated_at ?? 'mai ruotata' }}</span></div>
                                @empty
                                    <p class="text-slate-500">Nessuna credenziale per l’ambiente corrente.</p>
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-3 grid gap-3">
                                @csrf
                                <input name="credential_key" placeholder="token / api_key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                                <input type="password" name="credential_value" placeholder="Nuovo valore" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                                <button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm text-amber-100">Ruota credenziale</button>
                            </form>
                        </section>

                        <section data-mapping-section>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Mapping competizioni</h3>
                                    <p class="mt-1 text-xs text-slate-500">Filtra per lega interna, ID o nome esterno.</p>
                                </div>
                                <label class="relative block w-full sm:w-64">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z"/></svg>
                                    </span>
                                    <input type="search" data-mapping-filter placeholder="Filtra mapping..." class="w-full rounded-xl border border-white/10 bg-black/20 py-2 pl-9 pr-3 text-sm text-white placeholder:text-slate-600">
                                </label>
                            </div>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-slate-500"><tr><th class="pb-2">Lega interna</th><th class="pb-2">External ID</th><th class="pb-2">Nome esterno</th></tr></thead>
                                    <tbody class="divide-y divide-white/5" data-mapping-rows>
                                        @forelse($provider->mappings as $mapping)
                                            <tr data-mapping-row data-search="{{ mb_strtolower($mapping->league_name.' '.$mapping->external_id.' '.$mapping->external_name) }}"><td class="py-2 text-white">{{ $mapping->league_name }}</td><td class="py-2 font-mono text-slate-300">{{ $mapping->external_id }}</td><td class="py-2 text-slate-400">{{ $mapping->external_name }}</td></tr>
                                        @empty
                                            <tr><td colspan="3" class="py-3 text-slate-500">Nessun mapping registrato.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                <p data-mapping-empty class="hidden py-4 text-sm text-slate-500">Nessun mapping corrisponde al filtro.</p>
                            </div>
                        </section>
                    </div>
                </x-fo-accordion>
            @endforeach
        </section>

        <x-fo-accordion
            title="Aggiungi provider"
            subtitle="Crea catalogo e configurazione DB; l’adapter applicativo resta necessario per l’uso runtime."
        >
            <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">
                @csrf
                <input name="code" placeholder="codice_provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="name" placeholder="Nome provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="base_url" placeholder="https://api.example.com" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <select name="role" class="rounded-xl border border-white/10 bg-slate-950 px-3 py-2 text-white">
                    <option value="primary">Primary — fonte preferita</option>
                    <option value="fallback">Fallback — copertura alternativa</option>
                    <option value="audit">Audit — confronto</option>
                    <option value="statistics">Statistics — dati statistici</option>
                </select>
                <input type="number" name="priority" value="100" min="1" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="plan" placeholder="Piano contrattuale" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <input name="credential_key" placeholder="credential key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <input type="password" name="credential_value" placeholder="credential value" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <div class="md:col-span-4"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi provider</button></div>
            </form>
        </x-fo-accordion>
    </div>

    <script>
        document.querySelectorAll('[data-mapping-section]').forEach((section) => {
            const input = section.querySelector('[data-mapping-filter]');
            const rows = Array.from(section.querySelectorAll('[data-mapping-row]'));
            const empty = section.querySelector('[data-mapping-empty]');

            input?.addEventListener('input', () => {
                const query = input.value.trim().toLocaleLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const matches = row.dataset.search.includes(query);
                    row.classList.toggle('hidden', !matches);
                    if (matches) visible++;
                });

                empty?.classList.toggle('hidden', visible !== 0 || rows.length === 0);
            });
        });
    </script>
</x-app-layout>
