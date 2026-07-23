<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Classifiche</h1>
            <p class="mt-1 text-sm text-slate-400">Step 3 · sincronizzazione classifiche canoniche per lega e stagione.</p>
        </div>
    </x-slot>

    <div class="space-y-6" data-standing-management>
        @if (session('status'))<div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Guida Step 3</h2>
                    <p class="mt-1 text-sm text-slate-400">Prima completa le squadre. Poi interroga il layer provider canonico e salva la classifica normalizzata.</p>
                </div>
                <a href="{{ route('admin.teams.index') }}" class="rounded-xl border border-violet-400/30 bg-violet-400/10 px-4 py-2 text-sm text-violet-100">Vai a Squadre</a>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-4 text-sm">
                <div><strong class="text-white">1. Prerequisito</strong><p class="mt-1 text-slate-400">La lega/stagione deve avere squadre sincronizzate.</p></div>
                <div><strong class="text-white">2. Layer provider</strong><p class="mt-1 text-slate-400">Il sistema risolve le fonti configurate e restituisce righe classifica normalizzate.</p></div>
                <div><strong class="text-white">3. Analizza</strong><p class="mt-1 text-slate-400">Il dry-run mostra le righe senza scrivere.</p></div>
                <div><strong class="text-white">4. Sincronizza</strong><p class="mt-1 text-slate-400">Apply aggiorna il registry canonico classifiche.</p></div>
            </div>
        </section>

        <x-fo.panel title="Copertura classifiche" description="Una stagione e coperta quando ogni squadra attiva ha una riga classifica canonica." data-standing-coverage>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <details class="relative">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg bg-white/[0.05] text-slate-300 ring-1 ring-white/10 hover:bg-white/[0.09] [&::-webkit-details-marker]:hidden" title="Filtra classifiche" aria-label="Filtra copertura classifiche">
                        <flux:icon name="funnel" class="size-5" />
                    </summary>
                    <x-fo.card padding="p-4" class="absolute right-0 z-20 mt-2 bg-slate-100 text-slate-900" style="width: min(42rem, calc(100vw - 2rem));">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Ricerca rapida</span><input data-standing-search-filter type="search" placeholder="Cerca competizione o stagione..." class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400"></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Nazione</span><select data-standing-country-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte le nazioni</option>@foreach($standingCoverage->rows->whereNotNull('country_name')->unique('country_name')->sortBy('country_name') as $country)<option value="{{ \Illuminate\Support\Str::lower($country->country_name) }}">{{ $country->country_name }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Competizione</span><select data-standing-competition-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutte le competizioni</option>@foreach($standingCoverage->rows->unique('league_name')->sortBy('league_name') as $league)<option value="{{ \Illuminate\Support\Str::lower($league->league_name) }}">{{ $league->league_name }}</option>@endforeach</select></label>
                            <label class="space-y-1"><span class="text-xs font-semibold uppercase tracking-wide text-slate-600">Stato</span><select data-standing-status-filter class="w-full rounded-lg bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-slate-300"><option value="">Tutti gli stati</option><option value="covered">Coperta</option><option value="partial">Parziale</option><option value="missing">Mancante</option><option value="missing_teams">Senza squadre</option></select></label>
                        </div>
                        <button type="button" data-standing-filter-reset class="mt-3 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">Pulisci filtri</button>
                    </x-fo.card>
                </details>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-4">
                <x-fo.stat label="Complete" :value="$standingCoverage->covered" icon="check-circle" tone="green" />
                <x-fo.stat label="Parziali" :value="$standingCoverage->partial" icon="exclamation-triangle" tone="amber" />
                <x-fo.stat label="Mancanti" :value="$standingCoverage->missing" icon="minus-circle" tone="red" />
                <x-fo.stat label="Righe classifica" :value="$standingCoverage->standing_count" icon="queue-list" tone="blue" />
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-3">
                <form method="POST" action="{{ route('admin.standings.analyze') }}" class="rounded-xl border border-white/10 bg-black/20 p-4 xl:col-span-2">
                    @csrf
                    <h3 class="text-sm font-semibold text-white">Analizza classifica stagione</h3>
                    <p class="mt-1 text-xs text-slate-400">Interroga il layer provider e mostra cosa verrebbe scritto nel registry canonico.</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        <label class="space-y-2"><span class="text-sm font-medium text-slate-300">Lega + stagione</span><select name="league_season_id" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required><option value="">Seleziona...</option>@foreach($leagueSeasonOptions as $option)<option value="{{ $option->id }}" @selected((string) old('league_season_id', $lastStandingParameters['league_season_id'] ?? '') === (string) $option->id)>{{ $option->country_name ? $option->country_name.' · ' : '' }}{{ $option->league_name }} · {{ $option->season_label }}{{ $option->is_current ? ' · current' : '' }}</option>@endforeach</select></label>
                        <button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Analizza classifiche</button>
                    </div>
                </form>
                <div class="rounded-xl border border-sky-300/20 bg-sky-400/5 p-4 text-sm"><h3 class="font-semibold text-sky-100">Dove scrive</h3><p class="mt-2 text-slate-400">Scrive il dataset canonico in <span class="font-mono text-slate-200">league_season_team_standings</span>, collegato alle squadre gia normalizzate.</p></div>
            </div>

            <div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Nazione</th><th class="pb-3">Competizione</th><th class="pb-3">Stagione</th><th class="pb-3">Stato</th><th class="pb-3">Righe</th></tr></thead><tbody class="divide-y divide-white/5">
                @forelse($standingCoverage->rows as $row)
                    @php
                        $statusClass = match ($row->status) {
                            'covered' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-300/20',
                            'partial' => 'bg-amber-400/15 text-amber-200 ring-amber-300/20',
                            default => 'bg-slate-400/10 text-slate-300 ring-slate-300/20',
                        };
                        $statusLabel = match ($row->status) {
                            'covered' => 'coperta', 'partial' => 'parziale', 'missing_teams' => 'senza squadre', default => 'mancante',
                        };
                    @endphp
                    <tr data-standing-coverage-row data-standing-country="{{ \Illuminate\Support\Str::lower($row->country_name ?? '') }}" data-standing-competition="{{ \Illuminate\Support\Str::lower($row->league_name) }}" data-standing-season="{{ \Illuminate\Support\Str::lower($row->season_label) }}" data-standing-status="{{ $row->status }}"><td class="py-3 text-slate-400">{{ $row->country_name ?? '—' }}</td><td class="py-3 text-white">{{ $row->league_name }}</td><td class="py-3 text-slate-300">{{ $row->season_label }}{{ $row->is_current ? ' · current' : '' }}</td><td class="py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">{{ $statusLabel }}</span></td><td class="py-3 text-slate-300">{{ $row->standing_count }} / {{ $row->team_count }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-5 text-center text-slate-500">Nessuna stagione disponibile.</td></tr>
                @endforelse
                <tr data-standing-coverage-empty class="hidden"><td colspan="5" class="py-5 text-center text-slate-500">Nessuna riga per i filtri selezionati.</td></tr>
            </tbody></table></div>
        </x-fo.panel>

        <x-fo.panel title="Classifiche sincronizzate" description="Elenco canonico delle righe classifica disponibili nel nostro layer interno." data-standing-registry>
            <div class="mt-1 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Pos</th><th class="pb-3">Squadra</th><th class="pb-3">Competizione</th><th class="pb-3">Stagione</th><th class="pb-3">PG</th><th class="pb-3">Pt</th><th class="pb-3">DR</th></tr></thead><tbody class="divide-y divide-white/5">
                @forelse($standingRegistry->rows as $row)
                    <tr><td class="py-3 font-mono text-slate-200">{{ $row->position ?? '—' }}</td><td class="py-3 text-white">{{ $row->team_name }}</td><td class="py-3 text-slate-300">{{ $row->country_name ? $row->country_name.' · ' : '' }}{{ $row->league_name }}</td><td class="py-3 text-slate-300">{{ $row->season_label }}</td><td class="py-3 text-slate-300">{{ $row->played_games ?? '—' }}</td><td class="py-3 font-semibold text-white">{{ $row->points ?? '—' }}</td><td class="py-3 text-slate-300">{{ $row->goal_difference ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="7" class="py-5 text-center text-slate-500">Nessuna classifica sincronizzata. Esegui prima Analizza classifiche e poi Applica classifiche.</td></tr>
                @endforelse
            </tbody></table></div>
        </x-fo.panel>

        @if($lastStandingReport)
            <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold text-white">Ultimo report classifiche</h2><p class="mt-1 text-sm text-slate-400">{{ $lastStandingMode === 'apply' ? 'APPLY' : 'DRY-RUN' }} · Exit code {{ $lastStandingExitCode }} · League season {{ $lastStandingParameters['league_season_id'] ?? '—' }}</p></div>
                @if($lastStandingMode !== 'apply' && (int)$lastStandingExitCode === 0)<form method="POST" action="{{ route('admin.standings.apply') }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="league_season_id" value="{{ $lastStandingParameters['league_season_id'] ?? '' }}"><label class="space-y-1"><span class="block text-xs text-amber-200">Digita SINCRONIZZA</span><input name="confirmation" class="w-40 rounded-xl border border-amber-400/20 bg-black/20 px-3 py-2 text-white" required></label><button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm font-semibold text-amber-100">Applica classifiche</button></form>@endif</div>
                @if($lastStandingReportData && !empty($lastStandingReportData['standings']))<div class="mt-5 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-3">Squadra</th><th class="pb-3">Pos</th><th class="pb-3">Punti</th><th class="pb-3">Azione</th></tr></thead><tbody class="divide-y divide-white/5">@foreach($lastStandingReportData['standings'] as $row)<tr><td class="py-3 text-white">{{ $row['team_name'] }}</td><td class="py-3 text-slate-300">{{ $row['standing']['position'] ?? '—' }}</td><td class="py-3 text-slate-300">{{ $row['standing']['points'] ?? '—' }}</td><td class="py-3 font-medium text-violet-200">{{ $row['action'] }}</td></tr>@endforeach</tbody></table></div>@endif
                <details class="mt-5"><summary class="cursor-pointer text-sm text-slate-400">Dettagli tecnici</summary><pre class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-white/10 bg-black/30 p-4 text-xs leading-6 text-slate-300">{{ $lastStandingReport }}</pre></details>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-standing-management]');
            if (!root) return;
            const search = root.querySelector('[data-standing-search-filter]');
            const country = root.querySelector('[data-standing-country-filter]');
            const competition = root.querySelector('[data-standing-competition-filter]');
            const status = root.querySelector('[data-standing-status-filter]');
            const reset = root.querySelector('[data-standing-filter-reset]');
            const rows = Array.from(root.querySelectorAll('[data-standing-coverage-row]'));
            const empty = root.querySelector('[data-standing-coverage-empty]');
            const storageKey = 'fanta-oracle:standing-coverage-filters';
            const read = () => ({search: search?.value ?? '', country: country?.value ?? '', competition: competition?.value ?? '', status: status?.value ?? ''});
            const save = () => { try { window.localStorage.setItem(storageKey, JSON.stringify(read())); } catch (_) {} };
            const restore = () => { try { const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}'); if (search && typeof saved.search === 'string') search.value = saved.search; if (country && typeof saved.country === 'string') country.value = saved.country; if (competition && typeof saved.competition === 'string') competition.value = saved.competition; if (status && typeof saved.status === 'string') status.value = saved.status; } catch (_) {} };
            const apply = (persist = true) => { const filters = read(); const quick = filters.search.trim().toLowerCase(); let visible = 0; rows.forEach((row) => { const haystack = `${row.dataset.standingCompetition ?? ''} ${row.dataset.standingSeason ?? ''}`; const show = (quick === '' || haystack.includes(quick)) && (filters.country === '' || row.dataset.standingCountry === filters.country) && (filters.competition === '' || row.dataset.standingCompetition === filters.competition) && (filters.status === '' || row.dataset.standingStatus === filters.status); row.classList.toggle('hidden', !show); if (show) visible++; }); empty?.classList.toggle('hidden', visible !== 0); if (persist) save(); };
            search?.addEventListener('input', apply); country?.addEventListener('change', apply); competition?.addEventListener('change', apply); status?.addEventListener('change', apply); reset?.addEventListener('click', () => { if (search) search.value = ''; if (country) country.value = ''; if (competition) competition.value = ''; if (status) status.value = ''; try { window.localStorage.removeItem(storageKey); } catch (_) {} apply(); });
            restore(); apply(false);
        })();
    </script>
</x-app-layout>