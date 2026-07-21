<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class StandingManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.standings.index', [
            'standingCoverage' => $this->standingCoverage(),
            'standingRegistry' => $this->standingRegistry(),
            'leagueSeasonOptions' => $this->leagueSeasonOptions(),
            'lastStandingReport' => session('standing_sync_report'),
            'lastStandingReportData' => session('standing_sync_report_data'),
            'lastStandingMode' => session('standing_sync_mode'),
            'lastStandingExitCode' => session('standing_sync_exit_code'),
            'lastStandingParameters' => session('standing_sync_parameters', []),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        return $this->runStandingsSync($this->validateStandingsRequest($request), false);
    }

    public function apply(Request $request): RedirectResponse
    {
        return $this->runStandingsSync($this->validateStandingsRequest($request, true), true);
    }

    /** @return array{league_season_id:int,confirmation?:string} */
    private function validateStandingsRequest(Request $request, bool $apply = false): array
    {
        $rules = ['league_season_id' => ['required', 'integer', 'exists:league_seasons,id']];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:SINCRONIZZA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare la sincronizzazione classifiche devi digitare SINCRONIZZA.',
        ]);
    }

    /** @param array{league_season_id:int,confirmation?:string} $validated */
    private function runStandingsSync(array $validated, bool $apply): RedirectResponse
    {
        $arguments = [
            '--league-season-id' => (int) $validated['league_season_id'],
            '--json' => true,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('standings:sync', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.standings.index')
            ->with('standing_sync_report', $output)
            ->with('standing_sync_report_data', $reportData)
            ->with('standing_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('standing_sync_exit_code', $exitCode)
            ->with('standing_sync_parameters', ['league_season_id' => (int) $validated['league_season_id']])
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Sincronizzazione classifiche applicata correttamente.' : 'Analisi classifiche completata senza scritture sul database.')
                    : 'La sincronizzazione classifiche non e stata completata. Controlla il report.'
            );
    }

    private function leagueSeasonOptions()
    {
        return DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select(['ls.id', 'l.name as league_name', 'c.name as country_name', 's.season_key', 's.label as season_label', 'ls.is_current'])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get();
    }

    private function standingCoverage(): object
    {
        $rows = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->leftJoin('league_season_teams as lst', function ($join): void {
                $join->on('lst.league_season_id', '=', 'ls.id')->where('lst.is_active', true);
            })
            ->leftJoin('league_season_team_standings as st', 'st.league_season_team_id', '=', 'lst.id')
            ->select([
                'ls.id as league_season_id',
                'l.name as league_name',
                'c.name as country_name',
                's.label as season_label',
                's.season_key',
                'ls.is_current',
                DB::raw('COUNT(DISTINCT lst.id) as team_count'),
                DB::raw('COUNT(DISTINCT st.id) as standing_count'),
            ])
            ->groupBy(['ls.id', 'l.name', 'c.name', 's.label', 's.season_key', 'ls.is_current'])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get()
            ->map(function (object $row): object {
                $teamCount = (int) $row->team_count;
                $standingCount = (int) $row->standing_count;
                $status = $teamCount === 0
                    ? 'missing_teams'
                    : ($standingCount >= $teamCount ? 'covered' : ($standingCount > 0 ? 'partial' : 'missing'));

                return (object) [
                    'league_season_id' => (int) $row->league_season_id,
                    'league_name' => $row->league_name,
                    'country_name' => $row->country_name,
                    'season_label' => $row->season_label,
                    'season_key' => (int) $row->season_key,
                    'is_current' => (bool) $row->is_current,
                    'team_count' => $teamCount,
                    'standing_count' => $standingCount,
                    'status' => $status,
                ];
            });

        return (object) [
            'rows' => $rows,
            'covered' => $rows->where('status', 'covered')->count(),
            'partial' => $rows->where('status', 'partial')->count(),
            'missing' => $rows->whereIn('status', ['missing', 'missing_teams'])->count(),
            'standing_count' => $rows->sum('standing_count'),
        ];
    }

    private function standingRegistry(): object
    {
        $rows = DB::table('league_season_team_standings as st')
            ->join('league_season_teams as lst', 'lst.id', '=', 'st.league_season_team_id')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select([
                'st.id', 't.name as team_name', 't.code', 'l.name as league_name', 'c.name as country_name',
                's.label as season_label', 's.season_key', 'ls.is_current', 'st.position', 'st.points',
                'st.played_games', 'st.won', 'st.draw', 'st.lost', 'st.goals_for', 'st.goals_against',
                'st.goal_difference', 'st.stage_name', 'st.group_name', 'st.synced_at',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->orderBy('st.position')
            ->get();

        return (object) ['rows' => $rows, 'total' => $rows->count()];
    }

    /** @return array<string,mixed>|null */
    private function extractJsonReport(string $output): ?array
    {
        $position = strpos($output, '{');
        if ($position === false) {
            return null;
        }

        $decoded = json_decode(substr($output, $position), true);

        return is_array($decoded) ? $decoded : null;
    }
}