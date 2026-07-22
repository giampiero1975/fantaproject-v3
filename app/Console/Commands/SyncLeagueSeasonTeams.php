<?php

namespace App\Console\Commands;

use App\Data\Providers\TeamDataRequest;
use App\Data\Seasons\CanonicalTeamData;
use App\Services\Matching\NameSimilarityMatcher;
use App\Services\Providers\TeamProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class SyncLeagueSeasonTeams extends Command
{
    protected $signature = 'teams:sync
        {--league-season-id= : Internal league_seasons id}
        {--league-id= : Internal league id, used with --season}
        {--season= : Season start year, used with --league-id}
        {--provider-ref=* : Provider reference as provider_code=external_id}
        {--apply : Persist planned changes}
        {--json : Print full JSON report}';

    protected $description = 'Discover and synchronize teams for a league season using DB-configured HTTP providers.';

    public function __construct(private readonly NameSimilarityMatcher $matcher)
    {
        parent::__construct();
    }

    public function handle(TeamProviderRegistry $registry): int
    {
        $apply = (bool) $this->option('apply');
        $this->initializeTeamsSyncLog();

        try {
            $context = $this->resolveLeagueSeasonContext();
            $providerReferences = $this->providerReferences();

            if ($providerReferences === []) {
                $providerReferences = $this->providerReferencesFromLeague((int) $context['league_id']);
            }

            if ($providerReferences === []) {
                throw new RuntimeException('No provider references configured for this league.');
            }

            $this->teamsSyncLog('info', 'Team sync started.', [
                'mode' => $apply ? 'apply' : 'dry_run',
                'league_id' => $context['league_id'],
                'league_season_id' => $context['league_season_id'],
                'season_key' => $context['season_key'],
                'provider_references' => $providerReferences,
            ]);

            $providers = $registry->all();
            $providerIds = DB::table('data_providers')
                ->whereIn('code', array_keys($providerReferences))
                ->pluck('id', 'code')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $results = [];
            foreach ($providers as $provider) {
                $result = $provider->fetchTeams(new TeamDataRequest((int) $context['season_key'], $providerReferences));
                $results[] = $result;

                $this->teamsSyncLog($result->available ? 'info' : 'warning', 'Provider team result.', [
                    'provider' => $result->provider,
                    'available' => $result->available,
                    'teams_count' => count($result->teams),
                    'reason' => $result->reason,
                ]);
            }

            $available = array_values(array_filter($results, fn ($result): bool => $result->available));

            if ($available === []) {
                $report = $this->report($context, $providerReferences, $results, [], 'coverage_gap');
                $this->renderReport($report);

                return self::FAILURE;
            }

            $canonicalTeams = $this->canonicalTeams($available[0]->teams);
            $rows = $this->plannedRows((int) $context['league_season_id'], $canonicalTeams, $available, $providerIds);
            $status = $this->statusForRows($rows);
            $report = $this->report($context, $providerReferences, $results, $rows, $status);

            $this->renderReport($report);

            if ($apply) {
                $this->apply((int) $context['league_season_id'], (int) $context['country_id'], $rows, $available, $providerIds);
                $this->components->info('League season teams synchronized.');
            } else {
                $this->components->info('DRY-RUN complete. No database writes were performed.');
            }

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->teamsSyncLog('error', 'Team sync failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array<string,mixed> */
    private function resolveLeagueSeasonContext(): array
    {
        $query = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select([
                'ls.id as league_season_id',
                'l.id as league_id',
                'l.name as league_name',
                'l.country_id',
                'c.name as country_name',
                's.id as season_id',
                's.season_key',
                's.label as season_label',
            ]);

        if ($this->option('league-season-id') !== null) {
            $query->where('ls.id', (int) $this->option('league-season-id'));
        } else {
            $leagueId = (int) $this->option('league-id');
            $season = (int) $this->option('season');

            if ($leagueId <= 0 || $season <= 0) {
                throw new RuntimeException('Provide --league-season-id or both --league-id and --season.');
            }

            $query->where('ls.league_id', $leagueId)
                ->where('s.season_key', $season);
        }

        $row = $query->first();

        if ($row === null) {
            throw new RuntimeException('The requested league season does not exist.');
        }

        return (array) $row;
    }

    /** @return array<string,string> */
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

    /** @return array<string,string> */
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

    /**
     * @param  list<CanonicalTeamData>  $teams
     * @return list<CanonicalTeamData>
     */
    private function canonicalTeams(array $teams): array
    {
        return collect($teams)
            ->unique(fn (CanonicalTeamData $team): string => $team->comparisonKey())
            ->values()
            ->all();
    }

    /**
     * @param  list<CanonicalTeamData>  $canonicalTeams
     * @param  list<object>  $available
     * @param  array<string,int>  $providerIds
     * @return list<array<string,mixed>>
     */
    private function plannedRows(int $leagueSeasonId, array $canonicalTeams, array $available, array $providerIds): array
    {
        $existingTeams = DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->where('lst.league_season_id', $leagueSeasonId)
            ->select('lst.id as league_season_team_id', 'lst.is_active', 't.id as team_id', 't.name', 't.short_name', 't.code')
            ->get();

        return collect($canonicalTeams)
            ->map(function (CanonicalTeamData $team) use ($existingTeams, $available, $providerIds): array {
                $existing = $existingTeams->first(fn (object $row): bool => $this->sameTeam($team, $row));
                $providerMatches = [];

                foreach ($available as $result) {
                    if (! isset($providerIds[$result->provider])) {
                        continue;
                    }

                    $match = $this->matchProviderTeam($team, $result->teams);
                    if ($match !== null) {
                        $providerMatches[] = [
                            'provider' => $result->provider,
                            'data_provider_id' => $providerIds[$result->provider],
                            'team' => $match->toArray(),
                        ];
                    }
                }

                $action = $existing === null
                    ? 'CREATE'
                    : ((bool) $existing->is_active ? 'UNCHANGED' : 'UPDATE');

                return [
                    'action' => $action,
                    'team' => $team->toArray(),
                    'existing_team_id' => $existing?->team_id,
                    'existing_league_season_team_id' => $existing?->league_season_team_id,
                    'providers' => $providerMatches,
                ];
            })
            ->values()
            ->all();
    }

    private function sameTeam(CanonicalTeamData $team, object $existing): bool
    {
        if ($team->code !== null && $existing->code !== null && strcasecmp($team->code, (string) $existing->code) === 0) {
            return true;
        }

        if ($existing->short_name !== null && $this->matcher->matches($team->shortName ?: $team->name, (string) $existing->short_name)) {
            return true;
        }

        return $this->matcher->matches($team->name, (string) $existing->name);
    }

    /** @param list<CanonicalTeamData> $teams */
    private function matchProviderTeam(CanonicalTeamData $canonical, array $teams): ?CanonicalTeamData
    {
        foreach ($teams as $team) {
            if ($canonical->code !== null && $team->code !== null && strcasecmp($canonical->code, $team->code) === 0) {
                return $team;
            }
        }

        foreach ($teams as $team) {
            if ($this->matcher->matches($canonical->shortName ?: $canonical->name, $team->shortName ?: $team->name)) {
                return $team;
            }
        }

        foreach ($teams as $team) {
            if ($this->matcher->matches($canonical->name, $team->name)) {
                return $team;
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $rows */
    private function statusForRows(array $rows): string
    {
        if ($rows === []) {
            return 'empty_payload';
        }

        return collect($rows)->contains(fn (array $row): bool => $row['action'] !== 'UNCHANGED')
            ? 'changes_pending'
            : 'unchanged';
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,string>  $providerReferences
     * @param  list<object>  $results
     * @param  list<array<string,mixed>>  $rows
     * @return array<string,mixed>
     */
    private function report(array $context, array $providerReferences, array $results, array $rows, string $status): array
    {
        return [
            'status' => $status,
            'mode' => (bool) $this->option('apply') ? 'apply' : 'dry_run',
            'league_season' => $context,
            'provider_references' => $providerReferences,
            'providers' => array_map(fn ($result): array => [
                'provider' => $result->provider,
                'available' => $result->available,
                'teams_count' => count($result->teams),
                'reason' => $result->reason,
            ], $results),
            'teams' => $rows,
        ];
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Mode', strtoupper((string) $report['mode'])],
            ['League season', $report['league_season']['league_name'].' '.$report['league_season']['season_label']],
            ['Providers available', collect($report['providers'])->where('available', true)->count().' / '.count($report['providers'])],
            ['Teams', count($report['teams'])],
        ]);

        if ($report['teams'] !== []) {
            $this->table(['Team', 'Code', 'Action', 'Providers'], array_map(
                fn (array $row): array => [
                    $row['team']['name'],
                    $row['team']['code'] ?? '-',
                    $row['action'],
                    count($row['providers']),
                ],
                $report['teams'],
            ));
        }
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @param  list<object>  $available
     * @param  array<string,int>  $providerIds
     */
    private function apply(int $leagueSeasonId, ?int $countryId, array $rows, array $available, array $providerIds): void
    {
        DB::transaction(function () use ($leagueSeasonId, $countryId, $rows): void {
            DB::table('league_season_teams')->where('league_season_id', $leagueSeasonId)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

            foreach ($rows as $row) {
                $team = $row['team'];
                $teamId = $this->upsertTeam($countryId, $team);
                $leagueSeasonTeamId = $this->upsertLeagueSeasonTeam($leagueSeasonId, $teamId, $team);

                foreach ($row['providers'] as $providerMatch) {
                    $providerTeam = $providerMatch['team'];
                    DB::table('team_provider_mappings')->updateOrInsert(
                        [
                            'league_season_team_id' => $leagueSeasonTeamId,
                            'data_provider_id' => $providerMatch['data_provider_id'],
                        ],
                        [
                            'external_id' => (string) $providerTeam['external_id'],
                            'external_name' => (string) $providerTeam['name'],
                            'external_code' => $providerTeam['code'] ?? null,
                            'metadata' => null,
                            'verified_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });

        $this->teamsSyncLog('info', 'Team sync applied.', [
            'league_season_id' => $leagueSeasonId,
            'rows' => count($rows),
            'available_providers' => count($available),
            'provider_ids' => $providerIds,
        ]);
    }

    /** @param array<string,mixed> $team */
    private function upsertTeam(?int $countryId, array $team): int
    {
        $existing = DB::table('teams')
            ->where('country_id', $countryId)
            ->where('name', (string) $team['name'])
            ->first();

        $values = [
            'short_name' => $team['short_name'] ?? null,
            'code' => $team['code'] ?? null,
            'crest_url' => $team['crest_url'] ?? null,
            'metadata' => null,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('teams')->where('id', $existing->id)->update($values);

            return (int) $existing->id;
        }

        return DB::table('teams')->insertGetId($values + [
            'country_id' => $countryId,
            'name' => (string) $team['name'],
            'created_at' => now(),
        ]);
    }

    /** @param array<string,mixed> $team */
    private function upsertLeagueSeasonTeam(int $leagueSeasonId, int $teamId, array $team): int
    {
        $existing = DB::table('league_season_teams')
            ->where('league_season_id', $leagueSeasonId)
            ->where('team_id', $teamId)
            ->first();

        $values = [
            'is_active' => true,
            'metadata' => null,
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            DB::table('league_season_teams')->where('id', $existing->id)->update($values);

            return (int) $existing->id;
        }

        return DB::table('league_season_teams')->insertGetId($values + [
            'league_season_id' => $leagueSeasonId,
            'team_id' => $teamId,
            'created_at' => now(),
        ]);
    }

    private function initializeTeamsSyncLog(): void
    {
        $directory = storage_path('logs/administration/squadre');

        File::ensureDirectoryExists($directory);
        File::put($this->teamsSyncLogPath(), '');
    }

    /** @param array<string,mixed> $context */
    private function teamsSyncLog(string $level, string $message, array $context = []): void
    {
        $level = strtoupper(preg_replace('/[^a-z]+/', '', strtolower($level)) ?: 'info');
        $timestamp = now()->format('Y-m-d H:i:s');
        $context = $context === []
            ? ''
            : ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        File::append($this->teamsSyncLogPath(), "[{$timestamp}][teams_sync][{$level}] {$message}{$context}".PHP_EOL);
    }

    private function teamsSyncLogPath(): string
    {
        return storage_path('logs/administration/squadre/teams_sync.log');
    }
}
