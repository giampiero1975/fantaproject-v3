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
                <div><h2 class="font-semibold text-white">Configurato</h2><p class="mt-2 leading-6 text-slate-300">Il provider ha chiamate o runtime definiti nel DB.</p></div>
                <div><h2 class="font-semibold text-white">Attivo</h2><p class="mt-2 leading-6 text-slate-300">Il provider è abilitato per le procedure compatibili.</p></div>
                <div><h2 class="font-semibold text-white">Se disattivi</h2><p class="mt-2 leading-6 text-slate-300">Il provider non viene più usato nelle nuove chiamate. Storico, mapping e credenziali restano salvati.</p></div>
                <div><h2 class="font-semibold text-white">Priorità</h2><p class="mt-2 leading-6 text-slate-300"><strong class="text-emerald-300">10</strong> viene provato prima di <strong class="text-amber-300">20</strong>. Il numero non indica qualità.</p></div>
            </div>
        </section>

        <section class="space-y-4">
            @foreach ($providers as $provider)
                @php
                    $knownPlans = ['Free', 'Basic', 'Pro', 'Enterprise'];
                    $hasCustomPlan = filled($provider->plan) && ! in_array($provider->plan, $knownPlans, true);
                    $hasHttpAdapter = $provider->http_mappings_count > 0;
                    $httpCapabilities = $provider->http_mappings->pluck('capability')->unique()->values();
                    $hasTeamsHttpAdapter = $httpCapabilities->contains('teams');
                    $hasRuntimeConfiguration = $hasHttpAdapter;
                    $runtimeReady = $hasRuntimeConfiguration && $provider->is_enabled;
                @endphp

                <x-fo-accordion :title="$provider->name" :subtitle="$provider->code" bodyClass="bg-slate-100 text-slate-900">
                    <x-slot:badge>
                        @if ($hasRuntimeConfiguration)
                            <span class="inline-flex shrink-0 items-center rounded-full bg-sky-500/20 px-2.5 py-1 text-xs font-semibold text-sky-100 ring-1 ring-sky-300/20">Configurato</span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full bg-amber-400/20 px-2.5 py-1 text-xs font-semibold text-amber-100 ring-1 ring-amber-300/20">Da configurare</span>
                        @endif

                        @if ($runtimeReady)
                            <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-500/20 px-2.5 py-1 text-xs font-semibold text-emerald-100 ring-1 ring-emerald-300/20">Attivo</span>
                        @else
                            <span class="inline-flex shrink-0 items-center rounded-full bg-slate-600/70 px-2.5 py-1 text-xs font-semibold text-slate-100 ring-1 ring-slate-400/20">Non attivo</span>
                        @endif
                    </x-slot:badge>

                    <x-slot:meta>
                        <span>Codice: <strong class="text-slate-200">{{ $provider->code }}</strong></span>
                        <span>Ruolo: <strong class="text-slate-200">{{ ucfirst($provider->role ?? 'non configurato') }}</strong></span>
                        <span>Priorità: <strong class="text-slate-200">{{ $provider->priority ?? '—' }}</strong></span>
                        <span>Piano: <strong class="text-slate-200">{{ $provider->plan ?: 'non indicato' }}</strong></span>
                        <span>HTTP mapping: <strong class="text-slate-200">{{ $provider->http_mappings_count }}</strong></span>
                        @if ($hasHttpAdapter)
                            <span>HTTP capability: <strong class="text-slate-200">{{ $httpCapabilities->implode(', ') }}</strong></span>
                        @endif
                        <span>Runtime: <strong class="text-slate-200">{{ $hasRuntimeConfiguration ? 'configurato' : 'da configurare' }}</strong></span>
                    </x-slot:meta>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <div class="rounded-xl {{ $runtimeReady ? 'bg-emerald-50 text-emerald-950 ring-emerald-200' : ($hasRuntimeConfiguration ? 'bg-blue-50 text-blue-950 ring-blue-200' : 'bg-amber-50 text-amber-950 ring-amber-200') }} p-4 ring-1">
                            <h3 class="font-semibold">Stato corrente: {{ $runtimeReady ? 'ATTIVO' : ($hasRuntimeConfiguration ? 'CONFIGURATO' : 'DA CONFIGURARE') }}</h3>
                            <p class="mt-2 text-sm leading-5">
                                @if ($hasHttpAdapter)
                                    Hai configurato via UI: <strong>{{ $httpCapabilities->implode(', ') }}</strong>. Ogni procedura richiede la propria capability.
                                @elseif (! $hasRuntimeConfiguration)
                                    Il provider è registrato nel DB, ma non ha ancora chiamate runtime configurate.
                                @else
                                    {{ $runtimeReady ? 'Il sistema può utilizzare questo provider nelle procedure compatibili.' : 'Il provider è escluso dalle nuove chiamate runtime.' }}
                                @endif
                            </p>
                            <div class="mt-3 space-y-1 text-xs font-medium">
                                <p>HTTP mapping: {{ $provider->http_mappings_count }}</p>
                                <p>Runtime squadre: <span class="{{ $hasTeamsHttpAdapter ? 'text-emerald-700' : 'text-amber-700' }}">{{ $hasTeamsHttpAdapter ? 'pronto' : 'non pronto, manca endpoint teams' }}</span></p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-amber-50 p-4 text-amber-950 ring-1 ring-amber-200">
                            <h3 class="font-semibold">Cosa manca per usarlo?</h3>
                            @if ($hasHttpAdapter && ! $hasTeamsHttpAdapter)
                                <p class="mt-2 text-sm leading-5">Al momento questo provider e' configurato solo per <strong>{{ $httpCapabilities->implode(', ') }}</strong>. Per entrare nel runtime squadre serve aggiungere una chiamata HTTP con capability <strong>teams</strong>.</p>
                            @elseif ($hasTeamsHttpAdapter)
                                <p class="mt-2 text-sm leading-5">Il provider ha una configurazione compatibile con il runtime squadre.</p>
                            @else
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-5">
                                    <li>non verrà più usato nelle nuove sincronizzazioni;</li>
                                    <li>verrà provato il provider con priorità successiva;</li>
                                    <li>se manca un fallback, alcune procedure possono fallire;</li>
                                    <li>lo storico non viene eliminato.</li>
                                </ul>
                            @endif
                            <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}" class="mt-3">
                                @csrf @method('PATCH')
                                <input type="hidden" name="is_enabled" value="{{ $runtimeReady ? 0 : 1 }}">
                                <button @disabled(! $hasRuntimeConfiguration) class="rounded-lg px-3 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 {{ $runtimeReady ? 'bg-amber-200 text-amber-950 hover:bg-amber-300' : 'bg-emerald-200 text-emerald-950 hover:bg-emerald-300' }}">{{ ! $hasRuntimeConfiguration ? 'Configura runtime' : ($runtimeReady ? 'Disattiva provider' : 'Riattiva provider') }}</button>
                            </form>
                        </div>

                        <div class="rounded-xl bg-blue-50 p-4 text-blue-950 ring-1 ring-blue-200">
                            <h3 class="font-semibold">Chiamate configurate</h3>
                            <p class="mt-2 text-sm leading-5">Configura endpoint, query, test request e mapping JSON del provider.</p>
                            <div class="mt-3 space-y-2">
                                @forelse ($provider->http_mappings as $httpMapping)
                                    <div class="rounded-lg bg-white p-3 text-xs ring-1 ring-blue-200">
                                        @php($httpMappingStatus = $httpMapping->mapping_validation_status ?? $httpMapping->validation_status)
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <strong class="text-blue-950">{{ $httpMapping->label ?: "{$httpMapping->capability} · {$httpMapping->operation}" }}</strong>
                                                <div class="mt-0.5 text-blue-700">{{ $httpMapping->capability }} · {{ $httpMapping->operation }}</div>
                                            </div>
                                            <span class="{{ $httpMappingStatus === 'mapping_validated' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $httpMappingStatus }}</span>
                                        </div>
                                        <div class="mt-2 break-all font-mono text-blue-900">{{ $httpMapping->method }} {{ $httpMapping->endpoint }}</div>
                                        @if (! empty($httpMapping->query_params_decoded))
                                            <div class="mt-1 break-all font-mono text-blue-700">?{{ http_build_query($httpMapping->query_params_decoded) }}</div>
                                        @endif
                                        <div class="mt-2 text-blue-800">Items: <code>{{ $httpMapping->items_path ?: 'root object' }}</code> · Campi: {{ count($httpMapping->field_mappings_decoded) }}</div>
                                        <form method="POST" action="{{ route('admin.providers.http-adapter.destroy', [$provider->id, $httpMapping->id]) }}" class="mt-3" onsubmit="return confirm('Eliminare questa configurazione HTTP salvata? Verranno rimossi anche i mapping dei campi collegati.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded-lg bg-red-50 px-3 py-1.5 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Elimina configurazione</button>
                                        </form>
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
                                <label class="space-y-1">
                                    <span class="text-xs text-slate-700">Autenticazione HTTP</span>
                                    <select name="auth_type" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                        <option value="none" @selected(($provider->auth_type ?? 'none') === 'none')>Nessuna</option>
                                        <option value="header" @selected(($provider->auth_type ?? 'none') === 'header')>Header</option>
                                        <option value="query" @selected(($provider->auth_type ?? 'none') === 'query')>Query param</option>
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs text-slate-700">Nome credenziale</span>
                                    <input name="credential_key" value="{{ $provider->credential_key }}" placeholder="es. token, api_key" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs text-slate-700">Nome header</span>
                                    <input name="auth_header_name" value="{{ $provider->auth_header_name }}" placeholder="es. Authorization oppure x-api-key" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs text-slate-700">Query param auth</span>
                                    <input name="auth_query_param" value="{{ $provider->auth_query_param }}" placeholder="es. api_key" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                                </label>
                                <label class="space-y-1 md:col-span-2 xl:col-span-4">
                                    <span class="text-xs text-slate-700">Header HTTP extra</span>
                                    <textarea name="http_headers" rows="3" placeholder="Accept-Encoding=gzip" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-xs text-slate-900 ring-1 ring-slate-300">{{ $provider->http_headers_text }}</textarea>
                                    <span class="block text-[11px] text-slate-500">Una coppia key=value per riga. Usalo per header tecnici del provider, non per la credenziale.</span>
                                </label>
                            </div>
                        </details>

                        <button class="w-full rounded-lg bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-500">Salva configurazione provider</button>
                    </form>

                    <section class="mt-4 rounded-xl bg-red-50 p-4 text-red-950 ring-1 ring-red-200">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div class="max-w-3xl">
                                <h3 class="text-sm font-semibold">Elimina provider</h3>
                                <p class="mt-1 text-sm leading-5">
                                    Rimuove il provider dal database insieme a chiamate HTTP, mapping payload, credenziali, configurazioni runtime e collegamenti alle competizioni/stagioni.
                                    Il contratto interno dei campi resta disponibile per gli altri provider.
                                </p>
                            </div>
                            <form method="POST" action="{{ route('admin.providers.destroy', $provider->id) }}" class="grid gap-2 sm:min-w-96 sm:grid-cols-[1fr_auto]" data-provider-delete-form data-provider-code="{{ $provider->code }}" onsubmit="return confirm('Eliminare definitivamente {{ $provider->name }}? Questa operazione rimuove anche chiamate, mapping e collegamenti salvati.');">
                                @csrf
                                @method('DELETE')
                                <label class="space-y-1">
                                    <span class="text-xs font-medium text-red-900">Digita {{ $provider->code }} per confermare</span>
                                    <input name="confirmation_code" autocomplete="off" placeholder="{{ $provider->code }}" required class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-red-200" data-provider-delete-code>
                                </label>
                                <button class="self-end rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-600" data-provider-delete-button disabled>Elimina provider</button>
                            </form>
                        </div>
                    </section>

                    <div class="mt-5 grid gap-5">
                        <section class="rounded-xl bg-white p-4 ring-1 ring-slate-300">
                            <h3 class="text-sm font-semibold text-slate-900">Credenziale in uso</h3>
                            <p class="mt-1 text-xs text-slate-500">Il nome tecnico è definito dalla configurazione provider e non deve essere scelto manualmente.</p>
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
                    </div>
                </x-fo-accordion>
            @endforeach
        </section>

        <div id="nuovo-provider" data-provider-capabilities='@json($providerCapabilities)'>
            <x-fo-accordion title="Aggiungi provider" subtitle="Usa questa funzione solo quando stai integrando una nuova fonte dati." bodyClass="bg-slate-100 text-slate-900">
                <div class="mb-5 rounded-xl bg-blue-50 p-4 text-sm text-blue-950 ring-1 ring-blue-200">
                    <h3 class="font-semibold">Prima di registrare un provider</h3>
                    <p class="mt-2 leading-5">Questa funzione salva catalogo, configurazione runtime e credenziale. Le chiamate operative vanno poi definite in Provider Management tramite endpoint e mapping.</p>
                </div>

                <form method="POST" action="{{ route('admin.providers.store') }}" class="grid gap-4 md:grid-cols-4">
                    @csrf
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
                    <label class="space-y-1"><span class="text-xs font-medium text-slate-700">Nome tecnico credenziale</span><input name="credential_key" placeholder="es. api_token" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300"><span class="block text-[11px] text-slate-500">Deve corrispondere al nome richiesto dal provider.</span></label>
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

        document.querySelectorAll('[data-provider-delete-form]').forEach((form) => {
            const expectedCode = form.dataset.providerCode || '';
            const input = form.querySelector('[data-provider-delete-code]');
            const button = form.querySelector('[data-provider-delete-button]');

            const syncDeleteButton = () => {
                button.disabled = input.value.trim() !== expectedCode;
            };

            input.addEventListener('input', syncDeleteButton);
            syncDeleteButton();
        });

    </script>
</x-app-layout>
