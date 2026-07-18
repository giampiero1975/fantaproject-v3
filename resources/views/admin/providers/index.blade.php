<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Provider Management</h1>
                <p class="mt-1 text-sm text-slate-400">Controlla lo stato delle fonti dati e modifica soltanto ciò che cambia davvero.</p>
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
            <div class="grid gap-5 text-sm md:grid-cols-2 xl:grid-cols-5">
                <div><h2 class="font-semibold text-white">Registrato</h2><p class="mt-2 leading-6 text-slate-300">Il provider esiste nel DB con configurazione runtime, credenziali e metadata.</p></div>
                <div><h2 class="font-semibold text-white">Adapter richiesto</h2><p class="mt-2 leading-6 text-slate-300">Il provider è salvato, ma manca ancora il codice PHP che normalizza le risposte.</p></div>
                <div><h2 class="font-semibold text-white">Attivo</h2><p class="mt-2 leading-6 text-slate-300">Il provider ha adapter installato ed è abilitato per le procedure runtime.</p></div>
                <div><h2 class="font-semibold text-white">Se disattivi</h2><p class="mt-2 leading-6 text-slate-300">Il provider non viene più usato nelle nuove chiamate. Storico, mapping e credenziali restano salvati.</p></div>
                <div><h2 class="font-semibold text-white">Priorità</h2><p class="mt-2 leading-6 text-slate-300"><strong class="text-emerald-300">10</strong> viene provato prima di <strong class="text-amber-300">20</strong>. Il numero non indica qualità.</p></div>
            </div>
        </section>

        <section class="space-y-4">
            @foreach ($providers as $provider)
                @php
                    $knownPlans = ['Free', 'Basic', 'Pro', 'Enterprise'];
                    $hasCustomPlan = filled($provider->plan) && ! in_array($provider->plan, $knownPlans, true);
                    $requiresAdapter = ! $provider->adapter_supported;
                    $runtimeReady = $provider->adapter_supported && $provider->is_enabled;
                @endphp

                <x-fo-accordion :title="$provider->name" :subtitle="$provider->code" bodyClass="bg-slate-100 text-slate-900">
                    <x-slot:badge>
                        @if ($requiresAdapter)
                            <span class="rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-semibold text-amber-200">Adapter richiesto</span>
                        @else
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $runtimeReady ? 'bg-emerald-400/15 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">{{ $runtimeReady ? 'Attivo' : 'Disattivato' }}</span>
                        @endif
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>Codice: <strong class="text-slate-200">{{ $provider->code }}</strong></span>
                        <span>Ruolo: <strong class="text-slate-200">{{ ucfirst($provider->role ?? 'non configurato') }}</strong></span>
                        <span>Priorità: <strong class="text-slate-200">{{ $provider->priority ?? '—' }}</strong></span>
                        <span>Piano: <strong class="text-slate-200">{{ $provider->plan ?: 'non indicato' }}</strong></span>
                        <span>Mapping leghe: <strong class="text-slate-200">{{ $provider->mappings->count() }}</strong></span>
                        <span>HTTP mapping: <strong class="text-slate-200">{{ $provider->http_mappings_count }}</strong></span>
                        <span>Adapter: <strong class="text-slate-200">{{ $provider->adapter_supported ? 'installato' : 'richiesto' }}</strong></span>
                    </x-slot:meta>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <div class="rounded-xl {{ $requiresAdapter ? 'bg-amber-50 text-amber-950 ring-amber-200' : 'bg-emerald-50 text-emerald-950 ring-emerald-200' }} p-4 ring-1">
                            <h3 class="font-semibold">Stato corrente: {{ $requiresAdapter ? 'ADAPTER RICHIESTO' : ($runtimeReady ? 'ATTIVO' : 'DISATTIVATO') }}</h3>
                            <p class="mt-2 text-sm leading-5">
                                @if ($requiresAdapter)
                                    Il provider è registrato nel DB ma non può essere usato finché non viene installato il relativo adapter PHP.
                                @else
                                    {{ $runtimeReady ? 'Il sistema può utilizzare questo provider nelle procedure compatibili.' : 'Il provider è escluso dalle nuove chiamate runtime.' }}
                                @endif
                            </p>
                            <p class="mt-3 text-xs font-medium">Mapping leghe: {{ $provider->mappings->count() }} · HTTP mapping: {{ $provider->http_mappings_count }}</p>
                        </div>

                        <div class="rounded-xl bg-amber-50 p-4 text-amber-950 ring-1 ring-amber-200">
                            <h3 class="font-semibold">Cosa succede se lo disattivi?</h3>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-5">
                                <li>non verrà più usato nelle nuove sincronizzazioni;</li>
                                <li>verrà provato il provider con priorità successiva;</li>
                                <li>se manca un fallback, alcune procedure possono fallire;</li>
                                <li>lo storico non viene eliminato.</li>
                            </ul>
                            <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}" class="mt-3">
                                @csrf @method('PATCH')
                                <button @disabled($requiresAdapter) class="rounded-lg px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 {{ $runtimeReady ? 'bg-amber-200 text-amber-950 hover:bg-amber-300' : 'bg-emerald-200 text-emerald-950 hover:bg-emerald-300' }}">{{ $requiresAdapter ? 'Installa adapter per attivare' : ($runtimeReady ? 'Disattiva provider' : 'Riattiva provider') }}</button>
                            </form>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-4 text-blue-950 ring-1 ring-blue-200">
                            <h3 class="font-semibold">HTTP adapter</h3>
                            <p class="mt-2 text-sm leading-5">Configura endpoint, query, test request e mapping JSON per provider senza adapter nativo.</p>
                            <div class="mt-3 space-y-2">
                                @forelse ($provider->http_mappings as $httpMapping)
                                    <div class="rounded-lg bg-white p-3 text-xs ring-1 ring-blue-200">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <strong class="text-blue-950">{{ $httpMapping->capability }} · {{ $httpMapping->operation }}</strong>
                                            <span class="{{ $httpMapping->is_enabled ? 'text-emerald-700' : 'text-amber-700' }}">{{ $httpMapping->mapping_validation_status ?? $httpMapping->validation_status }}</span>
                                        </div>
                                        <div class="mt-2 break-all font-mono text-blue-900">{{ $httpMapping->method }} {{ $httpMapping->endpoint }}</div>
                                        @if (! empty($httpMapping->query_params_decoded))
                                            <div class="mt-1 break-all font-mono text-blue-700">?{{ http_build_query($httpMapping->query_params_decoded) }}</div>
                                        @endif
                                        <div class="mt-2 text-blue-800">Items: <code>{{ $httpMapping->items_path ?: 'root object' }}</code> · Campi: {{ count($httpMapping->field_mappings_decoded) }}</div>
                                    </div>
                                @empty
                                    <p class="rounded-lg bg-white/60 p-3 text-xs text-blue-800 ring-1 ring-blue-100">Nessuna chiamata HTTP configurata.</p>
                                @endforelse
                            </div>
                            <a href="{{ route('admin.providers.http-adapter.configure', $provider->id) }}" class="mt-3 inline-flex rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">Configura e testa</a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 space-y-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="name" value="{{ $provider->name }}">

                        <section>
                            <h3 class="text-sm font-semibold text-slate-900">Configurazione</h3>
                            <div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <div class="space-y-1" data-plan-control>
                                    <label class="text-xs font-medium text-slate-700">Piano contrattuale</label>
                                    <input type="hidden" name="plan" value="{{ $provider->plan }}" data-plan-value>
                                    <select data-plan-select class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                        <option value="">Non indicato</option>
                                        @foreach ($knownPlans as $plan)
                                            <option value="{{ $plan }}" @selected($provider->plan === $plan)>{{ $plan }}</option>
                                        @endforeach
                                        <option value="__other__" @selected($hasCustomPlan)>Altro...</option>
                                    </select>
                                    <input type="text" data-plan-custom value="{{ $hasCustomPlan ? $provider->plan : '' }}" placeholder="Nome del piano" class="{{ $hasCustomPlan ? '' : 'hidden' }} w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                    <span class="block text-[11px] text-slate-500">Solo promemoria amministrativo; non influenza il runtime.</span>
                                </div>

                                <label class="space-y-1 md:col-span-2 xl:col-span-2">
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
                                    <span class="block text-[11px] text-slate-500">Cambialo solo se cambia la funzione del provider.</span>
                                </label>

                                <label class="space-y-1">
                                    <span class="text-xs font-medium text-slate-700">Priorità</span>
                                    <input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                    <span class="block text-[11px] text-slate-500">10 viene valutato prima di 20.</span>
                                </label>
                            </div>
                        </section>

                        <details class="rounded-xl bg-slate-200 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-800">Impostazioni tecniche avanzate</summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <label class="space-y-1"><span class="text-xs text-slate-700">Timeout totale</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Timeout connessione</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                                <label class="space-y-1"><span class="text-xs text-slate-700">Pausa retry (ms)</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                            </div>
                        </details>

                        <button class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-500">Salva configurazione provider</button>
                    </form>

                    <div class="mt-5 grid gap-5 xl:grid-cols-2">
                        <section class="rounded-xl bg-white p-4 ring-1 ring-slate-300">
                            <h3 class="text-sm font-semibold text-slate-900">Credenziale in uso</h3>
                            <p class="mt-1 text-xs text-slate-500">Il nome tecnico è definito dall’adapter e non deve essere scelto manualmente.</p>
                            <div class="mt-3 space-y-4 text-sm">
                                @forelse ($provider->credentials as $credential)
                                    <div class="rounded-lg bg-slate-100 p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $credential->credential_key }}</div>
                                                <div class="mt-1 break-all font-mono text-slate-900">{{ $credential->current_value ?? 'Impossibile decifrare la credenziale' }}</div>
                                                <div class="mt-1 text-xs text-slate-500">Ultima rotazione: {{ $credential->rotated_at ?? 'mai registrata' }}</div>
                                            </div>
                                            <span class="text-xs font-medium text-emerald-700">Configurata</span>
                                        </div>
                                        <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-3 grid gap-3 md:grid-cols-[1fr_auto]">
                                            @csrf
                                            <input type="hidden" name="credential_key" value="{{ $credential->credential_key }}">
                                            <input type="text" name="credential_value" placeholder="Nuovo valore" class="rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
                                            <button class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-400">Sostituisci</button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-slate-500">Nessuna credenziale configurata per questo ambiente.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-xl bg-white p-4 ring-1 ring-slate-300" data-mapping-section>
                            @php
                                $mappingCountries = $provider->mappings
                                    ->filter(fn ($mapping) => $mapping->country_id !== null)
                                    ->unique('country_id')
                                    ->sortBy('country_name');
                            @endphp
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div><h3 class="text-sm font-semibold text-slate-900">Mapping competizioni</h3><p class="mt-1 text-xs text-slate-500">Collegamenti tra lega interna e identificativo del provider.</p></div>
                                <details class="relative">
                                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-slate-100 text-slate-700 ring-1 ring-slate-300 hover:bg-slate-200 [&::-webkit-details-marker]:hidden" title="Filtra per nazione" aria-label="Filtra mapping per nazione">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z" /></svg>
                                    </summary>
                                    <div class="absolute right-0 z-20 mt-2 w-64 rounded-xl bg-white p-3 shadow-xl ring-1 ring-slate-300">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nazione</label>
                                        <select data-country-filter class="mt-2 w-full rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                            <option value="">Tutte le nazioni</option>
                                            @foreach ($mappingCountries as $country)<option value="{{ $country->country_id }}">{{ $country->country_name ?? 'Nazione non indicata' }}</option>@endforeach
                                        </select>
                                    </div>
                                </details>
                            </div>
                            <div class="mt-3 space-y-2">
                                @forelse($provider->mappings as $mapping)
                                    <div data-mapping-row data-country-id="{{ $mapping->country_id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-3">
                                        <div><div class="font-medium text-slate-900">{{ $mapping->league_name }}</div><div class="text-xs text-slate-500">{{ $mapping->country_name ?? 'Nazione non indicata' }} · {{ $mapping->external_name }}</div></div>
                                        <div class="rounded-md bg-slate-800 px-2.5 py-1 font-mono text-xs text-white">{{ $mapping->external_id }}</div>
                                    </div>
                                @empty
                                    <p class="py-3 text-slate-500">Nessun mapping registrato.</p>
                                @endforelse
                                <p data-mapping-empty class="hidden py-4 text-sm text-slate-500">Nessun mapping per la nazione selezionata.</p>
                            </div>
                        </section>
                    </div>
                </x-fo-accordion>
            @endforeach
        </section>

        <div id="nuovo-provider">
            <x-fo-accordion title="Aggiungi provider" subtitle="Usa questa funzione solo quando stai integrando una nuova fonte dati." bodyClass="bg-slate-100 text-slate-900">
                <div class="mb-5 rounded-xl bg-blue-50 p-4 text-sm text-blue-950 ring-1 ring-blue-200">
                    <h3 class="font-semibold">Prima di registrare un provider</h3>
                    <p class="mt-2 leading-5">Questa funzione salva catalogo, configurazione runtime e credenziale. Il provider diventa realmente utilizzabile solo quando esiste anche il relativo adapter applicativo che normalizza le sue risposte.</p>
                </div>

                <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">
                    @csrf
                    <div class="space-y-1 md:col-span-4">
                        <label class="text-xs font-medium text-slate-700">Adapter installato disponibile</label>
                        <select data-installed-adapter class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                            <option value="">Configurazione manuale o adapter non ancora installato</option>
                            @foreach ($availableAdapters as $adapter)
                                <option
                                    value="{{ $adapter['code'] }}"
                                    data-code="{{ $adapter['code'] }}"
                                    data-name="{{ $adapter['name'] }}"
                                    data-credential-key="{{ $adapter['credential_key'] }}"
                                    data-capabilities='@json($adapter['capabilities'])'
                                >
                                    {{ $adapter['name'] }} · {{ $adapter['code'] }}
                                </option>
                            @endforeach
                        </select>
                        <span class="block text-[11px] text-slate-500">Seleziona un adapter PHP gia' installato per compilare automaticamente codice, nome tecnico, credenziale e capabilities.</span>
                    </div>
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Codice provider</span><input name="code" placeholder="es. thesportsdb" autocomplete="off" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required><span class="block text-[11px] text-slate-500">Puoi scrivere anche TheSportsDB: verrà salvato come codice tecnico minuscolo.</span></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Nome visualizzato</span><input name="name" placeholder="es. Sportmonks" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required></label>
                    <label class="space-y-1 md:col-span-2"><span class="text-xs font-medium text-slate-700">Base URL API</span><input name="base_url" placeholder="https://api.example.com" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Ruolo</span><select name="role" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"><option value="primary">Primary</option><option value="fallback">Fallback</option><option value="audit">Audit</option><option value="statistics">Statistics</option></select></label>
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Priorità</span><input type="number" name="priority" value="100" min="1" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required><span class="block text-[11px] text-slate-500">Numero più basso = valutato prima.</span></label>
                    <div class="space-y-1" data-plan-control>
                        <label class="text-xs font-medium text-slate-700">Piano contrattuale</label>
                        <input type="hidden" name="plan" value="" data-plan-value>
                        <select data-plan-select class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                            <option value="">Non indicato</option><option value="Free">Free</option><option value="Basic">Basic</option><option value="Pro">Pro</option><option value="Enterprise">Enterprise</option><option value="__other__">Altro...</option>
                        </select>
                        <input type="text" data-plan-custom placeholder="Nome del piano" class="hidden w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        <span class="block text-[11px] text-slate-500">Solo riferimento amministrativo.</span>
                    </div>
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Nome tecnico credenziale</span><input name="credential_key" placeholder="es. api_token" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"><span class="block text-[11px] text-slate-500">Deve corrispondere al nome richiesto dall’adapter.</span></label>
                    <label class="space-y-1 md:col-span-2"><span class="text-xs font-medium text-slate-700">Valore credenziale</span><input type="text" name="credential_value" placeholder="Token o API key" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"></label>
                    <div class="md:col-span-4"><button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Registra nuovo provider</button></div>
                </form>
            </x-fo-accordion>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-plan-control]').forEach((control) => {
            const select = control.querySelector('[data-plan-select]');
            const custom = control.querySelector('[data-plan-custom]');
            const value = control.querySelector('[data-plan-value]');

            const syncPlan = () => {
                const isOther = select.value === '__other__';
                custom.classList.toggle('hidden', !isOther);
                value.value = isOther ? custom.value.trim() : select.value;
            };

            select.addEventListener('change', syncPlan);
            custom.addEventListener('input', syncPlan);
            syncPlan();
        });

        document.querySelectorAll('[data-mapping-section]').forEach((section) => {
            const select = section.querySelector('[data-country-filter]');
            const rows = Array.from(section.querySelectorAll('[data-mapping-row]'));
            const empty = section.querySelector('[data-mapping-empty]');

            select?.addEventListener('change', () => {
                let visible = 0;
                rows.forEach((row) => {
                    const show = select.value === '' || row.dataset.countryId === select.value;
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                empty?.classList.toggle('hidden', visible !== 0 || rows.length === 0);
            });
        });
    </script>
</x-app-layout>
