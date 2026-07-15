<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Provider Management</h1>
                <p class="mt-1 text-sm text-slate-400">Configura e gestisci le fonti dati esterne utilizzate dal sistema.</p>
            </div>
            <a href="#nuovo-provider" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">+ Nuovo provider</a>
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

        <section class="rounded-2xl bg-slate-800/65 p-5 shadow-lg shadow-black/10">
            <div class="grid gap-5 text-sm md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <h2 class="font-semibold text-white">Guida rapida</h2>
                    <p class="mt-2 leading-6 text-slate-300">Normalmente non devi modificare nulla. Il sistema sceglie automaticamente il provider in base alla priorità impostata.</p>
                </div>
                <div>
                    <h2 class="font-semibold text-white">Attivazione / Disattivazione</h2>
                    <p class="mt-2 leading-6 text-slate-300">Disattivando un provider non verrà più usato nelle nuove sincronizzazioni. I dati già salvati, i mapping e le credenziali restano nel database.</p>
                </div>
                <div>
                    <h2 class="font-semibold text-white">Priorità</h2>
                    <p class="mt-2 leading-6 text-slate-300">Il numero più basso viene valutato prima. Esempio: <strong class="text-emerald-300">10</strong> è il provider preferito, <strong class="text-amber-300">20</strong> è il fallback successivo.</p>
                </div>
                <div>
                    <h2 class="font-semibold text-white">Quando intervenire</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-5 leading-6 text-slate-300">
                        <li>provider non funzionante;</li>
                        <li>cambio piano o endpoint;</li>
                        <li>rotazione credenziale;</li>
                        <li>aggiunta di una nuova fonte.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            @foreach ($providers as $provider)
                <x-fo-accordion
                    :title="$provider->name"
                    :subtitle="'Codice: '.$provider->code"
                    bodyClass="bg-slate-100 text-slate-900"
                >
                    <x-slot:badge>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $provider->is_enabled ? 'bg-emerald-400/15 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                            {{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}
                        </span>
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>Ruolo: <strong class="text-slate-200">{{ ucfirst($provider->role ?? 'non configurato') }}</strong></span>
                        <span>Priorità: <strong class="text-slate-200">{{ $provider->priority ?? '—' }}</strong></span>
                        <span>Piano: <strong class="text-slate-200">{{ $provider->plan ?: 'non indicato' }}</strong></span>
                        <span>Mapping: <strong class="text-slate-200">{{ $provider->mappings->count() }}</strong></span>
                    </x-slot:meta>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <div class="rounded-xl bg-emerald-50 p-4 text-emerald-900 ring-1 ring-emerald-200">
                            <h3 class="font-semibold">Stato corrente: {{ $provider->is_enabled ? 'ATTIVO' : 'DISATTIVATO' }}</h3>
                            <p class="mt-2 text-sm leading-5">
                                {{ $provider->is_enabled ? 'Questo provider è utilizzabile dal sistema nelle procedure compatibili.' : 'Questo provider è escluso dalle nuove chiamate runtime.' }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-amber-50 p-4 text-amber-900 ring-1 ring-amber-200">
                            <h3 class="font-semibold">Se disattivi questo provider</h3>
                            <p class="mt-2 text-sm leading-5">Il sistema proverà il provider con priorità successiva. Se non esiste un fallback compatibile, alcune procedure potrebbero fallire. Lo storico non viene eliminato.</p>
                            <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}" class="mt-3">
                                @csrf @method('PATCH')
                                <button class="rounded-lg px-3 py-2 text-sm font-semibold {{ $provider->is_enabled ? 'bg-amber-200 text-amber-950 hover:bg-amber-300' : 'bg-emerald-200 text-emerald-950 hover:bg-emerald-300' }}">
                                    {{ $provider->is_enabled ? 'Disattiva provider' : 'Riattiva provider' }}
                                </button>
                            </form>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-4 text-blue-900 ring-1 ring-blue-200">
                            <h3 class="font-semibold">Piano contrattuale</h3>
                            <p class="mt-2 text-sm leading-5">Indica il piano realmente acquistato. Aiuta a interpretare copertura, endpoint disponibili, rate limit e limiti storici. Non modifica il contratto esterno.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @csrf @method('PUT')

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-slate-700">Nome visualizzato</span>
                            <input name="name" value="{{ $provider->name }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-slate-700">Piano contrattuale</span>
                            <input name="plan" value="{{ $provider->plan }}" placeholder="Free, Basic, Pro..." class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                            <span class="block text-[11px] text-slate-500">Registra il piano acquistato come riferimento interno.</span>
                        </label>

                        <label class="space-y-1 md:col-span-2 xl:col-span-1">
                            <span class="text-xs font-medium text-slate-700">Base URL API</span>
                            <input name="base_url" value="{{ $provider->base_url }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-slate-700">Ruolo</span>
                            <select name="role" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                <option value="primary" @selected($provider->role === 'primary')>Primary — fonte preferita</option>
                                <option value="fallback" @selected($provider->role === 'fallback')>Fallback — copertura alternativa</option>
                                <option value="audit" @selected($provider->role === 'audit')>Audit — confronto</option>
                                <option value="statistics" @selected($provider->role === 'statistics')>Statistics — dati statistici</option>
                            </select>
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-slate-700">Priorità</span>
                            <input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                            <span class="block text-[11px] text-slate-500">Numero più basso = valutato prima.</span>
                        </label>

                        <div class="flex items-end">
                            <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salva modifiche</button>
                        </div>

                        <details class="md:col-span-2 xl:col-span-3 rounded-xl bg-slate-200 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Impostazioni avanzate</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <label class="space-y-1"><span class="text-xs text-slate-700">Timeout totale</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Timeout connessione</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Pausa retry (ms)</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                            </div>

                            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                                <section class="rounded-xl bg-white p-4 ring-1 ring-slate-300">
                                    <h3 class="text-sm font-semibold text-slate-900">Credenziale</h3>
                                    <div class="mt-3 space-y-2 text-sm">
                                        @forelse ($provider->credentials as $credential)
                                            <div class="flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2"><span class="font-mono text-slate-700">{{ $credential->credential_key }}</span><span class="text-xs text-slate-500">•••••••• · {{ $credential->rotated_at ?? 'mai ruotata' }}</span></div>
                                        @empty
                                            <p class="text-slate-500">Nessuna credenziale configurata.</p>
                                        @endforelse
                                    </div>
                                </section>

                                <section class="rounded-xl bg-white p-4 ring-1 ring-slate-300" data-mapping-section>
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-sm font-semibold text-slate-900">Mapping competizioni</h3>
                                            <p class="mt-1 text-xs text-slate-500">La lista completa resta sempre visibile.</p>
                                        </div>
                                        <select data-mapping-select class="rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-800 ring-1 ring-slate-300">
                                            <option value="">Tutte</option>
                                            @foreach ($provider->mappings as $mapping)
                                                <option value="{{ $mapping->league_id }}">{{ $mapping->league_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mt-3 overflow-x-auto">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-slate-500"><tr><th class="pb-2">Lega interna</th><th class="pb-2">External ID</th><th class="pb-2">Nome esterno</th></tr></thead>
                                            <tbody class="divide-y divide-slate-200">
                                                @forelse($provider->mappings as $mapping)
                                                    <tr data-mapping-row data-league-id="{{ $mapping->league_id }}"><td class="py-2 text-slate-900">{{ $mapping->league_name }}</td><td class="py-2 font-mono text-slate-700">{{ $mapping->external_id }}</td><td class="py-2 text-slate-600">{{ $mapping->external_name }}</td></tr>
                                                @empty
                                                    <tr><td colspan="3" class="py-3 text-slate-500">Nessun mapping registrato.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                        <p data-mapping-empty class="hidden py-4 text-sm text-slate-500">Nessun mapping corrisponde al filtro.</p>
                                    </div>
                                </section>
                            </div>
                        </details>
                    </form>

                    <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-4 grid gap-3 rounded-xl bg-slate-200 p-4 md:grid-cols-3">
                        @csrf
                        <input name="credential_key" placeholder="token / api_key" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                        <input type="password" name="credential_value" placeholder="Nuovo valore" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                        <button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400">Ruota credenziale</button>
                    </form>
                </x-fo-accordion>
            @endforeach
        </section>

        <div id="nuovo-provider">
            <x-fo-accordion title="Aggiungi provider" subtitle="Usa questa funzione solo quando stai integrando una nuova fonte dati." bodyClass="bg-slate-100 text-slate-900">
                <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">
                    @csrf
                    <input name="code" placeholder="codice_provider" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                    <input name="name" placeholder="Nome provider" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                    <input name="base_url" placeholder="https://api.example.com" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                    <select name="role" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"><option value="primary">Primary</option><option value="fallback">Fallback</option><option value="audit">Audit</option><option value="statistics">Statistics</option></select>
                    <input type="number" name="priority" value="100" min="1" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                    <input name="plan" placeholder="Piano contrattuale" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                    <input name="credential_key" placeholder="credential key" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                    <input type="password" name="credential_value" placeholder="credential value" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                    <div class="md:col-span-4"><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi provider</button></div>
                </form>
            </x-fo-accordion>
        </div>
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
