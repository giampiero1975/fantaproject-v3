<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class TeamManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.teams.index', [
            'teamCoverage' => $this->teamCoverage(),
            'teamRegistry' => $this->teamRegistry(),
            'leagueSeasonOptions' => $this->leagueSeasonOptions(),
            'lastTeamReport' => session('team_sync_report'),
            'lastTeamReportData' => session('team_sync_report_data'),
            'lastTeamMode' => session('team_sync_mode'),
            'lastTeamExitCode' => session('team_sync_exit_code'),
            'lastTeamParameters' => session('team_sync_parameters', []),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        return $this->runTeamsSync($this->validateTeamsRequest($request), false);
    }

    public function apply(Request $request): RedirectResponse
    {
        return $this->runTeamsSync($this->validateTeamsRequest($request, true), true);
    }

    /** @return array{league_season_id:int,confirmation?:string} */
    private function validateTeamsRequest(Request $request, bool $apply = false): array
    {
        $rules = [
            'league_season_id' => ['required', 'integer', 'exists:league_seasons,id'],
        ];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:SINCRONIZZA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare la sincronizzazione squadre devi digitare SINCRONIZZA.',
        ]);
    }

    /** @param array{league_season_id:int,confirmation?:string} $validated */
    private function runTeamsSync(array $validated, bool $apply): RedirectResponse
    {
        $arguments = [
            '--league-season-id' => (int) $validated['league_season_id'],
            '--json' => true,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('teams:sync', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.teams.index')
            ->with('team_sync_report', $output)
            ->with('team_sync_report_data', $reportData)
            ->with('team_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('team_sync_exit_code', $exitCode)
            ->with('team_sync_parameters', [
                'league_season_id' => (int) $validated['league_season_id'],
            ])
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Sincronizzazione squadre applicata correttamente.' : 'Analisi squadre completata senza scritture sul database.')
                    : 'La sincronizzazione squadre non è stata completata. Controlla il report.'
            );
    }

    private function leagueSeasonOptions()
    {
        return DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select([
                'ls.id',
                'l.name as league_name',
                'c.name as country_name',
                's.season_key',
                's.label as season_label',
                'ls.is_current',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get();
    }

    private function teamCoverage(): object
    {
        $rows = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->leftJoin('league_season_teams as lst', function ($join): void {
                $join->on('lst.league_season_id', '=', 'ls.id')
                    ->where('lst.is_active', true);
            })
            ->select([
                'ls.id as league_season_id',
                'l.name as league_name',
                'c.name as country_name',
                's.label as season_label',
                's.season_key',
                'ls.is_current',
                DB::raw('COUNT(DISTINCT lst.id) as team_count'),
            ])
            ->groupBy([
                'ls.id',
                'l.name',
                'c.name',
                's.label',
                's.season_key',
                'ls.is_current',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get()
            ->map(function (object $row): object {
                $teamCount = (int) $row->team_count;

                return (object) [
                    'league_season_id' => (int) $row->league_season_id,
                    'league_name' => $row->league_name,
                    'country_name' => $row->country_name,
                    'season_label' => $row->season_label,
                    'season_key' => (int) $row->season_key,
                    'is_current' => (bool) $row->is_current,
                    'team_count' => $teamCount,
                    'status' => $teamCount > 0 ? 'covered' : 'missing',
                ];
            });

        return (object) [
            'rows' => $rows,
            'covered' => $rows->where('status', 'covered')->count(),
            'missing' => $rows->where('status', 'missing')->count(),
            'team_count' => $rows->sum('team_count'),
        ];
    }

    private function teamRegistry(): object
    {
        $rows = DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select([
                'lst.id as league_season_team_id',
                't.id as team_id',
                't.name as team_name',
                't.short_name',
                't.code',
                't.crest_url',
                'l.name as league_name',
                'c.name as country_name',
                's.label as season_label',
                's.season_key',
                'ls.is_current',
                'lst.is_active',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->orderBy('t.name')
            ->get()
            ->map(fn (object $row): object => (object) [
                'league_season_team_id' => (int) $row->league_season_team_id,
                'team_id' => (int) $row->team_id,
                'team_name' => $row->team_name,
                'short_name' => $row->short_name,
                'code' => $row->code,
                'crest_url' => $row->crest_url,
                'league_name' => $row->league_name,
                'country_name' => $row->country_name,
                'season_label' => $row->season_label,
                'season_key' => (int) $row->season_key,
                'is_current' => (bool) $row->is_current,
                'is_active' => (bool) $row->is_active,
            ]);

        return (object) [
            'rows' => $rows,
            'total' => $rows->count(),
            'active' => $rows->where('is_active', true)->count(),
        ];
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
