<?php

namespace App\Console\Commands;

use App\Data\Providers\SeasonDataRequest;
use App\Services\Providers\SeasonProviderRegistry;
use App\Services\Seasons\LeagueMappingResolver;
use App\Services\Seasons\SeasonTimelinePlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        SeasonProviderRegistry $registry,
        LeagueMappingResolver $leagueResolver,
        SeasonTimelinePlanner $planner,
    ): int {
        $providerReferences = $this->providerReferences();
        $history = $this->option('history') !== null
            ? max(0, (int) $this->option('history'))
            : (int) config('seasons.history_fallback', 4);
        $apply = (bool) $this->option('apply');

        $this->initializeSeasonSyncLog();
        $this->seasonSyncLog('info', 'Season sync started.', [
            'mode' => $apply ? 'apply' : 'dry_run',
            'history' => $history,
            'provider_references' => $providerReferences,
        ]);

        try {
            $leagueId = $this->resolveLeagueId($leagueResolver, $providerReferences);
            if ($providerReferences === []) {
                $providerReferences = $this->providerReferencesFromLeague($leagueId);
            }
            $currentSeason = $this->option('season') !== null
                ? (int) $this->option('season')
                : (int) now()->year;
            $timeline = $planner->build($currentSeason, $history);
            $rows = [];
            $providers = $registry->all();
            $providerIds = DB::table('data_providers')
                ->whereIn('code', array_keys($providerReferences))
                ->pluck('id', 'code')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $this->seasonSyncLog('info', 'Runtime context resolved.', [
                'league_id' => $leagueId,
                'current_season' => $currentSeason,
                'timeline_count' => count($timeline),
                'season_provider_count' => count($providers),
                'season_providers' => array_map(fn ($provider): string => $provider->key(), $providers),
            ]);

            foreach ($timeline as $season) {
                $request = new SeasonDataRequest($season['season_key'], $providerReferences);

                $this->seasonSyncLog('info', 'Season analysis started.', [
                    'season_key' => $season['season_key'],
                    'label' => $season['label'],
                ]);

                $providerResults = [];
                foreach ($providers as $provider) {
                    $result = $provider->fetchSeason($request);
                    $providerResults[] = [
                        'provider' => $result->provider,
                        'available' => $result->available,
                        'external_id' => $result->season?->externalId,
                        'start_date' => $result->season?->startDate,
                        'end_date' => $result->season?->endDate,
                        'metadata' => $result->season?->metadata ?? [],
                        'reason' => $result->reason,
                    ];

                    $this->seasonSyncLog($result->available ? 'info' : 'warning', 'Provider season result.', [
                        'season_key' => $season['season_key'],
                        'provider' => $result->provider,
                        'available' => $result->available,
                        'external_id' => $result->season?->externalId,
                        'start_date' => $result->season?->startDate,
                        'end_date' => $result->season?->endDate,
                        'reason' => $result->reason,
                    ]);
                }

                $availableSeason = collect($providerResults)
                    ->first(fn (array $provider): bool => (bool) $provider['available']);
                $availableSeasonWithDates = collect($providerResults)
                    ->first(fn (array $provider): bool => (bool) $provider['available']
                        && $provider['start_date'] !== null
                        && $provider['end_date'] !== null);

                if ($availableSeason === null) {
                    $this->seasonSyncLog('warning', 'No provider available for season coverage.', [
                        'season_key' => $season['season_key'],
                        'provider_results' => collect($providerResults)
                            ->map(fn (array $provider): array => [
                                'provider' => $provider['provider'],
                                'available' => $provider['available'],
                                'reason' => $provider['reason'],
                            ])
                            ->all(),
                    ]);
                } elseif ($availableSeasonWithDates === null) {
                    $this->seasonSyncLog('warning', 'Season covered without dates.', [
                        'season_key' => $season['season_key'],
                        'provider' => $availableSeason['provider'],
                    ]);
                } else {
                    $this->seasonSyncLog('info', 'Season dates selected from provider.', [
                        'season_key' => $season['season_key'],
                        'provider' => $availableSeasonWithDates['provider'],
                        'start_date' => $availableSeasonWithDates['start_date'],
                        'end_date' => $availableSeasonWithDates['end_date'],
                    ]);
                }

                $existing = DB::table('league_seasons')
                    ->join('seasons', 'seasons.id', '=', 'league_seasons.season_id')
                    ->where('league_seasons.league_id', $leagueId)
                    ->where('seasons.season_key', $season['season_key'])
                    ->select('league_seasons.id', 'league_seasons.is_current', 'league_seasons.start_date', 'league_seasons.end_date')
                    ->first();

                $dates = [
                    'start_date' => $availableSeasonWithDates['start_date'] ?? ($existing ? $this->dateValue($existing->start_date) : null),
                    'end_date' => $availableSeasonWithDates['end_date'] ?? ($existing ? $this->dateValue($existing->end_date) : null),
                ];

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
                    $providerMappingsChanged = $this->providerMappingsChanged(
                        (int) $existing->id,
                        $providerResults,
                        $providerIds,
                        $providerReferences,
                        (int) $season['season_key'],
                    );
                    $action = $unchanged && ! $providerMappingsChanged ? 'UNCHANGED' : 'UPDATE';
                }

                $rows[] = [
                    ...$season,
                    ...$dates,
                    'action' => $action,
                    'providers' => $providerResults,
                ];

                $this->seasonSyncLog('info', 'Season action planned.', [
                    'season_key' => $season['season_key'],
                    'action' => $action,
                    'start_date' => $dates['start_date'],
                    'end_date' => $dates['end_date'],
                ]);
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
                    $this->providerCoverageLabel($row['providers']),
                ],
                $rows,
            ));

            if ($apply) {
                $this->seasonSyncLog('info', 'Applying season sync changes.', [
                    'league_id' => $leagueId,
                    'rows' => count($rows),
                ]);
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
            $this->seasonSyncLog('error', 'Season sync failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
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

    /**
     * @return array<string,string>
     */
    private function providerReferencesFromLeague(int $leagueId): array
    {
        return DB::table('league_provider_mappings as lpm')
            ->join('data_providers as p', 'p.id', '=', 'lpm.data_provider_id')
            ->join('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->where('lpm.league_id', $leagueId)
            ->where('rc.is_enabled', true)
            ->pluck('lpm.external_id', 'p.code')
            ->map(fn ($externalId): string => (string) $externalId)
            ->all();
    }

    private function dateValue(mixed $value): ?string
    {
        return $value === null ? null : substr((string) $value, 0, 10);
    }

    /**
     * @param  list<array<string,mixed>>  $providers
     */
    private function providerCoverageLabel(array $providers): string
    {
        $candidateProviders = array_filter(
            $providers,
            fn (array $provider): bool => ($provider['reason'] ?? null) !== 'missing_provider_reference',
        );
        $available = count(array_filter($candidateProviders, fn (array $provider): bool => (bool) $provider['available']));

        if ($candidateProviders === []) {
            return '0/0';
        }

        return $available.'/'.count($candidateProviders);
    }

    /**
     * @param  list<array<string,mixed>>  $providerResults
     * @param  array<string,int>  $providerIds
     * @param  array<string,string>  $providerReferences
     */
    private function providerMappingsChanged(
        int $leagueSeasonId,
        array $providerResults,
        array $providerIds,
        array $providerReferences,
        int $seasonKey,
    ): bool {
        $existingMappings = DB::table('league_season_provider_mappings')
            ->where('league_season_id', $leagueSeasonId)
            ->get()
            ->keyBy('data_provider_id');

        $candidateProviderIds = array_values($providerIds);

        foreach ($existingMappings as $mapping) {
            if (! in_array((int) $mapping->data_provider_id, $candidateProviderIds, true)) {
                return true;
            }
        }

        foreach ($providerResults as $provider) {
            $providerCode = (string) $provider['provider'];

            if (! isset($providerIds[$providerCode])) {
                continue;
            }

            $providerId = $providerIds[$providerCode];
            $existing = $existingMappings->get($providerId);

            if (! (bool) $provider['available']) {
                if ($existing !== null) {
                    return true;
                }

                continue;
            }

            if ($existing === null) {
                return true;
            }

            if ((string) $existing->external_id !== (string) ($providerReferences[$providerCode] ?? '')) {
                return true;
            }

            if ((int) $existing->external_year !== $seasonKey) {
                return true;
            }
        }

        return false;
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

                DB::table('league_season_provider_mappings')
                    ->where('league_season_id', $leagueSeasonId)
                    ->whereNotIn('data_provider_id', $providerIds->values()->all())
                    ->delete();

                foreach ($row['providers'] as $provider) {
                    if (! isset($providerIds[$provider['provider']])) {
                        continue;
                    }

                    if (! $provider['available']) {
                        DB::table('league_season_provider_mappings')
                            ->where('league_season_id', $leagueSeasonId)
                            ->where('data_provider_id', $providerIds[$provider['provider']])
                            ->delete();
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
                            'metadata' => json_encode([
                                'season_external_id' => $provider['external_id'] ?? null,
                                'start_date' => $provider['start_date'] ?? null,
                                'end_date' => $provider['end_date'] ?? null,
                                'payload' => $provider['metadata'] ?? [],
                            ], JSON_UNESCAPED_UNICODE),
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

    private function initializeSeasonSyncLog(): void
    {
        $directory = storage_path('logs/administration/season_management');

        File::ensureDirectoryExists($directory);
        File::put($this->seasonSyncLogPath(), '');
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function seasonSyncLog(string $level, string $message, array $context = []): void
    {
        $level = strtoupper(preg_replace('/[^a-z]+/', '', strtolower($level)) ?: 'info');
        $timestamp = now()->format('Y-m-d H:i:s');
        $context = $context === []
            ? ''
            : ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        File::append($this->seasonSyncLogPath(), "[{$timestamp}][season_sync][{$level}] {$message}{$context}".PHP_EOL);
    }

    private function seasonSyncLogPath(): string
    {
        return storage_path('logs/administration/season_management/season_sync.log');
    }
}
