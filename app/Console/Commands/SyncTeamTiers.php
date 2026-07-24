<?php

namespace App\Console\Commands;

use App\Services\Tiers\TeamTieringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class SyncTeamTiers extends Command
{
    protected $signature = 'team-tiers:sync
        {--league-season-id= : Internal league_seasons id}
        {--apply : Persist planned tier changes}
        {--json : Print full JSON report}';

    protected $description = 'Calculate and synchronize team tiers using canonical standings.';

    public function handle(TeamTieringService $service): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonId = (int) $this->option('league-season-id');
            if ($leagueSeasonId <= 0) {
                throw new \RuntimeException('Provide --league-season-id.');
            }

            $report = $service->analyze($leagueSeasonId);
            $report['mode'] = $this->option('apply') ? 'apply' : 'dry_run';

            $this->tierLog('info', 'Team tier sync analyzed.', [
                'mode' => $report['mode'],
                'league_season_id' => $leagueSeasonId,
                'status' => $report['status'],
                'rows' => count($report['rows']),
            ]);

            $this->renderReport($report);

            if ($this->option('apply')) {
                $service->apply($report);
                $this->tierLog('info', 'Team tier sync applied.', ['rows' => count($report['rows'])]);
                $this->components->info('Team tiers synchronized.');
            } else {
                $this->components->info('DRY-RUN complete. No database writes were performed.');
            }

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier sync failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Mode', strtoupper((string) $report['mode'])],
            ['League season', $report['league_season']['league_name'].' '.$report['league_season']['season_label']],
            ['Rows', count($report['rows'])],
        ]);

        if ($report['rows'] !== []) {
            $this->table(['Team', 'Tier', 'Score', 'Hist', 'Momentum', 'Missing', 'Action'], collect($report['rows'])->map(fn (array $row): array => [
                $row['team_name'],
                $row['tier'],
                $row['score'],
                $row['historical_component'],
                $row['momentum_component'],
                $row['missing_seasons'],
                $row['action'],
            ])->all());
        }
    }

    private function initializeLog(): void
    {
        File::ensureDirectoryExists(dirname($this->logPath()));
        File::put($this->logPath(), '');
    }

    private function tierLog(string $level, string $message, array $context = []): void
    {
        $context = $context === [] ? '' : ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::append($this->logPath(), '['.now()->toIso8601String().'][tier_squadre]['.strtoupper($level).'] '.$message.$context.PHP_EOL);
    }

    private function logPath(): string
    {
        return storage_path('logs/administration/tier-squadre/tier-squadre.log');
    }
}
