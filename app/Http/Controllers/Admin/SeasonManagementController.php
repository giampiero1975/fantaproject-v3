<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

final class SeasonManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.seasons.index', [
            'historyFallback' => (int) config('seasons.history_fallback', 4),
            'lastReport' => session('season_sync_report'),
            'lastMode' => session('season_sync_mode'),
            'lastExitCode' => session('season_sync_exit_code'),
        ]);
    }

    public function analyze(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        return $this->runSync($validated, false);
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request, true);

        return $this->runSync($validated, true);
    }

    /** @return array{competition:string,api_league_id:int,history:?int,confirmation?:string} */
    private function validateRequest(Request $request, bool $apply = false): array
    {
        $rules = [
            'competition' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'api_league_id' => ['required', 'integer', 'min:1'],
            'history' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:APPLICA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare la sincronizzazione devi digitare APPLICA.',
        ]);
    }

    /** @param array{competition:string,api_league_id:int,history:?int,confirmation?:string} $validated */
    private function runSync(array $validated, bool $apply): RedirectResponse
    {
        $arguments = [
            '--competition' => strtoupper($validated['competition']),
            '--api-league-id' => (int) $validated['api_league_id'],
            '--json' => true,
        ];

        if ($validated['history'] !== null) {
            $arguments['--history'] = (int) $validated['history'];
        }

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('season:sync', $arguments);
        $output = trim(Artisan::output());

        return redirect()
            ->route('admin.seasons.index')
            ->with('season_sync_report', $output)
            ->with('season_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('season_sync_exit_code', $exitCode)
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Sincronizzazione applicata correttamente.' : 'Analisi completata senza scritture sul database.')
                    : 'La sincronizzazione non è stata completata. Controlla il report.'
            );
    }
}
