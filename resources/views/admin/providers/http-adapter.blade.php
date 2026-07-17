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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <section class="rounded-2xl bg-slate-100 p-5 text-slate-900 shadow-lg shadow-black/10">
            <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-950 ring-1 ring-blue-200">
                <h2 class="font-semibold">Ordine guidato</h2>
                <p class="mt-2 leading-5">Parti da <strong>competitions</strong>. Solo dopo aver collegato la competizione esterna alla lega interna ha senso configurare seasons e teams.</p>
            </div>

            <form method="POST" class="mt-5 grid gap-4 md:grid-cols-2" data-http-adapter-form
                  x-data="{
                      selectedOperation: @js($formInput['operation'] ?? 'list'),
                      operationHelp: @js($operationDescriptions),
                  }">
                @csrf

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Capability</span>
                    <select name="capability" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        @foreach ($capabilities as $capability)
                            <option value="{{ $capability }}" @selected(($formInput['capability'] ?? 'competitions') === $capability)>{{ $capability }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Operation</span>
                    <select name="operation" x-model="selectedOperation" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        @foreach ($operations as $operation => $label)
                            <option value="{{ $operation }}" @selected(($formInput['operation'] ?? 'list') === $operation)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="block text-[11px] text-slate-500">Esempio: lista competizioni o dettaglio singola competizione.</span>
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
                    <input name="endpoint" value="{{ $formInput['endpoint'] ?? '' }}" placeholder="competitions" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300" required>
                    <span class="block text-[11px] text-slate-500">Inserisci un endpoint relativo alla base URL, come in Postman.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Query params</span>
                    <textarea name="query_params" rows="6" placeholder="id=135" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['query_params'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: una coppia key=value per riga.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Body JSON</span>
                    <textarea name="body_template" rows="6" placeholder='{"country":"Italy"}' class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['body_template'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Usato solo per POST. Per la prima fase usa GET.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Items path</span>
                    <input name="items_path" value="{{ $formInput['items_path'] ?? '' }}" placeholder="competitions" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">
                    <span class="block text-[11px] text-slate-500">Percorso dove si trova la lista nel JSON. Esempio: teams, leagues, data.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Field mapping</span>
                    <textarea name="field_mappings" rows="6" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $formInput['field_mappings'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: campo_interno=path_payload.</span>
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
                <h2 class="font-semibold text-white">Campi interni · {{ $contractCapability }}</h2>
                @error('contract_field')
                    <div class="mt-3 rounded-xl bg-red-400/10 p-3 text-xs text-red-200 ring-1 ring-red-400/20">{{ $message }}</div>
                @enderror
                @if (! empty($unknownContractFields))
                    <div class="mt-3 rounded-xl bg-amber-400/10 p-3 text-xs text-amber-100 ring-1 ring-amber-400/20">
                        <div class="font-semibold text-white">Campi nuovi rilevati nel Field mapping</div>
                        <p class="mt-1 text-amber-100/80">Aggiungili al contratto prima di salvare il mapping.</p>
                        <div class="mt-3 space-y-3">
                            @foreach ($unknownContractFields as $field)
                                <form method="POST" action="{{ route('admin.providers.contract-fields.store', $provider->id) }}" class="rounded-lg bg-slate-950/50 p-3">
                                    @csrf
                                    <input type="hidden" name="capability" value="{{ $contractCapability }}">
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
                    <summary class="cursor-pointer text-sm font-semibold text-emerald-200">Aggiungi nuovo campo</summary>
                    <form method="POST" action="{{ route('admin.providers.contract-fields.store', $provider->id) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input type="hidden" name="capability" value="{{ $contractCapability }}">

                        <label class="grid gap-1">
                            <span class="text-[11px] font-semibold text-slate-400">Field key</span>
                            <input name="field_key" value="{{ old('field_key') }}" placeholder="competition_logo_url" class="rounded bg-white px-2 py-1 text-xs text-slate-900 ring-1 ring-slate-300">
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
                    @foreach ($internalFields as $field => $info)
                        <div class="rounded-xl bg-slate-950/60 p-3 ring-1 ring-white/10">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-white">{{ $info['label'] ?? $field }}</div>
                                    <code class="text-xs text-slate-400">{{ $field }}</code>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $info['required'] ? 'bg-red-400/15 text-red-200' : 'bg-slate-600 text-slate-200' }}">{{ $info['required'] ? 'richiesto' : 'opzionale' }}</span>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-slate-400">{{ $info['description'] }}</p>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs font-semibold text-violet-200">Modifica campo</summary>
                                <form method="POST" action="{{ route('admin.providers.contract-fields.update', [$provider->id, $field]) }}" class="mt-3 grid gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="capability" value="{{ $contractCapability }}">
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
                    @endforeach
                </div>
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
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-white">{{ $endpoint->capability }} · {{ $endpoint->operation }}</div>
                                    <div class="font-mono text-xs text-slate-400">{{ $endpoint->method }} {{ $endpoint->endpoint }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $endpoint->is_enabled ? 'bg-emerald-400/15 text-emerald-200' : 'bg-amber-400/15 text-amber-200' }}">
                                    {{ $endpoint->mapping_validation_status ?? $endpoint->validation_status }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-2 text-xs">
                                <div><dt class="text-slate-500">Items path</dt><dd class="font-mono">{{ $endpoint->items_path ?: 'root object' }}</dd></div>
                                <div><dt class="text-slate-500">Ultimo status</dt><dd>{{ $endpoint->last_status_code ?? 'non testato' }}</dd></div>
                                <div><dt class="text-slate-500">Campi</dt><dd class="font-mono">{{ implode(', ', array_keys($endpoint->field_mappings_decoded)) ?: 'nessuno' }}</dd></div>
                            </dl>
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
                        <div><dt class="text-slate-500">Items trovati</dt><dd>{{ $testResult['items_count'] }}</dd></div>
                    </dl>

                    @if (! empty($testResult['error']))
                        <div class="mt-4 rounded-xl bg-red-50 p-3 text-red-800 ring-1 ring-red-200">{{ $testResult['error'] }}</div>
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
