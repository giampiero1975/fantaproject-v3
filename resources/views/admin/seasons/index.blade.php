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
                <div><strong class="text-white">2. Controlla capability</strong><p class="mt-1 text-slate-400">Verifica quali provider coprono competizioni, stagioni e squadre.</p></div>
                <div><strong class="text-white">3. Analizza</strong><p class="mt-1 text-slate-400">Esegue dry-run usando i riferimenti provider salvati.</p></div>
                <div><strong class="text-white">4. Applica</strong><p class="mt-1 text-slate-400">Scrive solo dopo conferma esplicita APPLICA.</p></div>
            </div>

            <div class="mt-5 rounded-xl border border-sky-300/20 bg-sky-400/5 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-sky-100">Capability richieste da Gestione Stagioni</h3>
                        <p class="mt-1 text-xs text-slate-400">La pagina usa solo configurazioni HTTP salvate nel DB. Un provider può essere attivo ma non pronto per tutte le capability.</p>
                    </div>
                    <div class="flex flex-wrap gap-2" data-season-required-capabilities>
                        @foreach($requiredCapabilities as $capability)
                            <span class="inline-flex rounded-full bg-slate-800 px-2.5 py-1 text-xs font-semibold text-slate-100 ring-1 ring-white/10">{{ $capability }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <x-fo.panel
            title="Copertura timeline stagioni"
            description="Visione DB-only: la copertura dipende dai provider collegati alle stagioni; le date sono opzionali e restano diagnostiche."
            data-season-timeline-coverage
        >
            <div class="flex flex-wrap items-center justify-end gap-2">
                <span class="rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-200 ring-1 ring-white/10">
                    {{ $timelineCoverage->coverage_percent }}% stagioni coperte
                </span>
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra copertura timeline" aria-label="Filtra copertura timeline stagioni">
                        <flux:icon name="funnel" class="size-5" />
                    </summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(34rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span>
                                <select data-season-coverage-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le nazioni</option>
                                    @foreach($timelineCoverage->leagues->whereNotNull('country_name')->unique('country_name')->sortBy('country_name') as $coverageCountry)
                                        <option value="{{ \Illuminate\Support\Str::lower($coverageCountry->country_name) }}">{{ $coverageCountry->country_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span>
                                <select data-season-coverage-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le competizioni</option>
                                    @foreach($timelineCoverage->leagues->sortBy('league_name') as $coverageLeague)
                                        <option value="{{ \Illuminate\Support\Str::lower($coverageLeague->league_name) }}">{{ $coverageLeague->league_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 sm:col-span-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stato timeline</span>
                                <select data-season-coverage-status-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutti gli stati</option>
                                    <option value="complete">Coperta</option>
                                    <option value="partial">Parziale</option>
                                    <option value="ready">Da generare</option>
                                    <option value="empty">Assente</option>
                                </select>
                            </label>
                        </div>
                        <button type="button" data-season-coverage-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <x-fo.stat label="Competizioni" :value="$timelineCoverage->league_count" icon="queue-list" tone="blue" />
                <x-fo.stat label="Complete" :value="$timelineCoverage->complete_leagues" icon="check-circle" tone="green" />
                <x-fo.stat label="Parziali" :value="$timelineCoverage->partial_leagues" icon="exclamation-triangle" tone="amber" />
                <x-fo.stat label="Da generare" :value="$timelineCoverage->ready_leagues" icon="arrow-path" tone="blue" />
                <x-fo.stat label="Senza timeline" :value="$timelineCoverage->empty_leagues" icon="minus-circle" tone="purple" />
                <x-fo.stat
                    label="Stagioni coperte"
                    :value="$timelineCoverage->covered_seasons.' / '.$timelineCoverage->season_count"
                    :hint="$timelineCoverage->missing_provider_mapped > 0 ? $timelineCoverage->missing_provider_mapped.' senza provider timeline' : null"
                    icon="calendar-days"
                    tone="blue"
                />
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Nazione</th>
                            <th class="pb-3">Competizione</th>
                            <th class="pb-3">Stato timeline</th>
                            <th class="pb-3">Current</th>
                            <th class="pb-3">Provider attivi</th>
                            <th class="pb-3">Stagioni coperte</th>
                            <th class="pb-3">Mancanti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($timelineCoverage->leagues as $coverage)
                            @php
                                $statusClass = match ($coverage->status) {
                                    'complete' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
                                    'partial' => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
                                    'ready' => 'bg-sky-400/15 text-sky-200 ring-sky-300/20',
                                    default => 'bg-slate-700/70 text-slate-300 ring-slate-500/20',
                                };
                                $statusLabel = match ($coverage->status) {
                                    'complete' => 'coperta',
                                    'partial' => 'parziale',
                                    'ready' => 'da generare',
                                    default => 'assente',
                                };
                            @endphp
                            <tr
                                data-season-coverage-row
                                data-coverage-country="{{ \Illuminate\Support\Str::lower($coverage->country_name ?? '') }}"
                                data-coverage-competition="{{ \Illuminate\Support\Str::lower($coverage->league_name) }}"
                                data-coverage-status="{{ $coverage->status }}"
                            >
                                <td class="py-3 text-slate-400">{{ $coverage->country_name ?? '—' }}</td>
                                <td class="py-3 text-white">{{ $coverage->league_name }}</td>
                                <td class="py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="py-3 text-slate-300">{{ $coverage->current_season_label ?? '—' }}</td>
                                <td class="py-3 text-slate-300">{{ $coverage->ready_season_providers }}</td>
                                <td class="py-3 text-slate-300">{{ $coverage->active_provider_mapped }} / {{ $coverage->season_count }}</td>
                                <td class="py-3 text-slate-400">
                                    @if(empty($coverage->uncovered_season_labels))
                                        —
                                    @else
                                        {{ implode(', ', $coverage->uncovered_season_labels) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr data-season-coverage-empty class="{{ $timelineCoverage->leagues->isEmpty() ? '' : 'hidden' }}"><td colspan="7" class="py-5 text-center text-slate-500">Nessuna copertura timeline per i filtri selezionati.</td></tr>
                    </tbody>
                </table>
            </div>
        </x-fo.panel>

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
                <div class="flex flex-wrap items-center gap-2">
                    <details class="relative">
                        <summary class="cursor-pointer list-none rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-500 [&::-webkit-details-marker]:hidden" data-season-provider-mapping-trigger>
                            Collega provider
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 rounded-xl bg-slate-100 p-4 text-slate-900 shadow-xl ring-1 ring-slate-300" style="width: min(42rem, calc(100vw - 2rem));">
                            <h3 class="text-sm font-semibold text-slate-950">Collega competizione interna a provider</h3>
                            <p class="mt-1 text-xs text-slate-600">Salva il codice esterno in league_provider_mappings. Esempio Football-Data: Serie A = SA.</p>
                            <form method="POST" action="{{ route('admin.seasons.provider-mappings.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2" data-season-provider-mapping-form>
                                @csrf
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione interna</span>
                                    <select name="league_id" class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300" required>
                                        <option value="">Seleziona...</option>
                                        @foreach($internalLeagues as $leagueOption)
                                            <option value="{{ $leagueOption->id }}" @selected((string) old('league_id') === (string) $leagueOption->id)>{{ $leagueOption->country_name ? $leagueOption->country_name.' · ' : '' }}{{ $leagueOption->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Provider</span>
                                    <select name="data_provider_id" class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300" required>
                                        <option value="">Seleziona...</option>
                                        @foreach($mappableProviders as $providerOption)
                                            <option value="{{ $providerOption->id }}" @selected((string) old('data_provider_id') === (string) $providerOption->id)>{{ $providerOption->name }} · {{ $providerOption->code }}{{ $providerOption->competitions_ready ? '' : ' · mapping da validare' }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Codice provider</span>
                                    <input name="external_id" value="{{ old('external_id') }}" placeholder="SA" class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400" required>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nome provider</span>
                                    <input name="external_name" value="{{ old('external_name') }}" placeholder="Serie A" class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400" required>
                                </label>
                                <label class="space-y-1 sm:col-span-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione provider</span>
                                    <input name="external_country" value="{{ old('external_country') }}" placeholder="Italy" class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400">
                                </label>
                                <div class="sm:col-span-2">
                                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Salva collegamento</button>
                                </div>
                            </form>
                        </div>
                    </details>

                    <details class="relative">
                        <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra registry" aria-label="Filtra registry competizioni e provider">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4.5h18l-7 8v5.25l-4 1.75v-7L3 4.5Z" /></svg>
                        </summary>
                        <div class="absolute right-0 z-20 mt-2 rounded-xl bg-slate-100 p-4 text-slate-900 shadow-xl ring-1 ring-slate-300" style="width: min(42rem, calc(100vw - 2rem));">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span>
                                    <select data-season-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                        <option value="">Tutte le nazioni</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->country_id }}">{{ $country->country_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span>
                                    <select data-season-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                        <option value="">Tutte le competizioni</option>
                                        @foreach($leagues->sortBy('name') as $league)
                                            <option value="{{ \Illuminate\Support\Str::lower($league->name) }}">{{ $league->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Provider</span>
                                    <select data-season-provider-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                        <option value="">Tutti i provider</option>
                                        @foreach($leagues->flatMap(fn ($league) => $league->providers)->unique('provider_name')->sortBy('provider_name') as $provider)
                                            <option value="{{ \Illuminate\Support\Str::lower($provider->provider_name) }}">{{ $provider->provider_name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Mapping</span>
                                    <input data-season-mapping-filter type="search" placeholder="es. SA, 135..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400">
                                </label>
                                <label class="space-y-1 sm:col-span-2">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Capability</span>
                                    <select data-season-capability-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                        <option value="">Tutte le capability</option>
                                        @foreach($requiredCapabilities as $capability)
                                            <option value="{{ $capability }}">{{ $capability }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <button type="button" data-season-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                        </div>
                    </details>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500"><tr><th class="pb-3">Nazione</th><th class="pb-3">Competizione</th><th class="pb-3">Provider</th><th class="pb-3">Mapping</th><th class="pb-3">Capability</th><th class="pb-3">Ruolo</th><th class="pb-3">Stato</th><th class="pb-3">Piano</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($leagues as $league)
                            @foreach($league->providers as $provider)
                                <tr
                                    data-season-registry-row
                                    data-country-id="{{ $league->country_id }}"
                                    data-competition="{{ \Illuminate\Support\Str::lower($league->name) }}"
                                    data-provider="{{ \Illuminate\Support\Str::lower($provider->provider_name) }}"
                                    data-mapping="{{ \Illuminate\Support\Str::lower($provider->external_id) }}"
                                    data-capabilities="{{ collect($provider->capabilities)->filter(fn ($status) => $status['configured'])->keys()->implode(' ') }}"
                                >
                                    <td class="py-3 text-slate-400">{{ $league->country_name ?? '—' }}</td>
                                    <td class="py-3 text-white">{{ $league->name }}</td>
                                    <td class="py-3 text-slate-300">{{ $provider->provider_name }}</td>
                                    <td class="py-3 font-mono text-slate-300">{{ $provider->external_id }}</td>
                                    <td class="py-3">
                                        <div class="flex flex-wrap gap-1.5" data-season-provider-capabilities>
                                            @foreach($provider->capabilities as $capability => $status)
                                                @php
                                                    $badgeClass = $status['ready']
                                                        ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20'
                                                        : ($status['configured'] ? 'bg-amber-400/15 text-amber-200 ring-amber-300/20' : 'bg-slate-700/70 text-slate-300 ring-slate-500/20');
                                                    $label = $status['ready'] ? 'pronta' : ($status['configured'] ? 'da validare' : 'manca');
                                                    $title = $status['operations'] === []
                                                        ? "{$capability}: nessuna operation configurata"
                                                        : "{$capability}: ".implode(', ', $status['operations']);
                                                @endphp
                                                <span title="{{ $title }}" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $badgeClass }}">
                                                    <span>{{ $capability }}</span>
                                                    <span class="opacity-80">{{ $label }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="py-3 text-slate-400">{{ $provider->role ?? '—' }}</td>
                                    <td class="py-3 {{ $provider->is_enabled ? 'text-emerald-300' : 'text-slate-500' }}">{{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}</td>
                                    <td class="py-3 text-slate-400">{{ $provider->plan ?? '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr data-season-registry-empty class="hidden"><td colspan="8" class="py-5 text-center text-slate-500">Nessuna competizione per la nazione selezionata.</td></tr>
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
                    <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Stagione</th><th class="pb-3">Current</th><th class="pb-3">Inizio</th><th class="pb-3">Fine</th><th class="pb-3">Azione</th><th class="pb-3">Copertura</th><th class="pb-3">Provider</th></tr></thead><tbody class="divide-y divide-white/5">@foreach($lastReportData['timeline'] as $row)@php($candidateProviders = collect($row['providers'] ?? [])->reject(fn ($provider) => ($provider['reason'] ?? null) === 'missing_provider_reference'))@php($availableProviders = $candidateProviders->where('available', true)->count())@php($coverageLabel = $candidateProviders->isEmpty() || $availableProviders === 0 ? 'scoperta' : ($availableProviders === $candidateProviders->count() ? 'coperta' : 'parziale'))@php($coverageClass = $coverageLabel === 'coperta' ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20' : ($coverageLabel === 'parziale' ? 'bg-amber-400/15 text-amber-200 ring-amber-300/20' : 'bg-rose-400/15 text-rose-200 ring-rose-300/20'))<tr><td class="py-3 text-white">{{ $row['label'] }}</td><td class="py-3 {{ $row['is_current'] ? 'text-emerald-300' : 'text-slate-400' }}">{{ $row['is_current'] ? 'Sì' : 'No' }}</td><td class="py-3 text-slate-300">{{ $row['start_date'] ?? '—' }}</td><td class="py-3 text-slate-300">{{ $row['end_date'] ?? '—' }}</td><td class="py-3 font-medium text-violet-200">{{ $row['action'] }}</td><td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $coverageClass }}">{{ $coverageLabel }}</span></td><td class="py-3 text-slate-300">{{ $availableProviders }} / {{ $candidateProviders->count() }}</td></tr>@endforeach</tbody></table></div>
                @endif

                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastReport }}</pre></details>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-season-management]');
            if (!root) return;

            const countryFilter = root.querySelector('[data-season-country-filter]');
            const competitionFilter = root.querySelector('[data-season-competition-filter]');
            const providerFilter = root.querySelector('[data-season-provider-filter]');
            const mappingFilter = root.querySelector('[data-season-mapping-filter]');
            const capabilityFilter = root.querySelector('[data-season-capability-filter]');
            const resetFilter = root.querySelector('[data-season-filter-reset]');
            const leagueSelect = root.querySelector('[data-season-league-select]');
            const leagueOptions = Array.from(leagueSelect?.querySelectorAll('option[data-country-id]') ?? []);
            const registryRows = Array.from(root.querySelectorAll('[data-season-registry-row]'));
            const leagueEmpty = root.querySelector('[data-season-filter-empty]');
            const registryEmpty = root.querySelector('[data-season-registry-empty]');
            const storageKey = 'fanta-oracle:season-registry-filters';
            const coverageCountryFilter = root.querySelector('[data-season-coverage-country-filter]');
            const coverageCompetitionFilter = root.querySelector('[data-season-coverage-competition-filter]');
            const coverageStatusFilter = root.querySelector('[data-season-coverage-status-filter]');
            const coverageResetFilter = root.querySelector('[data-season-coverage-filter-reset]');
            const coverageRows = Array.from(root.querySelectorAll('[data-season-coverage-row]'));
            const coverageEmpty = root.querySelector('[data-season-coverage-empty]');
            const coverageStorageKey = 'fanta-oracle:season-coverage-filters';

            const readFilters = () => ({
                country: countryFilter?.value ?? '',
                competition: competitionFilter?.value ?? '',
                provider: providerFilter?.value ?? '',
                mapping: mappingFilter?.value ?? '',
                capability: capabilityFilter?.value ?? '',
            });

            const writeFilters = () => {
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(readFilters()));
                } catch (_) {
                }
            };

            const restoreFilters = () => {
                try {
                    const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}');
                    if (countryFilter && typeof saved.country === 'string') countryFilter.value = saved.country;
                    if (competitionFilter && typeof saved.competition === 'string') competitionFilter.value = saved.competition;
                    if (providerFilter && typeof saved.provider === 'string') providerFilter.value = saved.provider;
                    if (mappingFilter && typeof saved.mapping === 'string') mappingFilter.value = saved.mapping;
                    if (capabilityFilter && typeof saved.capability === 'string') capabilityFilter.value = saved.capability;
                } catch (_) {
                }
            };

            const applyFilter = (persist = true) => {
                const countryId = countryFilter?.value ?? '';
                const competition = competitionFilter?.value ?? '';
                const provider = providerFilter?.value ?? '';
                const mapping = (mappingFilter?.value ?? '').trim().toLowerCase();
                const capability = capabilityFilter?.value ?? '';
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
                    const show = (countryId === '' || row.dataset.countryId === countryId)
                        && (competition === '' || row.dataset.competition === competition)
                        && (provider === '' || row.dataset.provider === provider)
                        && (mapping === '' || (row.dataset.mapping ?? '').includes(mapping))
                        && (capability === '' || (row.dataset.capabilities ?? '').split(' ').includes(capability));
                    row.classList.toggle('hidden', !show);
                    if (show) visibleRows++;
                });

                leagueEmpty?.classList.toggle('hidden', visibleLeagues !== 0);
                registryEmpty?.classList.toggle('hidden', visibleRows !== 0);

                if (persist) {
                    writeFilters();
                }
            };

            countryFilter?.addEventListener('change', applyFilter);
            competitionFilter?.addEventListener('change', applyFilter);
            providerFilter?.addEventListener('change', applyFilter);
            mappingFilter?.addEventListener('input', applyFilter);
            capabilityFilter?.addEventListener('change', applyFilter);
            resetFilter?.addEventListener('click', () => {
                if (countryFilter) countryFilter.value = '';
                if (competitionFilter) competitionFilter.value = '';
                if (providerFilter) providerFilter.value = '';
                if (mappingFilter) mappingFilter.value = '';
                if (capabilityFilter) capabilityFilter.value = '';
                try {
                    window.localStorage.removeItem(storageKey);
                } catch (_) {
                }
                applyFilter();
            });
            restoreFilters();
            applyFilter(false);

            const readCoverageFilters = () => ({
                country: coverageCountryFilter?.value ?? '',
                competition: coverageCompetitionFilter?.value ?? '',
                status: coverageStatusFilter?.value ?? '',
            });

            const writeCoverageFilters = () => {
                try {
                    window.localStorage.setItem(coverageStorageKey, JSON.stringify(readCoverageFilters()));
                } catch (_) {
                }
            };

            const restoreCoverageFilters = () => {
                try {
                    const saved = JSON.parse(window.localStorage.getItem(coverageStorageKey) ?? '{}');
                    if (coverageCountryFilter && typeof saved.country === 'string') coverageCountryFilter.value = saved.country;
                    if (coverageCompetitionFilter && typeof saved.competition === 'string') coverageCompetitionFilter.value = saved.competition;
                    if (coverageStatusFilter && typeof saved.status === 'string') coverageStatusFilter.value = saved.status;
                } catch (_) {
                }
            };

            const applyCoverageFilter = (persist = true) => {
                const country = coverageCountryFilter?.value ?? '';
                const competition = coverageCompetitionFilter?.value ?? '';
                const status = coverageStatusFilter?.value ?? '';
                let visibleRows = 0;

                coverageRows.forEach((row) => {
                    const show = (country === '' || row.dataset.coverageCountry === country)
                        && (competition === '' || row.dataset.coverageCompetition === competition)
                        && (status === '' || row.dataset.coverageStatus === status);
                    row.classList.toggle('hidden', !show);
                    if (show) visibleRows++;
                });

                coverageEmpty?.classList.toggle('hidden', visibleRows !== 0);

                if (persist) {
                    writeCoverageFilters();
                }
            };

            coverageCountryFilter?.addEventListener('change', applyCoverageFilter);
            coverageCompetitionFilter?.addEventListener('change', applyCoverageFilter);
            coverageStatusFilter?.addEventListener('change', applyCoverageFilter);
            coverageResetFilter?.addEventListener('click', () => {
                if (coverageCountryFilter) coverageCountryFilter.value = '';
                if (coverageCompetitionFilter) coverageCompetitionFilter.value = '';
                if (coverageStatusFilter) coverageStatusFilter.value = '';
                try {
                    window.localStorage.removeItem(coverageStorageKey);
                } catch (_) {
                }
                applyCoverageFilter();
            });
            restoreCoverageFilters();
            applyCoverageFilter(false);
        })();
    </script>
</x-app-layout>
