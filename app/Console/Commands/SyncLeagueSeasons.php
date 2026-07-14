<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\FootballData\FootballDataClient;
use App\Services\Providers\TeamProviderRegistry;
use App\Services\Seasons\SeasonTimelinePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SyncLeagueSeasons extends Command
{
    protected $signature = 'season:sync
        {--league-id= : Internal league id}
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
        SeasonTimelinePlanner $planner,
    ): int {
        $leagueId = (int) $this->option('league-id');
        if ($leagueId <= 0 || ! DB::table('leagues')->where('id', $leagueId)->exists()) {
            $this->components->error('A valid --league-id is required.');
            return self::FAILURE;
        }

        $competition = strtoupper(trim((string) $this->option('competition')));
        $apiLeagueId = (int) $this->option('api-league-id');
        $history = $this->option('history') !== null
            ? max(0, (int) $this->option('history'))
            : (int) config('seasons.history_fallback', 4);
        $apply = (bool) $this->option('apply');

        try {
            $discoveries = [];

            try {
                $discoveries['football_data'] = $footballData->currentSeasonYear($competition);
            } catch (Throwable $e) {
                $discoveries['football_data'] = null;
            }

            try {
                $discoveries['api_football'] = $apiFootball->currentSeasonYear($apiLeagueId);
            } catch (Throwable $e) {
                $discoveries['api_football'] = null;
            }

            $availableYears = array_values(array_filter($discoveries, fn ($year) => is_int($year) && $year > 0));
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

                $existing = DB::table('league_seasons')
                    ->join('seasons', 'seasons.id', '=', 'league_seasons.season_id')
                    ->where('league_seasons.league_id', $leagueId)
                    ->where('seasons.season_key', $season['season_key'])
                    ->select('league_seasons.id', 'league_seasons.is_current')
                    ->first();

                $rows[] = [
                    ...$season,
                    'action' => $existing ? ((bool) $existing->is_current === $season['is_current'] ? 'UNCHANGED' : 'UPDATE_CURRENT') : 'CREATE',
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

            $this->table(['Season', 'Current', 'Action', 'Available providers'], array_map(
                fn ($row) => [
                    $row['label'],
                    $row['is_current'] ? 'YES' : 'NO',
                    $row['action'],
                    count(array_filter($row['providers'], fn ($provider) => $provider['available'])),
                ],
                $rows,
            ));

            if ($apply) {
                $this->apply($leagueId, $rows);
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

    /** @param list<array<string,mixed>> $rows */
    private function apply(int $leagueId, array $rows): void
    {
        DB::transaction(function () use ($leagueId, $rows): void {
            DB::table('league_seasons')->where('league_id', $leagueId)->update([
                'is_current' => false,
                'updated_at' => now(),
            ]);

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

                DB::table('league_seasons')->updateOrInsert(
                    ['league_id' => $leagueId, 'season_id' => $seasonId],
                    [
                        'is_current' => $row['is_current'],
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        });
    }
}
