<?php

namespace App\Console\Commands;

use App\Services\Tiers\TeamTieringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

final class AuditTeamTierPerformance extends Command
{
    protected $signature = 'team-tiers:audit-performance
        {--league-season-id= : Internal league_seasons id}
        {--json : Print full JSON report}';

    protected $description = 'Audit calculated team tier values against real season standings score.';

    public function handle(TeamTieringService $service): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonId = (int) $this->option('league-season-id');
            if ($leagueSeasonId <= 0) {
                throw new \RuntimeException('Provide --league-season-id.');
            }

            $report = $service->performanceAudit($leagueSeasonId);

            $this->tierLog('info', 'Team tier performance audit completed.', [
                'league_season_id' => $leagueSeasonId,
                'status' => $report['status'],
                'rows' => count($report['rows']),
            ]);

            $this->renderReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier performance audit failed.', [
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
            ['League season', $report['league_season']['league_name'].' '.$report['league_season']['season_label']],
            ['Rows', count($report['rows'])],
        ]);

        if ($report['rows'] !== []) {
            $this->table(['Team', 'Expected', 'Real', 'Delta', 'Pos', 'Pts', 'Status'], collect($report['rows'])->map(fn (array $row): array => [
                $row['team_name'],
                ($row['expected_tier'] ?? '-').' / '.($row['expected_score'] ?? '-'),
                ($row['actual_tier'] ?? '-').' / '.($row['actual_score'] ?? '-'),
                $row['score_delta'] ?? '-',
                $row['position'] ?? '-',
                $row['points'] ?? '-',
                strtoupper((string) $row['status']),
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
