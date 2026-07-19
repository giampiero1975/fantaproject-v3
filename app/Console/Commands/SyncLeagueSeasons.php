<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
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
        {--provider-ref=* : Provider reference as provider_code=external_id}
        {--season= : Current season start year override}
        {--history= : Override SEASON_HISTORY_FALLBACK}
        {--apply : Persist planned changes}
        {--json : Print full JSON report}';

    protected $description = 'Discover and synchronize the current league season and configured historical fallback.';

    public function handle(
        TeamProviderRegistry $registry,
        LeagueMappingResolver $leagueResolver,
        SeasonTimelinePlanner $planner,
    ): int {
        $providerReferences = $this->providerReferences();
        $history = $this->option('history') !== null
            ? max(0, (int) $this->option('history'))
            : (int) config('seasons.history_fallback', 4);
        $apply = (bool) $this->option('apply');

        try {
            $leagueId = $this->resolveLeagueId($leagueResolver, $providerReferences);
            $currentSeason = $this->option('season') !== null
                ? (int) $this->option('season')
                : (int) now()->year;
            $timeline = $planner->build($currentSeason, $history);
            $rows = [];

            foreach ($timeline as $season) {
                $request = new TeamDataRequest($season['season_key'], $providerReferences);

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

                $dates = ['start_date' => null, 'end_date' => null];

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
                'provider_references' => $providerReferences,
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
                $this->apply($leagueId, $rows, $providerReferences);
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

    /**
     * @param  array<string, string>  $providerReferences
     */
    private function resolveLeagueId(LeagueMappingResolver $resolver, array $providerReferences): int
    {
        $override = (int) $this->option('league-id');
        if ($override > 0) {
            if (! DB::table('leagues')->where('id', $override)->exists()) {
                throw new RuntimeException('The provided --league-id does not exist.');
            }
            return $override;
        }

        if ($providerReferences === []) {
            throw new RuntimeException('No provider references configured for this sync.');
        }

        return $resolver->resolve($providerReferences);
    }

    private function dateValue(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 10);
    }

    /** @param list<array<string,mixed>> $rows */
    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  array<string, string>  $providerReferences
     */
    private function apply(int $leagueId, array $rows, array $providerReferences): void
    {
        DB::transaction(function () use ($leagueId, $rows, $providerReferences): void {
            DB::table('league_seasons')->where('league_id', $leagueId)->update([
                'is_current' => false,
                'updated_at' => now(),
            ]);

            $providerIds = DB::table('data_providers')
                ->whereIn('code', array_keys($providerReferences))
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

                    DB::table('league_season_provider_mappings')->updateOrInsert(
                        [
                            'league_season_id' => $leagueSeasonId,
                            'data_provider_id' => $providerIds[$provider['provider']],
                        ],
                        [
                            'external_id' => (string) $providerReferences[$provider['provider']],
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

    /**
     * @return array<string, string>
     */
    private function providerReferences(): array
    {
        $references = [];

        foreach ((array) $this->option('provider-ref') as $reference) {
            [$provider, $externalId] = array_pad(explode('=', (string) $reference, 2), 2, '');
            $provider = trim($provider);
            $externalId = trim($externalId);

            if ($provider !== '' && $externalId !== '') {
                $references[$provider] = $externalId;
            }
        }

        return $references;
    }
}
