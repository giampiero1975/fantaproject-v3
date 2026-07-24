<?php

namespace App\Console\Commands;

use App\Services\Tiers\TeamTierAutoTuningAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

final class AuditTeamTierAutoTuning extends Command
{
    protected $signature = 'team-tiers:audit-auto-tuning
        {--league-id= : Internal leagues id}
        {--league-season-id=* : Explicit league_seasons ids}
        {--persist : Save validation in ai_ tables}
        {--json : Print full JSON report}';

    protected $description = 'Validate the active tier auto-tuning profile against its DB baseline.';

    public function handle(TeamTierAutoTuningAuditService $service): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonIds = $this->resolveLeagueSeasonIds();
            $report = $service->analyze($leagueSeasonIds);

            if ((bool) $this->option('persist')) {
                $report = $service->persist($report);
            }

            $this->tierLog('info', 'Team tier auto-tuning audit completed.', [
                'league_season_ids' => $leagueSeasonIds,
                'status' => $report['status'],
                'persisted' => (bool) $this->option('persist'),
                'audit_run' => $report['audit_run'] ?? null,
            ]);

            $this->renderReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier auto-tuning audit failed.', [
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
        $candidates = collect($report['candidates']);
        $incremental = $candidates
            ->filter(fn (array $candidate): bool => str_starts_with($candidate['profile_key'], 'incremental_'))
            ->sortByDesc(fn (array $candidate): float => (float) $candidate['summary']['ranking_accuracy_pct'])
            ->values();
        $visibleCandidates = $candidates
            ->reject(fn (array $candidate): bool => str_starts_with($candidate['profile_key'], 'incremental_'))
            ->concat($incremental->take(10))
            ->values();

        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Incremental candidates tested', $incremental->count()],
            ['Accepted incremental candidates', $report['accepted_incremental_candidates']],
            ['Minimum average uplift', $report['guards']['min_average_ranking_uplift_pct'].'%'],
            ['Maximum season drop', $report['guards']['max_single_season_ranking_drop_pct'].'%'],
            ['Maximum MAE increase', $report['guards']['max_position_mae_increase']],
            ['Persisted run', $report['audit_run']['uuid'] ?? 'NO'],
        ]);

        $this->table(
            ['Profile', 'Ranking', 'Uplift', 'MAE', 'MAE delta', 'Accepted', 'Reason'],
            $visibleCandidates->map(fn (array $candidate): array => [
                $candidate['label'],
                $candidate['summary']['ranking_accuracy_pct'].'%',
                $candidate['comparison']['ranking_uplift_pct'].'%',
                $candidate['summary']['position_mae'],
                $candidate['comparison']['position_mae_delta'],
                $candidate['accepted'] ? 'YES' : 'NO',
                $candidate['rejection_reason'] ?? '-',
            ])->all()
        );

        $detailCandidates = $candidates
            ->reject(fn (array $candidate): bool => str_starts_with($candidate['profile_key'], 'incremental_'))
            ->concat($incremental->take(1))
            ->values();

        foreach ($detailCandidates as $candidate) {
            $this->line('');
            $this->components->info($candidate['label']);
            $this->table(
                ['Season', 'Ranking', 'Uplift', 'MAE', 'Exact tier', 'Within one'],
                collect($candidate['seasons'])->map(fn (array $season): array => [
                    $season['season_label'],
                    $season['ranking_accuracy_pct'].'%',
                    $season['ranking_uplift_pct'].'%',
                    $season['position_mae'],
                    $season['exact_tier_pct'].'%',
                    $season['within_one_tier_pct'].'%',
                ])->all()
            );
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
        File::append($this->logPath(), '['.now()->toIso8601String().'][tier_squadre][auto_tuning]['.strtoupper($level).'] '.$message.$context.PHP_EOL);
    }

    private function logPath(): string
    {
        return storage_path('logs/administration/tier-squadre/tier-squadre.log');
    }
}
