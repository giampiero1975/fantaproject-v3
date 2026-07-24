<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Tier Squadre</h1>
            <p class="mt-1 text-sm text-slate-400">Step 4 · calcolo tier globale e stagionale dalle classifiche canoniche.</p>
        </div>
    </x-slot>

    <div class="space-y-6" data-tier-management>
        @if (session('status'))<div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Guida Step 4</h2>
                    <p class="mt-1 text-sm text-slate-400">Porting V2 del motore Gold Standard: usa solo classifiche gia normalizzate nel nostro layer, senza scraping e senza provider visibili in uscita.</p>
                </div>
                <a href="{{ route('admin.standings.index') }}" class="rounded-xl border border-violet-400/30 bg-violet-400/10 px-4 py-2 text-sm text-violet-100">Vai a Classifiche</a>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm">
                <div><strong class="text-white">1. Prerequisito</strong><p class="mt-1 text-slate-400">Servono squadre e classifiche storiche per la lega/stagione scelta.</p></div>
                <div><strong class="text-white">2. Analizza</strong><p class="mt-1 text-slate-400">Il dry-run calcola tier e score senza scrivere.</p></div>
                <div><strong class="text-white">3. Applica</strong><p class="mt-1 text-slate-400">Scrive tier globale squadra e tier stagionale nella pivot.</p></div>
                <div><strong class="text-white">4. Regole</strong><p class="mt-1 text-slate-400">Pesi, soglie e moltiplicatori sono in tabella team_tier_settings.</p></div>
            </div>
        </section>

        <x-fo.panel title="Copertura tier" description="Una stagione e coperta quando tutte le squadre attive hanno tier_stagionale valorizzato." data-tier-coverage>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra tier" aria-label="Filtra copertura tier">
                        <flux:icon name="funnel" class="size-5" />
                    </summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(42rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ricerca rapida</span><input data-tier-search-filter type="search" placeholder="Cerca competizione o stagione..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400"></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span><select data-tier-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte</option>@foreach($tierCoverage->rows->whereNotNull('country_name')->unique('country_name')->sortBy('country_name') as $country)<option value="{{ \Illuminate\Support\Str::lower($country->country_name) }}">{{ $country->country_name }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span><select data-tier-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte</option>@foreach($tierCoverage->rows->unique('league_name')->sortBy('league_name') as $league)<option value="{{ \Illuminate\Support\Str::lower($league->league_name) }}">{{ $league->league_name }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stagione</span><select data-tier-season-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte</option>@foreach($tierCoverage->rows->unique('season_label')->sortByDesc('season_key') as $season)<option value="{{ \Illuminate\Support\Str::lower($season->season_label) }}">{{ $season->season_label }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stato</span><select data-tier-status-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutti</option><option value="covered">Coperta</option><option value="partial">Parziale</option><option value="missing">Mancante</option><option value="missing_teams">Senza squadre</option></select></label>
                        </div>
                        <button type="button" data-tier-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                <x-fo.stat label="Coperte" :value="$tierCoverage->covered" icon="check-circle" tone="green" />
                <x-fo.stat label="Parziali" :value="$tierCoverage->partial" icon="exclamation-triangle" tone="amber" />
                <x-fo.stat label="Da calcolare" :value="$tierCoverage->missing" icon="minus-circle" tone="blue" />
                <x-fo.stat label="Tier assegnati" :value="$tierCoverage->tier_count" icon="chart-bar" tone="violet" />
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-3">
                <form method="POST" action="{{ route('admin.team-tiers.analyze') }}" class="rounded-xl border border-white/10 bg-black/20 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-white">Analizza tier squadra</h3>
                    <p class="mt-1 text-xs text-slate-400">Calcola tier e score dal dataset interno. Nessuna chiamata provider viene esposta qui.</p>
                    <div class="mt-4 grid gap-3">
                        <label class="space-y-2"><span class="text-sm font-medium text-slate-300">Lega + stagione</span><select name="league_season_id" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><option value="">Seleziona...</option>@foreach($leagueSeasonOptions as $option)<option value="{{ $option->id }}" @selected((string) old('league_season_id', $lastTierParameters['league_season_id'] ?? '') === (string) $option->id)>{{ $option->country_name ? $option->country_name.' · ' : '' }}{{ $option->league_name }} · {{ $option->season_label }}{{ $option->is_current ? ' · current' : '' }}</option>@endforeach</select></label>
                        <button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Analizza tier</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.team-tiers.audit-performance') }}" class="rounded-xl border border-white/10 bg-black/20 p-4">
                    @csrf
                    <h3 class="text-sm font-semibold text-white">Audit prestazione reale</h3>
                    <p class="mt-1 text-xs text-slate-400">Confronta il valore tier calcolato con lo score reale della classifica finale.</p>
                    <div class="mt-4 grid gap-3">
                        <label class="space-y-2"><span class="text-sm font-medium text-slate-300">Lega + stagione</span><select name="league_season_id" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><option value="">Seleziona...</option>@foreach($leagueSeasonOptions as $option)<option value="{{ $option->id }}" @selected((string) old('league_season_id', $lastTierPerformanceParameters['league_season_id'] ?? '') === (string) $option->id)>{{ $option->country_name ? $option->country_name.' · ' : '' }}{{ $option->league_name }} · {{ $option->season_label }}{{ $option->is_current ? ' · current' : '' }}</option>@endforeach</select></label>
                        <button class="rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-sm font-semibold text-emerald-100 hover:bg-emerald-400/15">Esegui audit</button>
                    </div>
                </form>
                <div class="rounded-xl border border-sky-300/20 bg-sky-400/5 p-4 text-sm">
                    <h3 class="font-semibold text-sky-100">Dove scrive</h3>
                    <p class="mt-2 text-slate-400">Aggiorna <span class="font-mono text-slate-200">league_season_teams.tier_stagionale</span> e <span class="font-mono text-slate-200">league_season_teams.tier_score</span>. Il globale in <span class="font-mono text-slate-200">teams</span> viene aggiornato solo sulla stagione corrente.</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500"><tr><th class="pb-3">Nazione</th><th class="pb-3">Competizione</th><th class="pb-3">Stagione</th><th class="pb-3">Stato tier</th><th class="pb-3">Squadre</th><th class="pb-3">Tier</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($tierCoverage->rows as $row)
                            @php
                                $statusClass = match ($row->status) {
                                    'covered' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
                                    'partial' => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
                                    default => 'bg-slate-400/10 text-slate-300 ring-slate-300/20',
                                };
                                $statusLabel = match ($row->status) {
                                    'covered' => 'coperta',
                                    'partial' => 'parziale',
                                    'missing_teams' => 'senza squadre',
                                    default => 'mancante',
                                };
                            @endphp
                            <tr data-tier-coverage-row data-tier-country="{{ \Illuminate\Support\Str::lower($row->country_name ?? '') }}" data-tier-competition="{{ \Illuminate\Support\Str::lower($row->league_name) }}" data-tier-season="{{ \Illuminate\Support\Str::lower($row->season_label) }}" data-tier-status="{{ $row->status }}">
                                <td class="py-3 text-slate-400">{{ $row->country_name ?? '—' }}</td>
                                <td class="py-3 text-white">{{ $row->league_name }}</td>
                                <td class="py-3 text-slate-300">{{ $row->season_label }}{{ $row->is_current ? ' · current' : '' }}</td>
                                <td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td class="py-3 text-slate-300">{{ $row->team_count }}</td>
                                <td class="py-3 text-slate-300">{{ $row->tier_count }} / {{ $row->team_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-5 text-center text-slate-500">Nessuna squadra sincronizzata: completa prima Squadre e Classifiche.</td></tr>
                        @endforelse
                        <tr data-tier-coverage-empty class="hidden"><td colspan="6" class="py-5 text-center text-slate-500">Nessuna riga per i filtri selezionati.</td></tr>
                    </tbody>
                </table>
            </div>
        </x-fo.panel>

        <x-fo.panel title="Registry tier squadre" description="Elenco dei tier gia calcolati nel dataset canonico." data-tier-registry>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <x-fo.stat label="Righe tier" :value="$tierRegistry->total" icon="queue-list" tone="blue" />
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra registry tier" aria-label="Filtra registry tier"><flux:icon name="funnel" class="size-5" /></summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(42rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ricerca rapida</span><input data-tier-registry-search-filter type="search" placeholder="Cerca squadra, competizione o stagione..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400"></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stagione</span><select data-tier-registry-season-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte</option>@foreach($tierRegistry->rows->unique('season_label')->sortByDesc('season_key') as $season)<option value="{{ \Illuminate\Support\Str::lower($season->season_label) }}">{{ $season->season_label }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Tier</span><select data-tier-registry-tier-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutti</option>@foreach([1,2,3,4,5] as $tier)<option value="{{ $tier }}">Tier {{ $tier }}</option>@endforeach</select></label>
                        </div>
                        <button type="button" data-tier-registry-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>
            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500"><tr><th class="pb-3">Squadra</th><th class="pb-3">Competizione</th><th class="pb-3">Stagione</th><th class="pb-3">Valore calcolato</th><th class="pb-3">Tier globale</th><th class="pb-3">Tier stagione</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($tierRegistry->rows as $team)
                            <tr data-tier-registry-row data-tier-registry-season="{{ \Illuminate\Support\Str::lower($team->season_label) }}" data-tier-registry-tier="{{ $team->tier_stagionale }}" data-tier-registry-search="{{ \Illuminate\Support\Str::lower(trim($team->team_name.' '.($team->code ?? '').' '.$team->league_name.' '.$team->season_label)) }}">
                                <td class="py-3"><div class="flex items-center gap-3">@if($team->crest_url)<img src="{{ $team->crest_url }}" alt="" class="size-8 rounded-full bg-white object-contain p-1">@else<span class="flex size-8 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-slate-300">{{ \Illuminate\Support\Str::of($team->team_name)->substr(0, 2)->upper() }}</span>@endif<div><div class="font-semibold text-white">{{ $team->team_name }}</div><div class="text-xs text-slate-500">{{ $team->code ?? '—' }}</div></div></div></td>
                                <td class="py-3 text-slate-300">{{ $team->country_name ? $team->country_name.' · ' : '' }}{{ $team->league_name }}</td>
                                <td class="py-3 text-slate-300">{{ $team->season_label }}{{ $team->is_current ? ' · current' : '' }}</td>
                                <td class="py-3 font-mono text-slate-200">{{ $team->tier_score ?? '—' }}</td>
                                <td class="py-3 text-slate-300">{{ $team->tier_globale ?? '—' }}</td>
                                <td class="py-3"><span class="inline-flex rounded-full bg-violet-400/15 px-2.5 py-1 text-xs font-semibold text-violet-100 ring-1 ring-violet-300/20">Tier {{ $team->tier_stagionale }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-5 text-center text-slate-500">Nessun tier calcolato. Esegui prima il dry-run e poi applica.</td></tr>
                        @endforelse
                        <tr data-tier-registry-empty class="hidden"><td colspan="6" class="py-5 text-center text-slate-500">Nessuna squadra per i filtri selezionati.</td></tr>
                    </tbody>
                </table>
            </div>
        </x-fo.panel>

        @if($lastTierReport)
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Ultimo report tier</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $lastTierMode === 'apply' ? 'APPLY' : 'DRY-RUN' }} · Exit code {{ $lastTierExitCode }} · League season {{ $lastTierParameters['league_season_id'] ?? '—' }}</p>
                    </div>
                    @if($lastTierMode !== 'apply' && (int)$lastTierExitCode === 0)
                        <form method="POST" action="{{ route('admin.team-tiers.apply') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="league_season_id" value="{{ $lastTierParameters['league_season_id'] ?? '' }}">
                            <label class="space-y-1"><span class="block text-xs text-amber-200">Digita CALCOLA</span><input name="confirmation" class="w-40 rounded-xl border border-amber-400/20 bg-black/20 px-3 py-2 text-white" required></label>
                            <button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100">Applica tier</button>
                        </form>
                    @endif
                </div>
                @if($lastTierReportData && !empty($lastTierReportData['rows']))
                    <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Squadra</th><th class="pb-3">Valore calcolato</th><th class="pb-3">Tier</th><th class="pb-3">Storico</th><th class="pb-3">Momentum</th><th class="pb-3">Azione</th></tr></thead><tbody class="divide-y divide-white/5">@foreach($lastTierReportData['rows'] as $row)<tr><td class="py-3 text-white">{{ $row['team_name'] }}</td><td class="py-3 font-mono text-slate-200">{{ $row['score'] }}</td><td class="py-3 text-violet-100">{{ $row['tier'] }}</td><td class="py-3 text-slate-300">{{ $row['historical_component'] }}</td><td class="py-3 text-slate-300">{{ $row['momentum_component'] }}</td><td class="py-3 font-medium text-violet-200">{{ $row['action'] }}</td></tr>@endforeach</tbody></table></div>
                @endif
                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastTierReport }}</pre></details>
            </section>
        @endif

        @if($lastTierPerformanceReport)
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <div>
                    <h2 class="text-lg font-semibold text-white">Audit prestazione reale</h2>
                    <p class="mt-1 text-sm text-slate-400">Exit code {{ $lastTierPerformanceExitCode }} · League season {{ $lastTierPerformanceParameters['league_season_id'] ?? '—' }}</p>
                </div>
                @if($lastTierPerformanceReportData && !empty($lastTierPerformanceReportData['rows']))
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-slate-500"><tr><th class="pb-3">Squadra</th><th class="pb-3">Atteso</th><th class="pb-3">Reale</th><th class="pb-3">Delta</th><th class="pb-3">Classifica</th><th class="pb-3">Esito</th></tr></thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($lastTierPerformanceReportData['rows'] as $row)
                                    @php
                                        $tone = match ($row['status']) {
                                            'overperformed' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
                                            'underperformed' => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
                                            'aligned' => 'bg-sky-400/15 text-sky-200 ring-sky-300/20',
                                            default => 'bg-slate-400/10 text-slate-300 ring-slate-300/20',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="py-3 text-white">{{ $row['team_name'] }}</td>
                                        <td class="py-3 text-slate-300">Tier {{ $row['expected_tier'] ?? '—' }} · <span class="font-mono">{{ $row['expected_score'] ?? '—' }}</span></td>
                                        <td class="py-3 text-slate-300">Tier {{ $row['actual_tier'] ?? '—' }} · <span class="font-mono">{{ $row['actual_score'] ?? '—' }}</span></td>
                                        <td class="py-3 font-mono text-slate-200">{{ $row['score_delta'] ?? '—' }}</td>
                                        <td class="py-3 text-slate-300">{{ $row['position'] ?? '—' }} · {{ $row['points'] ?? '—' }} pt</td>
                                        <td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $tone }}">{{ str_replace('_', ' ', $row['status']) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastTierPerformanceReport }}</pre></details>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-tier-management]');
            if (!root) return;
            const bind = (prefix) => {
                const search = root.querySelector(`[data-${prefix}-search-filter]`);
                const country = root.querySelector(`[data-${prefix}-country-filter]`);
                const competition = root.querySelector(`[data-${prefix}-competition-filter]`);
                const season = root.querySelector(`[data-${prefix}-season-filter]`);
                const status = root.querySelector(`[data-${prefix}-status-filter]`);
                const tier = root.querySelector(`[data-${prefix}-tier-filter]`);
                const reset = root.querySelector(`[data-${prefix}-filter-reset]`);
                const rows = Array.from(root.querySelectorAll(`[data-${prefix}-row]`));
                const empty = root.querySelector(`[data-${prefix}-empty]`);
                const key = `fanta-oracle:${prefix}-filters`;
                const read = () => ({search: search?.value ?? '', country: country?.value ?? '', competition: competition?.value ?? '', season: season?.value ?? '', status: status?.value ?? '', tier: tier?.value ?? ''});
                const save = () => { try { window.localStorage.setItem(key, JSON.stringify(read())); } catch (_) {} };
                const restore = () => { try { const saved = JSON.parse(window.localStorage.getItem(key) ?? '{}'); if (search && typeof saved.search === 'string') search.value = saved.search; if (country && typeof saved.country === 'string') country.value = saved.country; if (competition && typeof saved.competition === 'string') competition.value = saved.competition; if (season && typeof saved.season === 'string') season.value = saved.season; if (status && typeof saved.status === 'string') status.value = saved.status; if (tier && typeof saved.tier === 'string') tier.value = saved.tier; } catch (_) {} };
                const apply = (persist = true) => {
                    const f = read();
                    const quick = f.search.trim().toLowerCase();
                    let visible = 0;
                    rows.forEach((row) => {
                        const haystack = row.dataset[`${prefix.replace(/-([a-z])/g, (_, c) => c.toUpperCase())}Search`] ?? `${row.dataset.tierCompetition ?? ''} ${row.dataset.tierSeason ?? ''}`;
                        const show = (quick === '' || haystack.includes(quick))
                            && (f.country === '' || row.dataset.tierCountry === f.country)
                            && (f.competition === '' || row.dataset.tierCompetition === f.competition)
                            && (f.season === '' || row.dataset.tierSeason === f.season || row.dataset.tierRegistrySeason === f.season)
                            && (f.status === '' || row.dataset.tierStatus === f.status)
                            && (f.tier === '' || row.dataset.tierRegistryTier === f.tier);
                        row.classList.toggle('hidden', !show);
                        if (show) visible++;
                    });
                    empty?.classList.toggle('hidden', visible !== 0);
                    if (persist) save();
                };
                [search, country, competition, season, status, tier].forEach((el) => el?.addEventListener(el === search ? 'input' : 'change', apply));
                reset?.addEventListener('click', () => { [search, country, competition, season, status, tier].forEach((el) => { if (el) el.value = ''; }); try { window.localStorage.removeItem(key); } catch (_) {} apply(); });
                restore();
                apply(false);
            };
            bind('tier-coverage');
            bind('tier-registry');
        })();
    </script>
</x-app-layout>
