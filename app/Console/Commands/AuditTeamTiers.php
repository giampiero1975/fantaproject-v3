<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

final class AuditTeamTiers extends Command
{
    protected $signature = 'team-tiers:audit
        {--league-season-id= : Internal league_seasons id}
        {--json : Print full JSON report}';

    protected $description = 'Audit readiness for team tier calculation using canonical teams and standings.';

    public function handle(): int
    {
        $this->initializeLog();

        try {
            $leagueSeasonId = (int) ($this->option('league-season-id') ?? 0);
            $report = $this->buildReport($leagueSeasonId > 0 ? $leagueSeasonId : null);

            $this->tierLog('info', 'Team tier readiness audit completed.', [
                'league_season_id' => $leagueSeasonId ?: null,
                'status' => $report['status'],
                'rows' => count($report['rows']),
            ]);

            $this->renderReport($report);

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->tierLog('error', 'Team tier readiness audit failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string,mixed> */
    private function buildReport(?int $leagueSeasonId): array
    {
        $settingsStatus = $this->settingsStatus();
        $lookback = $this->historicalLookback();
        $rows = $this->leagueSeasons($leagueSeasonId)
            ->map(fn (object $row): array => $this->auditLeagueSeason($row, $lookback, $settingsStatus['complete']))
            ->values()
            ->all();

        return [
            'status' => $this->statusFor($rows, $settingsStatus['complete']),
            'settings' => $settingsStatus,
            'required_historical_seasons' => $lookback,
            'rows' => $rows,
        ];
    }

    /** @return array{complete:bool,missing:list<string>,present:list<string>} */
    private function settingsStatus(): array
    {
        $required = [
            'lookback.historical',
            'lookback.momentum',
            'weights.historical',
            'weights.momentum',
            'weights.fusion',
            'weights.metrics',
            'divisors.historical',
            'divisors.momentum',
            'thresholds.by_tier',
            'league_multipliers.default',
            'rules.missing_season_score',
            'rules.trend_penalty',
        ];

        $present = DB::table('team_tier_settings')
            ->get(['setting_group', 'setting_key'])
            ->map(fn (object $row): string => $row->setting_group.'.'.$row->setting_key)
            ->all();

        $missing = array_values(array_diff($required, $present));

        return [
            'complete' => $missing === [],
            'missing' => $missing,
            'present' => $present,
        ];
    }

    private function historicalLookback(): int
    {
        $values = DB::table('team_tier_settings')
            ->where('setting_group', 'lookback')
            ->whereIn('setting_key', ['historical', 'momentum'])
            ->pluck('value')
            ->map(fn (string $value): int => (int) json_decode($value, true))
            ->all();

        return max($values ?: [4]);
    }

    /** @return Collection<int,object> */
    private function leagueSeasons(?int $leagueSeasonId): Collection
    {
        return DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->when($leagueSeasonId !== null, fn ($query) => $query->where('ls.id', $leagueSeasonId))
            ->select([
                'ls.id as league_season_id',
                'l.name as league_name',
                'c.name as country_name',
                's.season_key',
                's.label as season_label',
                'ls.is_current',
            ])
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByDesc('s.season_key')
            ->get();
    }

    /** @return array<string,mixed> */
    private function auditLeagueSeason(object $row, int $lookback, bool $settingsComplete): array
    {
        $teamIds = DB::table('league_season_teams')
            ->where('league_season_id', $row->league_season_id)
            ->where('is_active', true)
            ->pluck('team_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $seasonKeys = DB::table('seasons')
            ->where('season_key', '<', (int) $row->season_key)
            ->orderByDesc('season_key')
            ->limit($lookback)
            ->pluck('season_key')
            ->map(fn ($seasonKey): int => (int) $seasonKey)
            ->all();

        $expectedPairs = count($teamIds) * count($seasonKeys);
        $coveredPairs = $expectedPairs === 0 ? 0 : DB::table('league_season_team_standings as st')
            ->join('league_season_teams as lst', 'lst.id', '=', 'st.league_season_team_id')
            ->join('league_seasons as ls', 'ls.id', '=', 'lst.league_season_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->whereIn('lst.team_id', $teamIds)
            ->whereIn('s.season_key', $seasonKeys)
            ->distinct()
            ->count(DB::raw("lst.team_id || ':' || s.season_key"));

        $tierCount = DB::table('league_season_teams')
            ->where('league_season_id', $row->league_season_id)
            ->where('is_active', true)
            ->whereNotNull('tier_stagionale')
            ->count();

        $coveragePct = $expectedPairs > 0 ? round(($coveredPairs / $expectedPairs) * 100, 2) : 0.0;
        $status = $this->rowStatus($settingsComplete, count($teamIds), $expectedPairs, $coveredPairs);

        return [
            'status' => $status,
            'league_season_id' => (int) $row->league_season_id,
            'country_name' => $row->country_name,
            'league_name' => $row->league_name,
            'season_key' => (int) $row->season_key,
            'season_label' => $row->season_label,
            'is_current' => (bool) $row->is_current,
            'teams' => count($teamIds),
            'tiers_assigned' => (int) $tierCount,
            'historical_seasons' => $seasonKeys,
            'expected_standing_pairs' => $expectedPairs,
            'covered_standing_pairs' => (int) $coveredPairs,
            'standing_coverage_pct' => $coveragePct,
        ];
    }

    private function rowStatus(bool $settingsComplete, int $teams, int $expectedPairs, int $coveredPairs): string
    {
        if (! $settingsComplete) {
            return 'missing_settings';
        }

        if ($teams === 0) {
            return 'missing_teams';
        }

        if ($expectedPairs === 0 || $coveredPairs === 0) {
            return 'missing_history';
        }

        return $coveredPairs >= $expectedPairs ? 'ready' : 'partial_history';
    }

    /** @param list<array<string,mixed>> $rows */
    private function statusFor(array $rows, bool $settingsComplete): string
    {
        if (! $settingsComplete) {
            return 'missing_settings';
        }

        if ($rows === []) {
            return 'no_league_seasons';
        }

        return collect($rows)->every(fn (array $row): bool => $row['status'] === 'ready') ? 'ready' : 'attention_required';
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Settings', $report['settings']['complete'] ? 'complete' : 'missing: '.implode(', ', $report['settings']['missing'])],
            ['Required historical seasons', $report['required_historical_seasons']],
            ['Rows', count($report['rows'])],
        ]);

        if ($report['rows'] !== []) {
            $this->table(['League season', 'Teams', 'Tier', 'History', 'Coverage', 'Status'], collect($report['rows'])->map(fn (array $row): array => [
                $row['league_name'].' '.$row['season_label'],
                $row['teams'],
                $row['tiers_assigned'].'/'.$row['teams'],
                $row['covered_standing_pairs'].'/'.$row['expected_standing_pairs'],
                $row['standing_coverage_pct'].'%',
                strtoupper($row['status']),
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
