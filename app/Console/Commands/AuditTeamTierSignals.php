<?php

namespace App\Console\Commands;

use App\Services\Tiers\TeamTierSignalAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

final class AuditTeamTierSignals extends Command
{
    protected $signature = 'team-tiers:audit-signals
        {--league-id= : Internal leagues id}
        {--league-season-id=* : Explicit league_seasons ids}
        {--persist : Save the audit dataset in ai_ tables}
        {--json : Print full JSON report}';

    protected $description = 'Extract prospective tier signals for conceptual and future AI analysis.';

    public function handle(TeamTierSignalAuditService $service): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonIds = $this->resolveLeagueSeasonIds();
            $report = $service->analyze($leagueSeasonIds);

            if ((bool) $this->option('persist')) {
                $report = $service->persist($report);
            }

            $this->tierLog('info', 'Team tier signal audit completed.', [
                'league_season_ids' => $leagueSeasonIds,
                'persisted' => (bool) $this->option('persist'),
                'audit_run' => $report['audit_run'] ?? null,
                'summary' => $report['summary'],
            ]);

            $this->renderReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier signal audit failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @return list<int> */
    private function resolveLeagueSeasonIds(): array
    {
        $explicitIds = collect((array) $this->option('league-season-id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($explicitIds !== []) {
            return $explicitIds;
        }

        $leagueId = (int) $this->option('league-id');
        if ($leagueId <= 0) {
            throw new \RuntimeException('Indica --league-id oppure almeno un --league-season-id.');
        }

        return DB::table('league_seasons as ls')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->where('ls.league_id', $leagueId)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('league_season_teams as lst')
                    ->join('league_season_team_standings as st', 'st.league_season_team_id', '=', 'lst.id')
                    ->whereColumn('lst.league_season_id', 'ls.id');
            })
            ->orderBy('s.season_key')
            ->pluck('ls.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Seasons', $report['summary']['seasons']],
            ['Observations', count($report['observations'])],
            ['Ranking accuracy', $report['summary']['ranking_accuracy_pct'].'%'],
            ['Position MAE', $report['summary']['position_mae']],
            ['Persisted run', $report['audit_run']['uuid'] ?? 'NO'],
        ]);

        $this->table(
            ['Signal', 'Samples', 'Pearson r', '|r|'],
            collect($report['signals'])->map(fn (array $signal): array => [
                $signal['label'],
                $signal['sample_count'],
                $signal['pearson_correlation'] ?? '-',
                $signal['absolute_correlation'] ?? '-',
            ])->all()
        );
    }

    private function initializeLog(): void
    {
        File::ensureDirectoryExists(dirname($this->logPath()));
        File::put($this->logPath(), '');
    }

    private function tierLog(string $level, string $message, array $context = []): void
    {
        $context = $context === [] ? '' : ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::append($this->logPath(), '['.now()->toIso8601String().'][tier_squadre][audit_signals]['.strtoupper($level).'] '.$message.$context.PHP_EOL);
    }

    private function logPath(): string
    {
        return storage_path('logs/administration/tier-squadre/tier-squadre.log');
    }
}
