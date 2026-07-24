<?php

namespace App\Console\Commands;

use App\Services\Tiers\TeamTieringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

final class AuditTeamTierWalkForward extends Command
{
    protected $signature = 'team-tiers:audit-walk-forward
        {--league-id= : Internal leagues id}
        {--league-season-id=* : Explicit league_seasons ids}
        {--json : Print full JSON report}';

    protected $description = 'Backtest team tiers using only seasons preceding each evaluated season.';

    public function handle(TeamTieringService $service): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonIds = $this->resolveLeagueSeasonIds();
            if ($leagueSeasonIds === []) {
                throw new \RuntimeException('Nessuna lega-stagione conclusa e verificabile trovata.');
            }

            $report = $service->walkForwardAudit($leagueSeasonIds);
            $this->tierLog('info', 'Team tier walk-forward audit completed.', [
                'league_season_ids' => $leagueSeasonIds,
                'status' => $report['status'],
                'summary' => $report['summary'],
            ]);

            $this->renderReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier walk-forward audit failed.', [
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
            ['Teams', $report['summary']['teams']],
            ['Ranking accuracy', $report['summary']['ranking_accuracy_pct'].'%'],
            ['Position MAE', $report['summary']['position_mae']],
            ['Exact tier', $report['summary']['exact_tier_pct'].'%'],
            ['Within one tier', $report['summary']['within_one_tier_pct'].'%'],
        ]);

        $this->table(
            ['Season', 'Teams', 'Ranking', 'MAE', 'Exact tier', 'Within one tier'],
            collect($report['seasons'])->map(fn (array $season): array => [
                $season['league_season']['league_name'].' '.$season['league_season']['season_label'],
                $season['metrics']['teams'],
                $season['metrics']['ranking_accuracy_pct'].'%',
                $season['metrics']['position_mae'],
                $season['metrics']['exact_tier_pct'].'%',
                $season['metrics']['within_one_tier_pct'].'%',
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
        File::append($this->logPath(), '['.now()->toIso8601String().'][tier_squadre]['.strtoupper($level).'] '.$message.$context.PHP_EOL);
    }

    private function logPath(): string
    {
        return storage_path('logs/administration/tier-squadre/tier-squadre.log');
    }
}
