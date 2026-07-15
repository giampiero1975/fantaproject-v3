<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Provider Management</h1>
            <p class="mt-1 text-sm text-slate-400">Controlla quali fonti dati sono disponibili al sistema e aggiorna solo ciò che cambia davvero.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-xl bg-emerald-400/10 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl bg-red-400/10 p-4 text-sm text-red-100">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl bg-violet-500/10 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Cosa devo fare in questa pagina?</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                        In condizioni normali non devi fare nulla. Verifica soltanto che i provider siano <strong class="text-white">attivi</strong>.
                        Intervieni quando cambi piano o chiave API, quando aggiungi un nuovo provider oppure quando vuoi escludere temporaneamente una fonte dalle chiamate.
                    </p>
                </div>
                <span class="rounded-full bg-violet-400/15 px-3 py-1 text-xs text-violet-100">Ambiente: {{ $environment }}</span>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl bg-slate-800/80 p-4">
                    <div class="text-sm font-semibold text-white">Lascia attivo</div>
                    <p class="mt-1 text-xs leading-5 text-slate-300">Il provider può essere interrogato dalle procedure compatibili.</p>
                </div>
                <div class="rounded-xl bg-slate-800/80 p-4">
                    <div class="text-sm font-semibold text-white">Disattiva solo se serve</div>
                    <p class="mt-1 text-xs leading-5 text-slate-300">Il provider non verrà più chiamato. Configurazione, mapping, credenziali e storico restano salvati.</p>
                </div>
                <div class="rounded-xl bg-slate-800/80 p-4">
                    <div class="text-sm font-semibold text-white">Aggiorna quando cambia</div>
                    <p class="mt-1 text-xs leading-5 text-slate-300">Modifica piano, URL o credenziale soltanto quando cambiano realmente presso il provider.</p>
                </div>
            </div>
        </section>

        <x-fo-accordion title="Guida ai campi" subtitle="Consulta questa sezione solo quando devi modificare una configurazione.">
            <div class="grid gap-4 text-sm md:grid-cols-2">
                <div><strong class="text-white">Priorità</strong><p class="mt-1 leading-5 text-slate-300">Ordine di valutazione: il numero più basso viene provato prima. 10 precede 20. Non è un voto di qualità.</p></div>
                <div><strong class="text-white">Ruolo</strong><p class="mt-1 leading-5 text-slate-300">Primary è la fonte preferita; Fallback copre i buchi; Audit serve al confronto; Statistics fornisce dati statistici specializzati.</p></div>
                <div><strong class="text-white">Piano contrattuale</strong><p class="mt-1 leading-5 text-slate-300">Annota il piano realmente acquistato, per esempio Free o Pro. Serve a capire limiti di stagioni, competizioni, endpoint e rate limit. Non modifica il contratto esterno.</p></div>
                <div><strong class="text-white">Timeout e retry</strong><p class="mt-1 leading-5 text-slate-300">Controllano quanto attendere e quante volte riprovare una richiesta fallita. Normalmente non vanno modificati.</p></div>
                <div><strong class="text-white">Credenziale</strong><p class="mt-1 leading-5 text-slate-300">Token o API key cifrata per l’ambiente corrente. Va ruotata solo quando cambia o scade.</p></div>
                <div><strong class="text-white">Mapping competizioni</strong><p class="mt-1 leading-5 text-slate-300">Collega la lega interna all’ID usato dal provider. Permette al sistema di evitare ricerche ambigue per nome.</p></div>
            </div>
        </x-fo-accordion>

        <section class="space-y-4">
            @foreach ($providers as $provider)
                <x-fo-accordion :title="$provider->name" :subtitle="$provider->code">
                    <x-slot:badge>
                        <span class="rounded-full px-2.5 py-1 text-xs {{ $provider->is_enabled ? 'bg-emerald-400/15 text-emerald-100' : 'bg-slate-600/60 text-slate-200' }}">
                            {{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}
                        </span>
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>{{ ucfirst($provider->role ?? 'non configurato') }}</span>
                        <span>Priorità {{ $provider->priority ?? '—' }}</span>
                        <span>Piano {{ $provider->plan ?: 'non indicato' }}</span>
                        <span>{{ $provider->mappings->count() }} mapping</span>
                    </x-slot:meta>

                    <div class="rounded-xl {{ $provider->is_enabled ? 'bg-emerald-400/8' : 'bg-amber-400/10' }} p-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-white">
                                    {{ $provider->is_enabled ? 'Il provider è utilizzabile' : 'Il provider è escluso dalle chiamate' }}
                                </h3>
                                <p class="mt-1 text-xs leading-5 text-slate-300">
                                    @if ($provider->is_enabled)
                                        Le procedure compatibili possono interrogarlo secondo ruolo, priorità e copertura disponibile.
                                    @else
                                        Non verrà interrogato. Nessun dato, mapping o segreto è stato cancellato; puoi riattivarlo in qualsiasi momento.
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}">
                                @csrf @method('PATCH')
                                <button class="rounded-xl px-4 py-2 text-sm font-semibold {{ $provider->is_enabled ? 'bg-amber-400/15 text-amber-100 hover:bg-amber-400/25' : 'bg-emerald-400/15 text-emerald-100 hover:bg-emerald-400/25' }}">
                                    {{ $provider->is_enabled ? 'Disattiva' : 'Riattiva' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="space-y-1"><span class="text-xs text-slate-300">Nome</span><input name="name" value="{{ $provider->name }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-300">Piano contrattuale</span><input name="plan" value="{{ $provider->plan }}" placeholder="Free, Basic, Pro..." class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"><span class="block text-[11px] text-slate-400">Solo informativo: descrive il piano acquistato.</span></label>
                        <label class="space-y-1 md:col-span-2"><span class="text-xs text-slate-300">Base URL API</span><input name="base_url" value="{{ $provider->base_url }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-300">Ruolo</span><select name="role" class="w-full rounded-xl bg-slate-950 px-3 py-2 text-white"><option value="primary" @selected($provider->role === 'primary')>Primary — fonte preferita</option><option value="fallback" @selected($provider->role === 'fallback')>Fallback — copertura alternativa</option><option value="audit" @selected($provider->role === 'audit')>Audit — confronto</option><option value="statistics" @selected($provider->role === 'statistics')>Statistics — dati statistici</option></select></label>
                        <label class="space-y-1"><span class="text-xs text-slate-300">Priorità</span><input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"><span class="block text-[11px] text-slate-400">Più basso = valutato prima.</span></label>

                        <details class="md:col-span-2 rounded-xl bg-slate-950/35 p-4">
                            <summary class="cursor-pointer text-sm font-medium text-slate-200">Impostazioni tecniche avanzate</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="space-y-1"><span class="text-xs text-slate-300">Timeout totale</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-300">Timeout connessione</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-300">Retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-300">Pausa retry (ms)</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-xl bg-slate-950/70 px-3 py-2 text-white"></label>
                            </div>
                        </details>

                        <div class="md:col-span-2"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salva modifiche</button></div>
                    </form>

                    <div class="mt-6 grid gap-6 xl:grid-cols-2">
                        <section class="rounded-xl bg-slate-950/35 p-4">
                            <h3 class="text-sm font-semibold text-white">Credenziale</h3>
                            <div class="mt-3 space-y-2 text-sm">
                                @forelse ($provider->credentials as $credential)
                                    <div class="flex items-center justify-between rounded-lg bg-slate-800/70 px-3 py-2"><span class="font-mono text-slate-200">{{ $credential->credential_key }}</span><span class="text-xs text-slate-400">•••••••• · {{ $credential->rotated_at ?? 'mai ruotata' }}</span></div>
                                @empty
                                    <p class="text-slate-400">Nessuna credenziale configurata.</p>
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-3 grid gap-3">
                                @csrf
                                <input name="credential_key" placeholder="token / api_key" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                                <input type="password" name="credential_value" placeholder="Nuovo valore" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                                <button class="rounded-xl bg-amber-400/15 px-4 py-2 text-sm text-amber-100 hover:bg-amber-400/25">Ruota credenziale</button>
                            </form>
                        </section>

                        <section class="rounded-xl bg-slate-950/35 p-4" data-mapping-section>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-white">Mapping competizioni</h3>
                                    <p class="mt-1 text-xs text-slate-400">La lista completa è sempre visibile. Usa l’imbuto solo per restringerla.</p>
                                </div>
                                <details class="relative">
                                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl bg-violet-400/15 text-violet-100 hover:bg-violet-400/25 [&::-webkit-details-marker]:hidden" title="Filtra mapping">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z"/></svg>
                                    </summary>
                                    <div class="absolute right-0 z-20 mt-2 w-64 rounded-xl bg-slate-800 p-3 shadow-2xl">
                                        <label class="text-xs font-semibold text-slate-300">Mostra</label>
                                        <select data-mapping-select class="mt-2 w-full rounded-lg bg-slate-950 px-3 py-2 text-sm text-white">
                                            <option value="">Tutte le competizioni</option>
                                            @foreach ($provider->mappings as $mapping)
                                                <option value="{{ $mapping->league_id }}">{{ $mapping->league_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </details>
                            </div>

                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="text-slate-400"><tr><th class="pb-2">Lega interna</th><th class="pb-2">External ID</th><th class="pb-2">Nome esterno</th></tr></thead>
                                    <tbody class="divide-y divide-white/5">
                                        @forelse($provider->mappings as $mapping)
                                            <tr data-mapping-row data-league-id="{{ $mapping->league_id }}"><td class="py-2 text-white">{{ $mapping->league_name }}</td><td class="py-2 font-mono text-slate-200">{{ $mapping->external_id }}</td><td class="py-2 text-slate-300">{{ $mapping->external_name }}</td></tr>
                                        @empty
                                            <tr><td colspan="3" class="py-3 text-slate-400">Nessun mapping registrato.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                <p data-mapping-empty class="hidden py-4 text-sm text-slate-400">Nessun mapping corrisponde al filtro.</p>
                            </div>
                        </section>
                    </div>
                </x-fo-accordion>
            @endforeach
        </section>

        <x-fo-accordion title="Aggiungi provider" subtitle="Usa questa funzione solo quando stai integrando una nuova fonte dati.">
            <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">
                @csrf
                <input name="code" placeholder="codice_provider" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                <input name="name" placeholder="Nome provider" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                <input name="base_url" placeholder="https://api.example.com" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                <select name="role" class="rounded-xl bg-slate-950 px-3 py-2 text-white"><option value="primary">Primary</option><option value="fallback">Fallback</option><option value="audit">Audit</option><option value="statistics">Statistics</option></select>
                <input type="number" name="priority" value="100" min="1" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white" required>
                <input name="plan" placeholder="Piano contrattuale" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white">
                <input name="credential_key" placeholder="credential key" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white">
                <input type="password" name="credential_value" placeholder="credential value" class="rounded-xl bg-slate-950/70 px-3 py-2 text-white">
                <div class="md:col-span-4"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi provider</button></div>
            </form>
        </x-fo-accordion>
    </div>

    <script>
        document.querySelectorAll('[data-mapping-section]').forEach((section) => {
            const select = section.querySelector('[data-mapping-select]');
            const rows = Array.from(section.querySelectorAll('[data-mapping-row]'));
            const empty = section.querySelector('[data-mapping-empty]');

            select?.addEventListener('change', () => {
                let visible = 0;
                rows.forEach((row) => {
                    const show = select.value === '' || row.dataset.leagueId === select.value;
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                empty?.classList.toggle('hidden', visible !== 0 || rows.length === 0);
            });
        });
    </script>
</x-app-layout>
