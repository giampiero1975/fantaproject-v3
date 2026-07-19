<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">HTTP Adapter · {{ $provider->name }}</h1>
                <p class="mt-1 text-sm text-slate-400">Configura una capability, prova la request e valida il mapping prima di salvare un adapter generico.</p>
            </div>
            <a href="{{ route('admin.providers.index') }}" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/10 hover:bg-slate-700">Torna ai provider</a>
        </div>
    </x-slot>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]"
         x-data="{
             selectedCapability: @js($formInput['capability'] ?? 'competitions'),
             selectedOperation: @js($formInput['operation'] ?? 'list'),
             selectedLabel: @js($formInput['label'] ?? ''),
             operationHelp: @js($operationDescriptions),
             contractTitle() {
                 const label = (this.selectedLabel || '').trim();

                 return label || `${this.selectedCapability} · ${this.selectedOperation}`;
             },
             clearOperationFields() {
                 this.selectedLabel = '';
                 this.$refs.endpoint.value = '';
                 this.$refs.queryParams.value = '';
                 this.$refs.bodyTemplate.value = '';
                 this.$refs.itemsPath.value = '';
                 this.$refs.fieldMappings.value = '';
             },
         }">
        <section class="rounded-2xl bg-slate-100 p-5 text-slate-900 shadow-lg shadow-black/10">
            <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-950 ring-1 ring-blue-200">
                <h2 class="font-semibold">Ordine guidato</h2>
                <p class="mt-2 leading-5">Parti da <strong>competitions</strong>. Solo dopo aver collegato la competizione esterna alla lega interna ha senso configurare seasons e teams.</p>
            </div>

            <section class="mt-5 rounded-xl bg-white p-4 ring-1 ring-slate-300">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-950">Chiamate configurate</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Queste sono le configurazioni HTTP gia salvate per il provider. Usa Carica nel form per riprenderne una.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.providers.http-adapter.configure', ['provider' => $provider->id, 'new' => 1]) }}" class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Nuova configurazione</a>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $savedEndpoints->count() }} salvate</span>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($savedEndpoints as $endpoint)
                        <article class="grid gap-3 rounded-xl bg-slate-50 p-3 text-xs ring-1 ring-slate-200 lg:grid-cols-[minmax(160px,220px)_minmax(0,1fr)] lg:items-start">
                            @php($endpointStatus = $endpoint->mapping_validation_status ?? $endpoint->validation_status)
                            <div>
                                <div class="font-semibold text-slate-950">{{ $endpoint->label ?: "{$endpoint->capability} · {$endpoint->operation}" }}</div>
                                <div class="mt-1 text-slate-500">{{ $endpoint->capability }} · {{ $endpoint->operation }}</div>
                            </div>

                            <div class="min-w-0">
                                <div class="break-all font-mono text-slate-800">{{ $endpoint->method }} {{ $endpoint->endpoint }}</div>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-slate-600">
                                    <span>Query: <code>{{ ! empty($endpoint->query_params_decoded) ? http_build_query($endpoint->query_params_decoded) : 'nessuna' }}</code></span>
                                    <span>Items: <code>{{ $endpoint->items_path ?: 'root object' }}</code></span>
                                    <span>Campi: {{ count($endpoint->field_mappings_decoded) }}</span>
                                    <span>Ultimo test: {{ $endpoint->last_status_code ?? 'non testato' }}</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 lg:col-span-2 lg:justify-end">
                                <span class="rounded-full px-2 py-0.5 font-semibold {{ $endpointStatus === 'mapping_validated' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $endpointStatus }}
                                </span>
                                <a href="{{ route('admin.providers.http-adapter.configure', ['provider' => $provider->id, 'capability' => $endpoint->capability, 'operation' => $endpoint->operation]) }}" class="inline-flex rounded-lg bg-slate-900 px-3 py-1.5 font-semibold text-white hover:bg-slate-700">Carica nel form</a>
                                <form method="POST" action="{{ route('admin.providers.http-adapter.destroy', [$provider->id, $endpoint->id]) }}" onsubmit="return confirm('Eliminare questa configurazione HTTP salvata? Verranno rimossi anche i mapping dei campi collegati.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="inline-flex rounded-lg bg-red-50 px-3 py-1.5 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Elimina configurazione</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">
                            Nessuna chiamata configurata. Salva il primo mapping runtime dopo un test valido.
                        </div>
                    @endforelse
                </div>
            </section>

            <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" data-http-adapter-form>
                @csrf
                <input type="hidden" name="loaded_endpoint_id" value="{{ $formInput['loaded_endpoint_id'] ?? '' }}">

                <div class="md:col-span-2 rounded-xl p-3 text-sm ring-1 {{ filled($formInput['loaded_endpoint_id'] ?? '') ? 'bg-amber-50 text-amber-950 ring-amber-200' : 'bg-emerald-50 text-emerald-950 ring-emerald-200' }}">
                    <span class="font-semibold">
                        {{ filled($formInput['loaded_endpoint_id'] ?? '') ? 'Modifica configurazione caricata' : 'Nuova configurazione' }}
                    </span>
                    <span class="ml-1">
                        {{ filled($formInput['loaded_endpoint_id'] ?? '') ? 'Stai aggiornando una chiamata esistente. Cambiamenti errati sovrascrivono il mapping salvato.' : 'Il salvataggio crea una nuova chiamata. Se capability e operation esistono gia, prima usa Carica nel form.' }}
                    </span>
                </div>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Capability</span>
                    <select name="capability" x-model="selectedCapability" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        @foreach ($capabilities as $capability)
                            <option value="{{ $capability }}" @selected(($formInput['capability'] ?? 'competitions') === $capability)>{{ $capability }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Operation</span>
                    <select name="operation" x-model="selectedOperation" @change="clearOperationFields()" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        @foreach ($operations as $operation => $label)
                            <option value="{{ $operation }}" @selected(($formInput['operation'] ?? 'list') === $operation)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="block text-[11px] text-slate-500">Esempio: lista competizioni o dettaglio singola competizione.</span>
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Nome configurazione</span>
                    <input name="label" x-ref="label" x-model="selectedLabel" value="{{ $formInput['label'] ?? '' }}" placeholder="es. Lista competizioni Football-Data" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                    <span class="block text-[11px] text-slate-500">Nome leggibile per riconoscere la chiamata salvata. Se vuoto viene mostrato capability · operation.</span>
                </label>

                <x-fo.card padding="p-4" class="md:col-span-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-white">Operation selezionata</span>
                        <code class="rounded bg-slate-950/70 px-2 py-0.5 text-xs text-violet-200" x-text="selectedOperation"></code>
                    </div>
                    <p class="mt-2 text-sm leading-5 text-slate-300" x-text="operationHelp[selectedOperation]?.when"></p>
                    <p class="mt-2 font-mono text-xs leading-5 text-slate-200" x-text="operationHelp[selectedOperation]?.example"></p>
                </x-fo.card>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Metodo</span>
                    <select name="method" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        <option value="GET" @selected(($formInput['method'] ?? 'GET') === 'GET')>GET</option>
                        <option value="POST" @selected(($formInput['method'] ?? 'GET') === 'POST')>POST</option>
                    </select>
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Base URL</span>
                    <input value="{{ $provider->base_url }}" disabled class="w-full rounded-lg bg-slate-200 px-3 py-2 text-slate-700 ring-1 ring-slate-300">
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Endpoint</span>
                    <input name="endpoint" x-ref="endpoint" value="{{ $formInput['endpoint'] ?? '' }}" placeholder="competitions/{provider_competition_code}/standings" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300" required>
                    <span class="block text-[11px] text-slate-500">Endpoint relativo alla base URL. Puoi usare variabili come <code>{provider_competition_code}</code>.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Query params</span>
                    <textarea name="query_params" x-ref="queryParams" rows="6" placeholder="season={season_year}" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['query_params'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: una coppia key=value per riga. I valori possono contenere variabili.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Body JSON</span>
                    <textarea name="body_template" x-ref="bodyTemplate" rows="6" placeholder='{"country":"Italy"}' class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['body_template'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Usato solo per POST. Per la prima fase usa GET.</span>
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Valori test variabili</span>
                    <textarea name="test_variables" rows="4" placeholder="provider_competition_code=SA&#10;season_year=2024" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['test_variables'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Usati solo dal pulsante Test request. Non vengono salvati nella configurazione runtime.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Items path</span>
                    <input name="items_path" x-ref="itemsPath" value="{{ $formInput['items_path'] ?? '' }}" placeholder="competitions" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">
                    <span class="block text-[11px] text-slate-500">Percorso dove si trova la lista nel JSON. Esempio: teams, leagues, data.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Field mapping</span>
                    <textarea name="field_mappings" x-ref="fieldMappings" rows="6" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['field_mappings'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: campo_interno=path_payload. Liste annidate: <code>pluck(path_array, path_valore)</code> oppure <code>map(path_array, campo=path, campo=path)</code>.</span>
                </label>

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button formaction="{{ route('admin.providers.http-adapter.test', $provider->id) }}" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Test request</button>
                    <button formaction="{{ route('admin.providers.http-adapter.save', $provider->id) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Salva mapping runtime</button>
                </div>
            </form>
        </section>

        <aside class="space-y-5">
            @if (session('status'))
                <section class="rounded-2xl bg-emerald-400/10 p-4 text-sm text-emerald-100 shadow-lg shadow-black/10">{{ session('status') }}</section>
            @endif

            <section class="rounded-2xl bg-slate-800/70 p-5 text-sm text-slate-200 shadow-lg shadow-black/10">
                <h2 class="font-semibold text-white">Provider</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="text-slate-400">Codice</dt><dd class="font-mono">{{ $provider->code }}</dd></div>
                    <div><dt class="text-slate-400">Base URL</dt><dd class="break-all font-mono">{{ $provider->base_url }}</dd></div>
                    <div><dt class="text-slate-400">Stato</dt><dd>{{ $provider->is_enabled ? 'Runtime attivo' : 'Runtime disattivato' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl bg-slate-800/70 p-5 text-sm text-slate-200 shadow-lg shadow-black/10">
                <h2 class="font-semibold text-white">
                    Campi interni ·
                    <span x-text="contractTitle()">{{ ($formInput['label'] ?? '') !== '' ? $formInput['label'] : "{$contractCapability} · {$contractOperation}" }}</span>
                </h2>
                <p class="mt-1 text-xs leading-5 text-slate-400">Questi campi appartengono solo alla operation selezionata. Cambiando operation cambia anche il contratto interno visualizzato.</p>
                @error('contract_field')
                    <div class="mt-3 rounded-xl bg-red-400/10 p-3 text-xs text-red-200 ring-1 ring-red-400/20">{{ $message }}</div>
                @enderror
                @if (collect(['field_key', 'label', 'description', 'data_type', 'is_required', 'sort_order', 'operation'])->contains(fn (string $field): bool => $errors->has($field)))
                    <div class="mt-3 rounded-xl bg-red-400/10 p-3 text-xs text-red-200 ring-1 ring-red-400/20">
                        <div class="font-semibold text-white">Campo interno non salvato</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4">
                            @foreach (['field_key', 'label', 'description', 'data_type', 'is_required', 'sort_order', 'operation'] as $errorField)
                                @error($errorField)
                                    <li>{{ $message }}</li>
                                @enderror
                            @endforeach
                        </ul>
                    </div>
                @endif
                @foreach ($internalFieldsByOperation as $operation => $internalFields)
                    <div x-show="selectedOperation === @js($operation)" x-cloak>
                        @if ($operation === $contractOperation && ! empty($unknownContractFields))
                            <div class="mt-3 rounded-xl bg-amber-400/10 p-3 text-xs text-amber-100 ring-1 ring-amber-400/20">
                                <div class="font-semibold text-white">Campi nuovi rilevati nel Field mapping</div>
                                <p class="mt-1 text-amber-100/80">Aggiungili al contratto prima di salvare il mapping.</p>
                                <div class="mt-3 space-y-3">
                                    @foreach ($unknownContractFields as $field)
                                        <form method="POST" action="{{ route('admin.providers.contract-fields.store', $provider->id) }}" class="rounded-lg bg-slate-950/50 p-3">
                                            @csrf
                                            <input type="hidden" name="capability" value="{{ $contractCapability }}">
                                            <input type="hidden" name="operation" value="{{ $operation }}">
                                            <input type="hidden" name="field_key" value="{{ $field }}">
                                            <div class="font-mono text-[11px] text-amber-100">{{ $field }}</div>
                                            <div class="mt-2 grid gap-2">
                                                <input name="label" value="{{ \Illuminate\Support\Str::headline($field) }}" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                                <textarea name="description" rows="2" placeholder="Descrizione del campo interno" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300"></textarea>
                                                <div class="grid grid-cols-[minmax(0,1fr)_80px] gap-2">
                                                    <select name="data_type" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                                        @foreach (['url', 'string', 'integer', 'float', 'boolean', 'date', 'datetime', 'json'] as $type)
                                                            <option value="{{ $type }}" @selected(\Illuminate\Support\Str::endsWith($field, '_url') ? $type === 'url' : $type === 'string')>{{ $type }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="number" name="sort_order" value="{{ 80 + ($loop->index * 10) }}" min="0" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                                </div>
                                                <label class="inline-flex items-center gap-2 text-[11px] text-slate-300">
                                                    <input type="checkbox" name="is_required" value="1" class="rounded border-white/20 bg-slate-900">
                                                    richiesto
                                                </label>
                                                <button class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Aggiungi al contratto</button>
                                            </div>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <details class="mt-3 rounded-xl bg-slate-950/60 p-3 ring-1 ring-white/10">
                            <summary class="cursor-pointer text-sm font-semibold text-emerald-200">Aggiungi nuovo campo · {{ $operation }}</summary>
                            <form method="POST" action="{{ route('admin.providers.contract-fields.store', $provider->id) }}" class="mt-3 grid gap-2">
                                @csrf
                                <input type="hidden" name="capability" value="{{ $contractCapability }}">
                                <input type="hidden" name="operation" value="{{ $operation }}">

                                <label class="grid gap-1">
                                    <span class="text-[11px] font-semibold text-slate-400">Field key</span>
                                    <input name="field_key" value="{{ old('field_key') }}" placeholder="competition_logo_url" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                    <span class="text-[11px] leading-4 text-slate-500">Nome interno in snake_case. Esempio: per il payload <code>startDate</code> usa <code>season_start_date</code> e poi nel mapping scrivi <code>season_start_date=startDate</code>.</span>
                                </label>

                                <label class="grid gap-1">
                                    <span class="text-[11px] font-semibold text-slate-400">Label</span>
                                    <input name="label" value="{{ old('label') }}" placeholder="Logo competizione" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                </label>

                                <label class="grid gap-1">
                                    <span class="text-[11px] font-semibold text-slate-400">Descrizione</span>
                                    <textarea name="description" rows="3" placeholder="Spiega cosa rappresenta il campo interno." class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">{{ old('description') }}</textarea>
                                </label>

                                <div class="grid grid-cols-[minmax(0,1fr)_80px] gap-2">
                                    <label class="grid gap-1">
                                        <span class="text-[11px] font-semibold text-slate-400">Tipo</span>
                                        <select name="data_type" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                            @foreach (['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'url', 'json'] as $type)
                                                <option value="{{ $type }}" @selected(old('data_type', 'string') === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="grid gap-1">
                                        <span class="text-[11px] font-semibold text-slate-400">Ordine</span>
                                        <input type="number" name="sort_order" value="{{ old('sort_order', 100) }}" min="0" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                    </label>
                                </div>

                                <label class="inline-flex items-center gap-2 text-[11px] text-slate-300">
                                    <input type="checkbox" name="is_required" value="1" @checked(old('is_required')) class="rounded border-white/20 bg-slate-900">
                                    richiesto
                                </label>

                                <button class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Crea campo interno</button>
                            </form>
                        </details>

                        <div class="mt-3 space-y-3">
                    @forelse ($internalFields as $field => $info)
                        <div class="rounded-xl bg-slate-950/60 p-3 ring-1 ring-white/10">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-white">{{ $info['label'] ?? $field }}</div>
                                    <code class="text-xs text-slate-400">{{ $field }}</code>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $info['required'] ? 'bg-red-400/15 text-red-200' : 'bg-slate-600 text-slate-200' }}">{{ $info['required'] ? 'richiesto' : 'opzionale' }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-400">{{ $info['description'] }}</p>
                            <form method="POST" action="{{ route('admin.providers.contract-fields.destroy', [$provider->id, $field]) }}" class="mt-3" onsubmit="return confirm('Eliminare questo campo interno dal contratto?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="capability" value="{{ $contractCapability }}">
                                <input type="hidden" name="operation" value="{{ $operation }}">
                                <button class="rounded bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-200 ring-1 ring-red-400/20 hover:bg-red-500/25">Elimina campo interno</button>
                            </form>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-violet-200">Modifica campo</summary>
                                <form method="POST" action="{{ route('admin.providers.contract-fields.update', [$provider->id, $field]) }}" class="mt-3 grid gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="capability" value="{{ $contractCapability }}">
                                    <input type="hidden" name="operation" value="{{ $operation }}">
                                    <input name="label" value="{{ $info['label'] ?? $field }}" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                    <textarea name="description" rows="3" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">{{ $info['description'] }}</textarea>
                                    <div class="grid grid-cols-[minmax(0,1fr)_80px] gap-2">
                                        <select name="data_type" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                            @foreach (['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'url', 'json'] as $type)
                                                <option value="{{ $type }}" @selected(($info['data_type'] ?? 'string') === $type)>{{ $type }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="sort_order" value="{{ $info['sort_order'] ?? 0 }}" min="0" class="w-full min-w-0 rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-[11px] text-slate-300">
                                        <input type="checkbox" name="is_required" value="1" @checked($info['required']) class="rounded border-white/20 bg-slate-900">
                                        richiesto
                                    </label>
                                    <button class="rounded bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-500">Aggiorna campo</button>
                                </form>
                            </details>
                        </div>
                    @empty
                        <div class="rounded-xl bg-slate-950/60 p-3 text-xs leading-5 text-slate-400 ring-1 ring-white/10">
                            Nessun campo registrato per {{ $contractCapability }} · {{ $operation }}. Aggiungi i campi specifici di questa operation prima di salvare il mapping.
                        </div>
                    @endforelse
                        </div>
                    </div>
                @endforeach
            </section>

            <x-fo.panel title="Guida operation" description="Capability e' la famiglia dati. Operation dice che tipo di chiamata stai configurando per quella famiglia.">
                <div class="mt-3 space-y-3">
                    @foreach ($operations as $operation => $label)
                        @php($description = $operationDescriptions[$operation] ?? null)
                        <div class="rounded-xl bg-slate-950/60 p-3 ring-1 ring-white/10">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-white">{{ $label }}</span>
                                <code class="rounded bg-slate-900 px-2 py-0.5 text-[11px] text-slate-300">{{ $operation }}</code>
                            </div>
                            @if ($description)
                                <p class="mt-2 text-xs leading-5 text-slate-400">{{ $description['when'] }}</p>
                                <p class="mt-1 font-mono text-[11px] leading-5 text-slate-300">{{ $description['example'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-fo.panel>

            <section class="rounded-2xl bg-slate-800/70 p-5 text-sm text-slate-200 shadow-lg shadow-black/10">
                <h2 class="font-semibold text-white">Mapping salvati</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($savedEndpoints as $endpoint)
                        <div class="rounded-xl bg-slate-950/70 p-3 ring-1 ring-white/10">
                            @php($endpointStatus = $endpoint->mapping_validation_status ?? $endpoint->validation_status)
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-white">{{ $endpoint->capability }} · {{ $endpoint->operation }}</div>
                                    <div class="font-mono text-xs text-slate-400">{{ $endpoint->method }} {{ $endpoint->endpoint }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $endpointStatus === 'mapping_validated' ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-400/15 text-amber-200' }}">
                                    {{ $endpointStatus }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-2 text-xs">
                                <div><dt class="text-slate-500">Items path</dt><dd class="font-mono">{{ $endpoint->items_path ?: 'root object' }}</dd></div>
                                <div><dt class="text-slate-500">Ultimo status</dt><dd>{{ $endpoint->last_status_code ?? 'non testato' }}</dd></div>
                                <div><dt class="text-slate-500">Campi</dt><dd class="font-mono">{{ implode(', ', array_keys($endpoint->field_mappings_decoded)) ?: 'nessuno' }}</dd></div>
                            </dl>
                            <form method="POST" action="{{ route('admin.providers.http-adapter.destroy', [$provider->id, $endpoint->id]) }}" class="mt-3" onsubmit="return confirm('Eliminare questa configurazione HTTP salvata? Verranno rimossi anche i mapping dei campi collegati.');">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-200 ring-1 ring-red-400/20 hover:bg-red-500/25">Elimina configurazione</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-slate-400">Nessun mapping runtime salvato.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl bg-slate-800/70 p-5 text-sm text-slate-200 shadow-lg shadow-black/10">
                <h2 class="font-semibold text-white">Variabili future</h2>
                <p class="mt-2 leading-5 text-slate-300">Questa prima pagina testa endpoint e mapping. La fase successiva usera' variabili come:</p>
                <pre class="mt-3 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-200">{country_name}
{league_name}
{provider_league_id}
{season_year}</pre>
            </section>

            @if ($testResult)
                <section class="rounded-2xl bg-slate-100 p-5 text-sm text-slate-900 shadow-lg shadow-black/10">
                    <h2 class="font-semibold">Risultato test</h2>
                    <dl class="mt-3 space-y-2">
                        <div><dt class="text-slate-500">Status</dt><dd class="font-semibold {{ $testResult['ok'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $testResult['status'] ?? 'Errore' }}</dd></div>
                        <div><dt class="text-slate-500">URL</dt><dd class="break-all font-mono text-xs">{{ $testResult['resolved_url'] }}</dd></div>
                        @if (! empty($testResult['resolved_query']))
                            <div><dt class="text-slate-500">Query risolta</dt><dd class="break-all font-mono text-xs">{{ http_build_query($testResult['resolved_query']) }}</dd></div>
                        @endif
                        <div><dt class="text-slate-500">Items trovati</dt><dd>{{ $testResult['items_count'] }}</dd></div>
                    </dl>

                    @if (! empty($testResult['error']))
                        <div class="mt-4 rounded-xl bg-red-50 p-3 text-red-800 ring-1 ring-red-200">{{ $testResult['error'] }}</div>
                    @endif

                    @if (! empty($testResult['warning']))
                        <div class="mt-4 rounded-xl bg-amber-50 p-3 text-amber-900 ring-1 ring-amber-200">{{ $testResult['warning'] }}</div>
                    @endif

                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Preview normalizzata</h3>
                    <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($testResult['normalized_preview'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

                    <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Primo item raw</h3>
                    <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($testResult['first_item'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
