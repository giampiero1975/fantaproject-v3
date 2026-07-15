<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SeasonManagementController extends Controller
{
    public function index(): View
    {
        $leagues = DB::table('leagues as l')
            ->join('league_provider_mappings as lpm', 'lpm.league_id', '=', 'l.id')
            ->join('data_providers as p', 'p.id', '=', 'lpm.data_provider_id')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->select('l.id', 'l.name', 'p.code as provider_code', 'p.name as provider_name', 'lpm.external_id', 'lpm.external_name', 'rc.is_enabled', 'rc.role', 'rc.priority', 'rc.plan')
            ->orderBy('l.name')
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $first = $rows->first();

                return (object) [
                    'id' => $first->id,
                    'name' => $first->name,
                    'providers' => $rows->values(),
                ];
            })
            ->values();

        return view('admin.seasons.index', [
            'leagues' => $leagues,
            'historyFallback' => (int) config('seasons.history_fallback', 4),
            'lastReport' => session('season_sync_report'),
            'lastReportData' => session('season_sync_report_data'),
            'lastMode' => session('season_sync_mode'),
            'lastExitCode' => session('season_sync_exit_code'),
            'lastParameters' => session('season_sync_parameters', []),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        return $this->runSync($this->validateRequest($request), false);
    }

    public function apply(Request $request): RedirectResponse
    {
        return $this->runSync($this->validateRequest($request, true), true);
    }

    /** @return array{league_id:int,history:?int,confirmation?:string} */
    private function validateRequest(Request $request, bool $apply = false): array
    {
        $rules = [
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'history' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:APPLICA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare la sincronizzazione devi digitare APPLICA.',
        ]);
    }

    /** @param array{league_id:int,history:?int,confirmation?:string} $validated */
    private function runSync(array $validated, bool $apply): RedirectResponse
    {
        $mappings = DB::table('league_provider_mappings as lpm')
            ->join('data_providers as p', 'p.id', '=', 'lpm.data_provider_id')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->where('lpm.league_id', $validated['league_id'])
            ->whereIn('p.code', ['football_data', 'api_football'])
            ->get(['p.code', 'p.name', 'lpm.external_id', 'lpm.external_name', 'rc.is_enabled', 'rc.priority', 'rc.role', 'rc.plan']);

        $footballData = $mappings->firstWhere('code', 'football_data');
        $apiFootball = $mappings->firstWhere('code', 'api_football');

        if (! $footballData || ! $apiFootball) {
            throw ValidationException::withMessages([
                'league_id' => 'La competizione selezionata deve avere mapping per football_data e api_football prima della sincronizzazione.',
            ]);
        }

        $parameters = [
            'league_id' => (int) $validated['league_id'],
            'competition' => strtoupper((string) $footballData->external_id),
            'api_league_id' => (int) $apiFootball->external_id,
            'history' => $validated['history'] !== null ? (int) $validated['history'] : null,
            'providers' => $mappings->map(fn ($row) => (array) $row)->all(),
        ];

        $arguments = [
            '--league-id' => $parameters['league_id'],
            '--competition' => $parameters['competition'],
            '--api-league-id' => $parameters['api_league_id'],
            '--json' => true,
        ];

        if ($parameters['history'] !== null) {
            $arguments['--history'] = $parameters['history'];
        }

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('season:sync', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.seasons.index')
            ->with('season_sync_report', $output)
            ->with('season_sync_report_data', $reportData)
            ->with('season_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('season_sync_exit_code', $exitCode)
            ->with('season_sync_parameters', $parameters)
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Sincronizzazione applicata correttamente.' : 'Analisi completata senza scritture sul database.')
                    : 'La sincronizzazione non è stata completata. Controlla il report.'
            );
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
