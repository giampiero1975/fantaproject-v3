<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
use App\Services\Providers\TeamAuditPlanner;
use App\Services\Providers\TeamProviderRegistry;
use Illuminate\Console\Command;
use Throwable;

final class AuditSeasonProviderCongruity extends Command
{
    protected $signature = 'season:audit-providers
        {--competition=SA : football-data.org competition reference}
        {--league-id=135 : API-Football league reference}
        {--season=2024 : season start year}
        {--json : print full JSON report}';

    protected $description = 'Dry-run capability audit for all registered team data providers.';

    public function handle(TeamProviderRegistry $registry, TeamAuditPlanner $planner): int
    {
        $season = (int) $this->option('season');
        $request = new TeamDataRequest($season, [
            'football_data' => strtoupper(trim((string) $this->option('competition'))),
            'api_football' => (int) $this->option('league-id'),
        ]);

        $this->components->info("DRY-RUN teams / season {$season}");
        $this->line('No database writes will be performed.');

        try {
            $results = array_map(
                fn ($provider) => $provider->fetchTeams($request),
                $registry->all(),
            );
            $plan = $planner->plan($results);
            $available = $plan['available'];

            if ($plan['mode'] === 'multi_provider_congruity') {
                $comparison = $plan['comparison'];
                $this->table(['Metric', 'Value'], [
                    ['Status', strtoupper($plan['status'])],
                    ['Mode', 'MULTI PROVIDER CONGRUITY'],
                    [$available[0]->provider.' teams', count($available[0]->teams)],
                    [$available[1]->provider.' teams', count($available[1]->teams)],
                    ['Matched', count($comparison['matched'])],
                    ['Missing left', count($comparison['missing_left'])],
                    ['Missing right', count($comparison['missing_right'])],
                    ['Warnings', count($comparison['warnings'])],
                ]);
            } elseif ($plan['mode'] === 'single_provider_validation') {
                $this->table(['Metric', 'Value'], [
                    ['Status', 'PASS'],
                    ['Mode', 'SINGLE PROVIDER VALIDATION'],
                    ['Selected provider', $available[0]->provider],
                    ['Teams', count($available[0]->teams)],
                    ['Unavailable providers', count($results) - 1],
                ]);
            } else {
                $this->table(['Metric', 'Value'], [
                    ['Status', 'FAIL'],
                    ['Mode', 'COVERAGE GAP'],
                    ['Available providers', 0],
                    ['Unavailable providers', count($results)],
                ]);
            }

            $report = [
                'status' => $plan['status'],
                'mode' => $plan['mode'],
                'providers' => array_map(fn ($result) => [
                    'provider' => $result->provider,
                    'available' => $result->available,
                    'teams_count' => count($result->teams),
                    'reason' => $result->reason,
                ], $results),
                'comparison' => $plan['comparison'] ?? null,
            ];

            if ((bool) $this->option('json')) {
                $this->newLine();
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return $plan['status'] === 'fail' ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
