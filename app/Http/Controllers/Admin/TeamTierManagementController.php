<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class TeamTierManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.team-tiers.index', [
            'tierCoverage' => $this->tierCoverage(),
            'tierRegistry' => $this->tierRegistry(),
            'leagueSeasonOptions' => $this->leagueSeasonOptions(),
            'lastTierReport' => session('tier_sync_report'),
            'lastTierReportData' => session('tier_sync_report_data'),
            'lastTierMode' => session('tier_sync_mode'),
            'lastTierExitCode' => session('tier_sync_exit_code'),
            'lastTierParameters' => session('tier_sync_parameters', []),
            'lastTierPerformanceReport' => session('tier_performance_report'),
            'lastTierPerformanceReportData' => session('tier_performance_report_data'),
            'lastTierPerformanceExitCode' => session('tier_performance_exit_code'),
            'lastTierPerformanceParameters' => session('tier_performance_parameters', []),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        return $this->runTierSync($this->validateTierRequest($request), false);
    }

    public function auditPerformance(Request $request): RedirectResponse
    {
        $validated = $this->validateTierRequest($request);
        $arguments = [
            '--league-season-id' => (int) $validated['league_season_id'],
            '--json' => true,
        ];

        $exitCode = Artisan::call('team-tiers:audit-performance', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.team-tiers.index')
            ->with('tier_performance_report', $output)
            ->with('tier_performance_report_data', $reportData)
            ->with('tier_performance_exit_code', $exitCode)
            ->with('tier_performance_parameters', ['league_season_id' => (int) $validated['league_season_id']])
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? 'Audit prestazione reale completato.'
                    : 'Audit prestazione reale non completato. Controlla il report.'
            );
    }

    public function apply(Request $request): RedirectResponse
    {
        return $this->runTierSync($this->validateTierRequest($request, true), true);
    }

    /** @return array{league_season_id:int,confirmation?:string} */
    private function validateTierRequest(Request $request, bool $apply = false): array
    {
        $rules = ['league_season_id' => ['required', 'integer', 'exists:league_seasons,id']];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:CALCOLA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare il calcolo tier devi digitare CALCOLA.',
        ]);
    }

    /** @param array{league_season_id:int,confirmation?:string} $validated */
    private function runTierSync(array $validated, bool $apply): RedirectResponse
    {
        $arguments = [
            '--league-season-id' => (int) $validated['league_season_id'],
            '--json' => true,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('team-tiers:sync', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.team-tiers.index')
            ->with('tier_sync_report', $output)
            ->with('tier_sync_report_data', $reportData)
            ->with('tier_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('tier_sync_exit_code', $exitCode)
            ->with('tier_sync_parameters', ['league_season_id' => (int) $validated['league_season_id']])
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Tier squadre applicati correttamente.' : 'Analisi tier completata senza scritture sul database.')
                    : 'Il calcolo tier non è stato completato. Controlla il report.'
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

    private function tierCoverage(): object
    {
        $rows = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->leftJoin('league_season_teams as lst', function ($join): void {
                $join->on('lst.league_season_id', '=', 'ls.id')->where('lst.is_active', true);
            })
            ->select([
                'ls.id as league_season_id',
                'l.name as league_name',
                'c.name as country_name',
                's.label as season_label',
                's.season_key',
                'ls.is_current',
                DB::raw('COUNT(DISTINCT lst.id) as team_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN lst.tier_stagionale IS NOT NULL THEN lst.id END) as tier_count'),
            ])
            ->groupBy(['ls.id', 'l.name', 'c.name', 's.label', 's.season_key', 'ls.is_current'])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get()
            ->map(function (object $row): object {
                $teamCount = (int) $row->team_count;
                $tierCount = (int) $row->tier_count;
                $status = $teamCount === 0
                    ? 'missing_teams'
                    : ($tierCount >= $teamCount ? 'covered' : ($tierCount > 0 ? 'partial' : 'missing'));

                return (object) [
                    'league_season_id' => (int) $row->league_season_id,
                    'league_name' => $row->league_name,
                    'country_name' => $row->country_name,
                    'season_label' => $row->season_label,
                    'season_key' => (int) $row->season_key,
                    'is_current' => (bool) $row->is_current,
                    'team_count' => $teamCount,
                    'tier_count' => $tierCount,
                    'status' => $status,
                ];
            });

        return (object) [
            'rows' => $rows,
            'covered' => $rows->where('status', 'covered')->count(),
            'partial' => $rows->where('status', 'partial')->count(),
            'missing' => $rows->whereIn('status', ['missing', 'missing_teams'])->count(),
            'tier_count' => $rows->sum('tier_count'),
        ];
    }

    private function tierRegistry(): object
    {
        $rows = DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->whereNotNull('lst.tier_stagionale')
            ->select([
                't.name as team_name',
                't.code',
                't.crest_url',
                't.tier_globale',
                't.posizione_media_storica',
                'lst.tier_stagionale',
                'lst.tier_score',
                'l.name as league_name',
                'c.name as country_name',
                's.label as season_label',
                's.season_key',
                'ls.is_current',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->orderBy('lst.tier_score')
            ->orderBy('t.name')
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
