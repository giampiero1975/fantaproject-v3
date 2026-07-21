<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Squadre</h1>
            <p class="mt-1 text-sm text-slate-400">Step 2 · sincronizzazione anagrafica squadre per lega e stagione.</p>
        </div>
    </x-slot>

    <div class="space-y-6" data-team-management>
        @if (session('status'))<div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Guida Step 2</h2>
                    <p class="mt-1 text-sm text-slate-400">Prima completa le stagioni. Poi seleziona una lega/stagione e interroga il layer provider canonico per costruire il registry squadre.</p>
                </div>
                <a href="{{ route('admin.seasons.index') }}" class="rounded-xl border border-violet-400/30 bg-violet-400/10 px-4 py-2 text-sm text-violet-100">Vai a Gestione Stagioni</a>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm">
                <div><strong class="text-white">1. Prerequisito</strong><p class="mt-1 text-slate-400">La timeline deve avere almeno una stagione sincronizzata.</p></div>
                <div><strong class="text-white">2. Layer provider</strong><p class="mt-1 text-slate-400">Il sistema risolve internamente le fonti configurate e restituisce squadre normalizzate.</p></div>
                <div><strong class="text-white">3. Analizza</strong><p class="mt-1 text-slate-400">Il dry-run mostra le squadre normalizzate senza scrivere.</p></div>
                <div><strong class="text-white">4. Sincronizza</strong><p class="mt-1 text-slate-400">Apply aggiorna il registry canonico delle squadre.</p></div>
            </div>
        </section>

        <x-fo.panel
            title="Copertura squadre"
            description="Visione DB-only: una stagione è coperta quando esistono squadre attive in league_season_teams."
            data-team-coverage
        >
            <div class="flex flex-wrap items-center justify-end gap-2">
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra squadre" aria-label="Filtra copertura squadre">
                        <flux:icon name="funnel" class="size-5" />
                    </summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(42rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ricerca rapida</span>
                                <input data-team-search-filter type="search" placeholder="Cerca competizione o stagione..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span>
                                <select data-team-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le nazioni</option>
                                    @foreach($teamCoverage->rows->whereNotNull('country_name')->unique('country_name')->sortBy('country_name') as $country)
                                        <option value="{{ \Illuminate\Support\Str::lower($country->country_name) }}">{{ $country->country_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span>
                                <select data-team-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le competizioni</option>
                                    @foreach($teamCoverage->rows->unique('league_name')->sortBy('league_name') as $league)
                                        <option value="{{ \Illuminate\Support\Str::lower($league->league_name) }}">{{ $league->league_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stato</span>
                                <select data-team-status-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutti gli stati</option>
                                    <option value="covered">Coperta</option>
                                    <option value="missing">Da sincronizzare</option>
                                </select>
                            </label>
                        </div>
                        <button type="button" data-team-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <x-fo.stat label="Stagioni con squadre" :value="$teamCoverage->covered" icon="check-circle" tone="green" />
                <x-fo.stat label="Stagioni senza squadre" :value="$teamCoverage->missing" icon="minus-circle" tone="amber" />
                <x-fo.stat label="Squadre attive" :value="$teamCoverage->team_count" icon="queue-list" tone="blue" />
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-3">
                <form method="POST" action="{{ route('admin.teams.analyze') }}" class="rounded-xl border border-white/10 bg-black/20 p-4 xl:col-span-2">
                    @csrf
                    <h3 class="text-sm font-semibold text-white">Analizza squadre stagione</h3>
                    <p class="mt-1 text-xs text-slate-400">Interroga il layer provider e mostra cosa verrebbe scritto nel registry canonico.</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        <label class="space-y-2">
                            <span class="text-sm font-medium text-slate-300">Lega + stagione</span>
                            <select name="league_season_id" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                                <option value="">Seleziona...</option>
                                @foreach($leagueSeasonOptions as $option)
                                    <option value="{{ $option->id }}" @selected((string) old('league_season_id', $lastTeamParameters['league_season_id'] ?? '') === (string) $option->id)>
                                        {{ $option->country_name ? $option->country_name.' · ' : '' }}{{ $option->league_name }} · {{ $option->season_label }}{{ $option->is_current ? ' · current' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Analizza squadre</button>
                    </div>
                </form>

                <div class="rounded-xl border border-sky-300/20 bg-sky-400/5 p-4 text-sm">
                    <h3 class="font-semibold text-sky-100">Dove scrive</h3>
                    <p class="mt-2 text-slate-400">Scrive il dataset canonico: anagrafica squadre e presenza per lega/stagione. La tracciabilita tecnica resta nei dettagli e nei log.</p>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500"><tr><th class="pb-3">Nazione</th><th class="pb-3">Competizione</th><th class="pb-3">Stagione</th><th class="pb-3">Stato squadre</th><th class="pb-3">Squadre</th></tr></thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($teamCoverage->rows as $row)
                            @php
                                $teamStatusClass = $row->status === 'covered'
                                    ? 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20'
                                    : 'bg-amber-400/15 text-amber-200 ring-amber-300/20';
                                $teamStatusLabel = $row->status === 'covered' ? 'coperta' : 'da sincronizzare';
                            @endphp
                            <tr
                                data-team-coverage-row
                                data-team-country="{{ \Illuminate\Support\Str::lower($row->country_name ?? '') }}"
                                data-team-competition="{{ \Illuminate\Support\Str::lower($row->league_name) }}"
                                data-team-season="{{ \Illuminate\Support\Str::lower($row->season_label) }}"
                                data-team-status="{{ $row->status }}"
                            >
                                <td class="py-3 text-slate-400">{{ $row->country_name ?? '—' }}</td>
                                <td class="py-3 text-white">{{ $row->league_name }}</td>
                                <td class="py-3 text-slate-300">{{ $row->season_label }}{{ $row->is_current ? ' · current' : '' }}</td>
                                <td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $teamStatusClass }}">{{ $teamStatusLabel }}</span></td>
                                <td class="py-3 text-slate-300">{{ $row->team_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-5 text-center text-slate-500">Nessuna stagione sincronizzata: completa prima Gestione Stagioni.</td></tr>
                        @endforelse
                        <tr data-team-coverage-empty class="hidden"><td colspan="5" class="py-5 text-center text-slate-500">Nessuna riga per i filtri selezionati.</td></tr>
                    </tbody>
                </table>
            </div>
        </x-fo.panel>

        <x-fo.panel
            title="Squadre sincronizzate"
            description="Elenco canonico delle squadre disponibili per competizione e stagione nel nostro layer interno."
            data-team-registry
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="grid flex-1 gap-3 sm:grid-cols-2">
                    <x-fo.stat label="Presenze stagionali" :value="$teamRegistry->total" icon="users" tone="blue" />
                    <x-fo.stat label="Presenze attive" :value="$teamRegistry->active" icon="check-circle" tone="green" />
                </div>
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra squadre sincronizzate" aria-label="Filtra squadre sincronizzate">
                        <flux:icon name="funnel" class="size-5" />
                    </summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(42rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ricerca rapida</span>
                                <input data-team-registry-search-filter type="search" placeholder="Cerca squadra, codice o stagione..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400">
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span>
                                <select data-team-registry-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le nazioni</option>
                                    @foreach($teamRegistry->rows->whereNotNull('country_name')->unique('country_name')->sortBy('country_name') as $country)
                                        <option value="{{ \Illuminate\Support\Str::lower($country->country_name) }}">{{ $country->country_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span>
                                <select data-team-registry-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le competizioni</option>
                                    @foreach($teamRegistry->rows->unique('league_name')->sortBy('league_name') as $league)
                                        <option value="{{ \Illuminate\Support\Str::lower($league->league_name) }}">{{ $league->league_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stagione</span>
                                <select data-team-registry-season-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutte le stagioni</option>
                                    @foreach($teamRegistry->rows->unique('season_label')->sortByDesc('season_key') as $season)
                                        <option value="{{ \Illuminate\Support\Str::lower($season->season_label) }}">{{ $season->season_label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stato</span>
                                <select data-team-registry-status-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300">
                                    <option value="">Tutti gli stati</option>
                                    <option value="active">Attiva</option>
                                    <option value="inactive">Non attiva</option>
                                </select>
                            </label>
                        </div>
                        <button type="button" data-team-registry-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr>
                            <th class="pb-3">Squadra</th>
                            <th class="pb-3">Codice</th>
                            <th class="pb-3">Competizione</th>
                            <th class="pb-3">Stagione</th>
                            <th class="pb-3">Stato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse($teamRegistry->rows as $team)
                            <tr
                                data-team-registry-row
                                data-team-registry-country="{{ \Illuminate\Support\Str::lower($team->country_name ?? '') }}"
                                data-team-registry-competition="{{ \Illuminate\Support\Str::lower($team->league_name) }}"
                                data-team-registry-season="{{ \Illuminate\Support\Str::lower($team->season_label) }}"
                                data-team-registry-status="{{ $team->is_active ? 'active' : 'inactive' }}"
                                data-team-registry-search="{{ \Illuminate\Support\Str::lower(trim($team->team_name.' '.($team->short_name ?? '').' '.($team->code ?? '').' '.$team->league_name.' '.$team->season_label)) }}"
                            >
                                <td class="py-3">
                                    <div class="flex items-center gap-3">
                                        @if($team->crest_url)
                                            <img src="{{ $team->crest_url }}" alt="" class="size-8 rounded-full bg-white object-contain p-1">
                                        @else
                                            <span class="flex size-8 items-center justify-center rounded-full bg-white/10 text-xs font-semibold text-slate-300">{{ \Illuminate\Support\Str::of($team->team_name)->substr(0, 2)->upper() }}</span>
                                        @endif
                                        <div>
                                            <div class="font-semibold text-white">{{ $team->team_name }}</div>
                                            @if($team->short_name)
                                                <div class="text-xs text-slate-500">{{ $team->short_name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 font-mono text-slate-300">{{ $team->code ?? '—' }}</td>
                                <td class="py-3 text-slate-300">{{ $team->country_name ? $team->country_name.' · ' : '' }}{{ $team->league_name }}</td>
                                <td class="py-3 text-slate-300">{{ $team->season_label }}{{ $team->is_current ? ' · current' : '' }}</td>
                                <td class="py-3">
                                    @if($team->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-400/15 px-2.5 py-1 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-300/20">attiva</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-400/10 px-2.5 py-1 text-xs font-semibold text-slate-300 ring-1 ring-slate-300/20">non attiva</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-5 text-center text-slate-500">Nessuna squadra sincronizzata. Esegui prima Analizza squadre e poi Applica squadre.</td></tr>
                        @endforelse
                        <tr data-team-registry-empty class="hidden"><td colspan="5" class="py-5 text-center text-slate-500">Nessuna squadra per i filtri selezionati.</td></tr>
                    </tbody>
                </table>
            </div>
        </x-fo.panel>        @if($lastTeamReport)
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Ultimo report squadre</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $lastTeamMode === 'apply' ? 'APPLY' : 'DRY-RUN' }} · Exit code {{ $lastTeamExitCode }} · League season {{ $lastTeamParameters['league_season_id'] ?? '—' }}</p>
                    </div>
                    @if($lastTeamMode !== 'apply' && (int)$lastTeamExitCode === 0)
                        <form method="POST" action="{{ route('admin.teams.apply') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <input type="hidden" name="league_season_id" value="{{ $lastTeamParameters['league_season_id'] ?? '' }}">
                            <label class="space-y-1"><span class="block text-xs text-amber-200">Digita SINCRONIZZA</span><input name="confirmation" class="w-40 rounded-xl border border-amber-400/20 bg-black/20 px-3 py-2 text-white" required></label>
                            <button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100">Applica squadre</button>
                        </form>
                    @endif
                </div>

                @if($lastTeamReportData && !empty($lastTeamReportData['teams']))
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-slate-500"><tr><th class="pb-3">Squadra</th><th class="pb-3">Codice</th><th class="pb-3">Azione</th></tr></thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($lastTeamReportData['teams'] as $row)
                                    <tr>
                                        <td class="py-3 text-white">{{ $row['team']['name'] }}</td>
                                        <td class="py-3 font-mono text-slate-300">{{ $row['team']['code'] ?? '—' }}</td>
                                        <td class="py-3 font-medium text-violet-200">{{ $row['action'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastTeamReport }}</pre></details>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-team-management]');
            if (!root) return;

            const search = root.querySelector('[data-team-search-filter]');
            const country = root.querySelector('[data-team-country-filter]');
            const competition = root.querySelector('[data-team-competition-filter]');
            const status = root.querySelector('[data-team-status-filter]');
            const reset = root.querySelector('[data-team-filter-reset]');
            const rows = Array.from(root.querySelectorAll('[data-team-coverage-row]'));
            const empty = root.querySelector('[data-team-coverage-empty]');
            const storageKey = 'fanta-oracle:team-coverage-filters';
            const registrySearch = root.querySelector('[data-team-registry-search-filter]');
            const registryCountry = root.querySelector('[data-team-registry-country-filter]');
            const registryCompetition = root.querySelector('[data-team-registry-competition-filter]');
            const registrySeason = root.querySelector('[data-team-registry-season-filter]');
            const registryStatus = root.querySelector('[data-team-registry-status-filter]');
            const registryReset = root.querySelector('[data-team-registry-filter-reset]');
            const registryRows = Array.from(root.querySelectorAll('[data-team-registry-row]'));
            const registryEmpty = root.querySelector('[data-team-registry-empty]');
            const registryStorageKey = 'fanta-oracle:team-registry-filters';

            const readRegistry = () => ({
                search: registrySearch?.value ?? '',
                country: registryCountry?.value ?? '',
                competition: registryCompetition?.value ?? '',
                season: registrySeason?.value ?? '',
                status: registryStatus?.value ?? '',
            });

            const saveRegistry = () => {
                try {
                    window.localStorage.setItem(registryStorageKey, JSON.stringify(readRegistry()));
                } catch (_) {}
            };

            const restoreRegistry = () => {
                try {
                    const saved = JSON.parse(window.localStorage.getItem(registryStorageKey) ?? '{}');
                    if (registrySearch && typeof saved.search === 'string') registrySearch.value = saved.search;
                    if (registryCountry && typeof saved.country === 'string') registryCountry.value = saved.country;
                    if (registryCompetition && typeof saved.competition === 'string') registryCompetition.value = saved.competition;
                    if (registrySeason && typeof saved.season === 'string') registrySeason.value = saved.season;
                    if (registryStatus && typeof saved.status === 'string') registryStatus.value = saved.status;
                } catch (_) {}
            };

            const applyRegistry = (persist = true) => {
                const filters = readRegistry();
                const quick = filters.search.trim().toLowerCase();
                let visible = 0;

                registryRows.forEach((row) => {
                    const show = (quick === '' || (row.dataset.teamRegistrySearch ?? '').includes(quick))
                        && (filters.country === '' || row.dataset.teamRegistryCountry === filters.country)
                        && (filters.competition === '' || row.dataset.teamRegistryCompetition === filters.competition)
                        && (filters.season === '' || row.dataset.teamRegistrySeason === filters.season)
                        && (filters.status === '' || row.dataset.teamRegistryStatus === filters.status);
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });

                registryEmpty?.classList.toggle('hidden', visible !== 0);

                if (persist) saveRegistry();
            };

            const read = () => ({
                search: search?.value ?? '',
                country: country?.value ?? '',
                competition: competition?.value ?? '',
                status: status?.value ?? '',
            });

            const save = () => {
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify(read()));
                } catch (_) {}
            };

            const restore = () => {
                try {
                    const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}');
                    if (search && typeof saved.search === 'string') search.value = saved.search;
                    if (country && typeof saved.country === 'string') country.value = saved.country;
                    if (competition && typeof saved.competition === 'string') competition.value = saved.competition;
                    if (status && typeof saved.status === 'string') status.value = saved.status;
                } catch (_) {}
            };

            const apply = (persist = true) => {
                const filters = read();
                const quick = filters.search.trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const haystack = `${row.dataset.teamCompetition ?? ''} ${row.dataset.teamSeason ?? ''}`;
                    const show = (quick === '' || haystack.includes(quick))
                        && (filters.country === '' || row.dataset.teamCountry === filters.country)
                        && (filters.competition === '' || row.dataset.teamCompetition === filters.competition)
                        && (filters.status === '' || row.dataset.teamStatus === filters.status);
                    row.classList.toggle('hidden', !show);
                    if (show) visible++;
                });

                empty?.classList.toggle('hidden', visible !== 0);

                if (persist) save();
            };

            search?.addEventListener('input', apply);
            country?.addEventListener('change', apply);
            competition?.addEventListener('change', apply);
            status?.addEventListener('change', apply);
            reset?.addEventListener('click', () => {
                if (search) search.value = '';
                if (country) country.value = '';
                if (competition) competition.value = '';
                if (status) status.value = '';
                try {
                    window.localStorage.removeItem(storageKey);
                } catch (_) {}
                apply();
            });

            registrySearch?.addEventListener('input', applyRegistry);
            registryCountry?.addEventListener('change', applyRegistry);
            registryCompetition?.addEventListener('change', applyRegistry);
            registrySeason?.addEventListener('change', applyRegistry);
            registryStatus?.addEventListener('change', applyRegistry);
            registryReset?.addEventListener('click', () => {
                if (registrySearch) registrySearch.value = '';
                if (registryCountry) registryCountry.value = '';
                if (registryCompetition) registryCompetition.value = '';
                if (registrySeason) registrySeason.value = '';
                if (registryStatus) registryStatus.value = '';
                try {
                    window.localStorage.removeItem(registryStorageKey);
                } catch (_) {}
                applyRegistry();
            });

            restore();
            apply(false);
            restoreRegistry();
            applyRegistry(false);
        })();
    </script>
</x-app-layout>
