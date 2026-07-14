<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\FootballData\FootballDataClient;
use App\Services\Providers\TeamProviderRegistry;
use App\Services\Seasons\LeagueMappingResolver;
use App\Services\Seasons\SeasonTimelinePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SyncLeagueSeasons extends Command
{
    protected $signature = 'season:sync
        {--league-id= : Optional internal league id override}
        {--competition=SA : football-data.org competition reference}
        {--api-league-id=135 : API-Football league reference}
        {--history= : Override SEASON_HISTORY_FALLBACK}
        {--apply : Persist planned changes}
        {--json : Print full JSON report}';

    protected $description = 'Discover and synchronize the current league season and configured historical fallback.';

    public function handle(
        FootballDataClient $footballData,
        ApiFootballClient $apiFootball,
        TeamProviderRegistry $registry,
        LeagueMappingResolver $leagueResolver,
        SeasonTimelinePlanner $planner,
    ): int {
        $competition = strtoupper(trim((string) $this->option('competition')));
        $apiLeagueId = (int) $this->option('api-league-id');
        $history = $this->option('history') !== null
            ? max(0, (int) $this->option('history'))
            : (int) config('seasons.history_fallback', 4);
        $apply = (bool) $this->option('apply');

        try {
            $leagueId = $this->resolveLeagueId($leagueResolver, $competition, $apiLeagueId);
            $discoveries = [];

            try {
                $discoveries['football_data'] = $footballData->currentSeasonInfo($competition);
            } catch (Throwable) {
                $discoveries['football_data'] = null;
            }

            try {
                $discoveries['api_football'] = $apiFootball->currentSeasonInfo($apiLeagueId);
            } catch (Throwable) {
                $discoveries['api_football'] = null;
            }

            $availableYears = array_values(array_filter(array_map(
                fn ($info) => is_array($info) ? ($info['year'] ?? null) : null,
                $discoveries,
            ), fn ($year) => is_int($year) && $year > 0));

            if ($availableYears === []) {
                throw new RuntimeException('No registered provider could discover the current season.');
            }

            $currentSeason = max($availableYears);
            $timeline = $planner->build($currentSeason, $history);
            $rows = [];

            foreach ($timeline as $season) {
                $request = new TeamDataRequest($season['season_key'], [
                    'football_data' => $competition,
                    'api_football' => $apiLeagueId,
                ]);

                $providerResults = [];
                foreach ($registry->all() as $provider) {
                    $result = $provider->fetchTeams($request);
                    $providerResults[] = [
                        'provider' => $result->provider,
                        'available' => $result->available,
                        'teams_count' => count($result->teams),
                        'reason' => $result->reason,
                    ];
                }

                $dates = $this->resolveDates($footballData, $apiFootball, $competition, $apiLeagueId, $season['season_key']);

                $existing = DB::table('league_seasons')
                    ->join('seasons', 'seasons.id', '=', 'league_seasons.season_id')
                    ->where('league_seasons.league_id', $leagueId)
                    ->where('seasons.season_key', $season['season_key'])
                    ->select('league_seasons.id', 'league_seasons.is_current', 'league_seasons.start_date', 'league_seasons.end_date')
                    ->first();

                $desired = [
                    'is_current' => $season['is_current'],
                    'start_date' => $dates['start_date'],
                    'end_date' => $dates['end_date'],
                ];

                $action = 'CREATE';
                if ($existing) {
                    $unchanged = (bool) $existing->is_current === $desired['is_current']
                        && $this->dateValue($existing->start_date) === $desired['start_date']
                        && $this->dateValue($existing->end_date) === $desired['end_date'];
                    $action = $unchanged ? 'UNCHANGED' : 'UPDATE';
                }

                $rows[] = [
                    ...$season,
                    ...$dates,
                    'action' => $action,
                    'providers' => $providerResults,
                ];
            }

            $report = [
                'status' => 'pass',
                'mode' => $apply ? 'apply' : 'dry_run',
                'league_id' => $leagueId,
                'history_fallback' => $history,
                'current_season' => $currentSeason,
                'discoveries' => $discoveries,
                'timeline' => $rows,
            ];

            $this->table(['Season', 'Current', 'Start', 'End', 'Action', 'Providers'], array_map(
                fn ($row) => [
                    $row['label'],
                    $row['is_current'] ? 'YES' : 'NO',
                    $row['start_date'] ?? '-',
                    $row['end_date'] ?? '-',
                    $row['action'],
                    count(array_filter($row['providers'], fn ($provider) => $provider['available'])),
                ],
                $rows,
            ));

            if ($apply) {
                $this->apply($leagueId, $rows, $competition, $apiLeagueId);
                $this->components->info('League season timeline synchronized.');
            } else {
                $this->components->info('DRY-RUN complete. No database writes were performed.');
            }

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->components->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveLeagueId(LeagueMappingResolver $resolver, string $competition, int $apiLeagueId): int
    {
        $override = (int) $this->option('league-id');
        if ($override > 0) {
            if (! DB::table('leagues')->where('id', $override)->exists()) {
                throw new RuntimeException('The provided --league-id does not exist.');
            }
            return $override;
        }

        return $resolver->resolve([
            'football_data' => $competition,
            'api_football' => $apiLeagueId,
        ]);
    }

    /** @return array{start_date:?string,end_date:?string} */
    private function resolveDates(
        FootballDataClient $footballData,
        ApiFootballClient $apiFootball,
        string $competition,
        int $apiLeagueId,
        int $seasonYear,
    ): array {
        $candidates = [];

        try {
            $candidates[] = $footballData->seasonDates($competition, $seasonYear);
        } catch (Throwable) {
        }

        try {
            $candidates[] = $apiFootball->seasonDates($apiLeagueId, $seasonYear);
        } catch (Throwable) {
        }

        foreach ($candidates as $dates) {
            if (($dates['start_date'] ?? null) !== null || ($dates['end_date'] ?? null) !== null) {
                return $dates;
            }
        }

        return ['start_date' => null, 'end_date' => null];
    }

    private function dateValue(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 10);
    }

    /** @param list<array<string,mixed>> $rows */
    private function apply(int $leagueId, array $rows, string $competition, int $apiLeagueId): void
    {
        DB::transaction(function () use ($leagueId, $rows, $competition, $apiLeagueId): void {
            DB::table('league_seasons')->where('league_id', $leagueId)->update([
                'is_current' => false,
                'updated_at' => now(),
            ]);

            $providerIds = DB::table('data_providers')
                ->whereIn('code', ['football_data', 'api_football'])
                ->pluck('id', 'code');

            foreach ($rows as $row) {
                $seasonId = DB::table('seasons')->where('season_key', $row['season_key'])->value('id');

                if (! $seasonId) {
                    $seasonId = DB::table('seasons')->insertGetId([
                        'season_key' => $row['season_key'],
                        'label' => $row['label'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $leagueSeason = DB::table('league_seasons')
                    ->where('league_id', $leagueId)
                    ->where('season_id', $seasonId)
                    ->first();

                $values = [
                    'is_current' => $row['is_current'],
                    'status' => 'active',
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date'],
                    'updated_at' => now(),
                ];

                if ($leagueSeason) {
                    DB::table('league_seasons')->where('id', $leagueSeason->id)->update($values);
                    $leagueSeasonId = (int) $leagueSeason->id;
                } else {
                    $leagueSeasonId = DB::table('league_seasons')->insertGetId($values + [
                        'league_id' => $leagueId,
                        'season_id' => $seasonId,
                        'created_at' => now(),
                    ]);
                }

                foreach ($row['providers'] as $provider) {
                    if (! $provider['available'] || ! isset($providerIds[$provider['provider']])) {
                        continue;
                    }

                    $externalId = $provider['provider'] === 'football_data' ? $competition : (string) $apiLeagueId;
                    DB::table('league_season_provider_mappings')->updateOrInsert(
                        [
                            'league_season_id' => $leagueSeasonId,
                            'data_provider_id' => $providerIds[$provider['provider']],
                        ],
                        [
                            'external_id' => $externalId,
                            'external_year' => $row['season_key'],
                            'metadata' => json_encode(['teams_count' => $provider['teams_count']], JSON_UNESCAPED_UNICODE),
                            'verified_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });
    }
}
