<?php

namespace App\Console\Commands;

use App\Data\Providers\StandingDataRequest;
use App\Data\Seasons\CanonicalStandingData;
use App\Services\Matching\NameSimilarityMatcher;
use App\Services\Providers\StandingProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class SyncLeagueSeasonStandings extends Command
{
    protected $signature = 'standings:sync
        {--league-season-id= : Internal league_seasons id}
        {--league-id= : Internal league id, used with --season}
        {--season= : Season start year, used with --league-id}
        {--provider-ref=* : Provider reference as provider_code=external_id}
        {--apply : Persist planned changes}
        {--json : Print full JSON report}';

    protected $description = 'Discover and synchronize standings for a league season using the canonical provider layer.';

    public function __construct(private readonly NameSimilarityMatcher $matcher)
    {
        parent::__construct();
    }

    public function handle(StandingProviderRegistry $registry): int
    {
        $apply = (bool) $this->option('apply');
        $this->initializeStandingsSyncLog();

        try {
            $context = $this->resolveLeagueSeasonContext();
            $providerReferences = $this->providerReferences();

            if ($providerReferences === []) {
                $providerReferences = $this->providerReferencesFromLeague((int) $context['league_id']);
            }

            if ($providerReferences === []) {
                throw new RuntimeException('No provider references configured for this league.');
            }

            $this->standingsSyncLog('info', 'Standing sync started.', [
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
                $result = $provider->fetchStandings(new StandingDataRequest(
                    (int) $context['season_key'],
                    (string) $context['season_label'],
                    $providerReferences,
                ));
                $results[] = $result;

                $this->standingsSyncLog($result->available ? 'info' : 'warning', 'Provider standing result.', [
                    'provider' => $result->provider,
                    'available' => $result->available,
                    'standings_count' => count($result->standings),
                    'reason' => $result->reason,
                ]);
            }

            $available = array_values(array_filter($results, fn ($result): bool => $result->available));
            if ($available === []) {
                $report = $this->report($context, $providerReferences, $results, [], 'coverage_gap');
                $this->renderReport($report);

                return self::FAILURE;
            }

            $rows = $this->plannedRows((int) $context['league_season_id'], $available[0]->standings, $available, $providerIds);
            $status = $this->statusForRows($rows);
            $report = $this->report($context, $providerReferences, $results, $rows, $status);

            $this->renderReport($report);

            if ($apply) {
                $this->apply($rows);
                $this->components->info('League season standings synchronized.');
            } else {
                $this->components->info('DRY-RUN complete. No database writes were performed.');
            }

            if ((bool) $this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->standingsSyncLog('error', 'Standing sync failed.', [
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

            $query->where('ls.league_id', $leagueId)->where('s.season_key', $season);
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

    /** @param list<CanonicalStandingData> $standings @param list<object> $available @param array<string,int> $providerIds @return list<array<string,mixed>> */
    private function plannedRows(int $leagueSeasonId, array $standings, array $available, array $providerIds): array
    {
        $memberships = DB::table('league_season_teams as lst')
            ->join('teams as t', 't.id', '=', 'lst.team_id')
            ->where('lst.league_season_id', $leagueSeasonId)
            ->where('lst.is_active', true)
            ->select('lst.id as league_season_team_id', 't.id as team_id', 't.name', 't.short_name', 't.code')
            ->get();

        $existingStandings = DB::table('league_season_team_standings as st')
            ->join('league_season_teams as lst', 'lst.id', '=', 'st.league_season_team_id')
            ->where('lst.league_season_id', $leagueSeasonId)
            ->get()
            ->keyBy(fn (object $row): string => $row->league_season_team_id.'|'.($row->stage_name ?? '').'|'.($row->group_name ?? ''));

        return collect($standings)
            ->map(function (CanonicalStandingData $standing) use ($memberships, $existingStandings, $available, $providerIds): array {
                $membership = $this->matchMembership($standing, $memberships, $available, $providerIds);
                $key = $membership !== null
                    ? $membership->league_season_team_id.'|'.($standing->stageName ?? '').'|'.($standing->groupName ?? '')
                    : null;
                $existing = $key !== null ? $existingStandings->get($key) : null;
                $action = $membership === null ? 'UNMATCHED' : ($existing === null ? 'CREATE' : ($this->standingChanged($standing, $existing) ? 'UPDATE' : 'UNCHANGED'));

                return [
                    'action' => $action,
                    'standing' => $standing->toArray(),
                    'league_season_team_id' => $membership?->league_season_team_id,
                    'team_id' => $membership?->team_id,
                    'team_name' => $membership?->name ?? $standing->teamName,
                ];
            })
            ->values()
            ->all();
    }

    /** @param \Illuminate\Support\Collection<int,object> $memberships @param list<object> $available @param array<string,int> $providerIds */
    private function matchMembership(CanonicalStandingData $standing, $memberships, array $available, array $providerIds): ?object
    {
        $providerId = $providerIds[$standing->provider] ?? null;
        if ($providerId !== null && $standing->providerTeamId !== '') {
            $mapped = DB::table('team_provider_mappings as tpm')
                ->where('tpm.data_provider_id', $providerId)
                ->where('tpm.external_id', $standing->providerTeamId)
                ->whereIn('tpm.league_season_team_id', $memberships->pluck('league_season_team_id')->all())
                ->first();

            if ($mapped !== null) {
                return $memberships->first(fn (object $row): bool => (int) $row->league_season_team_id === (int) $mapped->league_season_team_id);
            }
        }

        return $memberships->first(fn (object $row): bool => $this->sameTeam($standing, $row));
    }

    private function sameTeam(CanonicalStandingData $standing, object $row): bool
    {
        if ($standing->teamCode !== null && $row->code !== null && strtoupper($standing->teamCode) === strtoupper((string) $row->code)) {
            return true;
        }

        return $this->matcher->matches($standing->comparisonKey(), (string) $row->name)
            || ($row->short_name !== null && $this->matcher->matches($standing->comparisonKey(), (string) $row->short_name));
    }

    private function standingChanged(CanonicalStandingData $standing, object $existing): bool
    {
        foreach (['position', 'played_games', 'won', 'draw', 'lost', 'points', 'goals_for', 'goals_against', 'goal_difference'] as $field) {
            $property = match ($field) {
                'played_games' => 'playedGames',
                'goals_for' => 'goalsFor',
                'goals_against' => 'goalsAgainst',
                'goal_difference' => 'goalDifference',
                default => $field,
            };

            if ((int) ($existing->{$field} ?? -999999) !== (int) ($standing->{$property} ?? -999999)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private function statusForRows(array $rows): string
    {
        if (collect($rows)->contains(fn (array $row): bool => $row['action'] === 'UNMATCHED')) {
            return 'unmatched_teams';
        }

        return collect($rows)->contains(fn (array $row): bool => in_array($row['action'], ['CREATE', 'UPDATE'], true))
            ? 'changes_pending'
            : 'unchanged';
    }

    /** @param list<object> $results @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function report(array $context, array $providerReferences, array $results, array $rows, string $status): array
    {
        return [
            'status' => $status,
            'mode' => $this->option('apply') ? 'apply' : 'dry_run',
            'league_season' => $context,
            'provider_references' => $providerReferences,
            'providers' => collect($results)->map(fn ($result): array => [
                'provider' => $result->provider,
                'available' => $result->available,
                'standings_count' => count($result->standings),
                'reason' => $result->reason,
            ])->all(),
            'standings' => $rows,
        ];
    }

    /** @param array<string,mixed> $report */
    private function renderReport(array $report): void
    {
        $this->table(['Metric', 'Value'], [
            ['Status', strtoupper((string) $report['status'])],
            ['Mode', strtoupper((string) $report['mode'])],
            ['League season', $report['league_season']['league_name'].' '.$report['league_season']['season_label']],
            ['Rows', count($report['standings'])],
        ]);

        if ($report['standings'] !== []) {
            $this->table(['Team', 'Pos', 'Pts', 'Action'], collect($report['standings'])->map(fn (array $row): array => [
                $row['team_name'],
                $row['standing']['position'] ?? '-',
                $row['standing']['points'] ?? '-',
                $row['action'],
            ])->all());
        }
    }

    /** @param list<array<string,mixed>> $rows */
    private function apply(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                if (! in_array($row['action'], ['CREATE', 'UPDATE'], true) || $row['league_season_team_id'] === null) {
                    continue;
                }

                $standing = $row['standing'];
                DB::table('league_season_team_standings')->updateOrInsert(
                    [
                        'league_season_team_id' => $row['league_season_team_id'],
                        'stage_name' => $standing['stage_name'],
                        'group_name' => $standing['group_name'],
                    ],
                    [
                        'position' => $standing['position'],
                        'played_games' => $standing['played_games'],
                        'won' => $standing['won'],
                        'draw' => $standing['draw'],
                        'lost' => $standing['lost'],
                        'points' => $standing['points'],
                        'goals_for' => $standing['goals_for'],
                        'goals_against' => $standing['goals_against'],
                        'goal_difference' => $standing['goal_difference'],
                        'metadata' => null,
                        'synced_at' => now(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        });

        $this->standingsSyncLog('info', 'Standing sync applied.', [
            'rows' => count($rows),
        ]);
    }

    private function initializeStandingsSyncLog(): void
    {
        File::ensureDirectoryExists(dirname($this->standingsSyncLogPath()));
        File::put($this->standingsSyncLogPath(), '');
    }

    private function standingsSyncLog(string $level, string $message, array $context = []): void
    {
        $context = $context === [] ? '' : ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        File::append($this->standingsSyncLogPath(), '['.now()->toIso8601String().'][standings_sync]['.strtoupper($level).'] '.$message.$context.PHP_EOL);
    }

    private function standingsSyncLogPath(): string
    {
        return storage_path('logs/administration/classifiche/standings_sync.log');
    }
}
