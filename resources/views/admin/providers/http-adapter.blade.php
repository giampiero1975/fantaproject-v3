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

            <form method="POST" action="{{ route('admin.providers.http-adapter.test', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Capability</span>
                    <select name="capability" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        @foreach ($capabilities as $capability)
                            <option value="{{ $capability }}" @selected(($testInput['capability'] ?? 'competitions') === $capability)>{{ $capability }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Metodo</span>
                    <select name="method" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300">
                        <option value="GET" @selected(($testInput['method'] ?? 'GET') === 'GET')>GET</option>
                        <option value="POST" @selected(($testInput['method'] ?? 'GET') === 'POST')>POST</option>
                    </select>
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Base URL</span>
                    <input value="{{ $provider->base_url }}" disabled class="w-full rounded-lg bg-slate-200 px-3 py-2 text-slate-700 ring-1 ring-slate-300">
                </label>

                <label class="space-y-1 md:col-span-2">
                    <span class="text-xs font-medium text-slate-700">Endpoint</span>
                    <input name="endpoint" value="{{ $testInput['endpoint'] ?? 'search_all_leagues.php' }}" placeholder="search_all_leagues.php" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300" required>
                    <span class="block text-[11px] text-slate-500">Inserisci un endpoint relativo alla base URL, come in Postman.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Query params</span>
                    <textarea name="query_params" rows="6" placeholder="c=Italy&#10;l=Italian Serie A" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $testInput['query_params'] ?? "c=Italy" }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: una coppia key=value per riga.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Body JSON</span>
                    <textarea name="body_template" rows="6" placeholder='{"country":"Italy"}' class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $testInput['body_template'] ?? '' }}</textarea>
                    <span class="block text-[11px] text-slate-500">Usato solo per POST. Per la prima fase usa GET.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Items path</span>
                    <input name="items_path" value="{{ $testInput['items_path'] ?? 'leagues' }}" placeholder="leagues" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">
                    <span class="block text-[11px] text-slate-500">Percorso dove si trova la lista nel JSON. Esempio: teams, leagues, data.</span>
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium text-slate-700">Field mapping</span>
                    <textarea name="field_mappings" rows="6" class="w-full rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-slate-300">{{ $testInput['field_mappings'] ?? "external_id=idLeague\nname=strLeague\ncountry=strCountry" }}</textarea>
                    <span class="block text-[11px] text-slate-500">Formato: campo_interno=path_payload.</span>
                </label>

                <div class="md:col-span-2">
                    <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Test request</button>
                </div>
            </form>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl bg-slate-800/70 p-5 text-sm text-slate-200 shadow-lg shadow-black/10">
                <h2 class="font-semibold text-white">Provider</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="text-slate-400">Codice</dt><dd class="font-mono">{{ $provider->code }}</dd></div>
                    <div><dt class="text-slate-400">Base URL</dt><dd class="break-all font-mono">{{ $provider->base_url }}</dd></div>
                    <div><dt class="text-slate-400">Stato</dt><dd>{{ $provider->is_enabled ? 'Runtime attivo' : 'Runtime disattivato' }}</dd></div>
                </dl>
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
