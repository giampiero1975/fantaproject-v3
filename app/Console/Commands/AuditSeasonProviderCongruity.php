<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\ApiFootballClient;
use App\Services\FootballData\FootballDataClient;
use App\Services\Seasons\TeamCongruityValidator;
use App\Services\Seasons\TeamPayloadNormalizer;
use Illuminate\Console\Command;
use Throwable;

final class AuditSeasonProviderCongruity extends Command
{
    protected $signature = 'season:audit-providers
        {--competition=SA : football-data.org competition code}
        {--league-id=135 : API-Football league id}
        {--season=2024 : season start year}
        {--json : print full JSON report}';

    protected $description = 'Dry-run comparison of season teams returned by football-data.org and API-Football.';

    public function handle(
        FootballDataClient $footballData,
        ApiFootballClient $apiFootball,
        TeamPayloadNormalizer $normalizer,
        TeamCongruityValidator $validator,
    ): int {
        $competition = strtoupper(trim((string) $this->option('competition')));
        $leagueId = (int) $this->option('league-id');
        $season = (int) $this->option('season');

        $this->components->info("DRY-RUN {$competition} / API-Football {$leagueId} / season {$season}");
        $this->line('No database writes will be performed.');

        try {
            $leftPayload = $footballData->teams($competition, $season);
            $rightPayload = $apiFootball->teams($leagueId, $season);

            $left = $normalizer->fromFootballData($leftPayload);
            $right = $normalizer->fromApiFootball($rightPayload);
            $report = $validator->compare($left, $right);

            $this->table(['Metric', 'Value'], [
                ['Status', strtoupper($report['status'])],
                ['football-data.org teams', $report['left_count']],
                ['API-Football teams', $report['right_count']],
                ['Matched', count($report['matched'])],
                ['Missing in football-data.org', count($report['missing_left'])],
                ['Missing in API-Football', count($report['missing_right'])],
                ['Warnings', count($report['warnings'])],
            ]);

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
}
