<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
use App\Services\Providers\TeamProviderRegistry;
use App\Services\Seasons\TeamCongruityValidator;
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

    public function handle(
        TeamProviderRegistry $registry,
        TeamCongruityValidator $validator,
    ): int {
        $competition = strtoupper(trim((string) $this->option('competition')));
        $leagueId = (int) $this->option('league-id');
        $season = (int) $this->option('season');

        $request = new TeamDataRequest($season, [
            'football_data' => $competition,
            'api_football' => $leagueId,
        ]);

        $this->components->info("DRY-RUN teams / season {$season}");
        $this->line('No database writes will be performed.');

        try {
            $results = [];
            foreach ($registry->all() as $provider) {
                $results[] = $provider->fetchTeams($request);
            }

            $available = array_values(array_filter($results, fn ($result) => $result->available));
            $unavailable = array_values(array_filter($results, fn ($result) => ! $result->available));

            if (count($available) >= 2) {
                $comparison = $validator->compare($available[0]->teams, $available[1]->teams);
                $report = [
                    'status' => $comparison['status'],
                    'mode' => 'multi_provider_congruity',
                    'providers' => $this->providerSummary($results),
                    'comparison' => $comparison,
                ];

                $this->table(['Metric', 'Value'], [
                    ['Status', strtoupper($report['status'])],
                    ['Mode', 'MULTI PROVIDER CONGRUITY'],
                    [$available[0]->provider.' teams', count($available[0]->teams)],
                    [$available[1]->provider.' teams', count($available[1]->teams)],
                    ['Matched', count($comparison['matched'])],
                    ['Missing left', count($comparison['missing_left'])],
                    ['Missing right', count($comparison['missing_right'])],
                    ['Warnings', count($comparison['warnings'])],
                ]);
            } elseif (count($available) === 1) {
                $report = [
                    'status' => 'pass',
                    'mode' => 'single_provider_validation',
                    'providers' => $this->providerSummary($results),
                    'selected_provider' => $available[0]->provider,
                    'teams_count' => count($available[0]->teams),
                ];

                $this->table(['Metric', 'Value'], [
                    ['Status', 'PASS'],
                    ['Mode', 'SINGLE PROVIDER VALIDATION'],
                    ['Selected provider', $available[0]->provider],
                    ['Teams', count($available[0]->teams)],
                    ['Unavailable providers', count($unavailable)],
                ]);
            } else {
                $report = [
                    'status' => 'fail',
                    'mode' => 'coverage_gap',
                    'providers' => $this->providerSummary($results),
                ];

                $this->table(['Metric', 'Value'], [
                    ['Status', 'FAIL'],
                    ['Mode', 'COVERAGE GAP'],
                    ['Available providers', 0],
                    ['Unavailable providers', count($unavailable)],
                ]);
            }

            if ((bool) $this->option('json')) {
                $this->newLine();
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return $report['status'] === 'fail' ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @param array<int,object> $results */
    private function providerSummary(array $results): array
    {
        return array_map(fn ($result) => [
            'provider' => $result->provider,
            'available' => $result->available,
            'teams_count' => count($result->teams),
            'reason' => $result->reason,
        ], $results);
    }
}
