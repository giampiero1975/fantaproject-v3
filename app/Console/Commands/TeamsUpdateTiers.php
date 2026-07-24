<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class TeamsUpdateTiers extends Command
{
    protected $signature = 'teams:update-tiers
        {--league-season-id= : Internal league_seasons id}
        {--year= : Season key/year, for example 2026}
        {--apply : Persist planned tier changes}
        {--json : Print full JSON report}';

    protected $description = 'Compatibility alias for V2 team tier update, backed by V3 DB-driven tier sync.';

    public function handle(): int
    {
        $leagueSeasonId = $this->resolveLeagueSeasonId();
        if ($leagueSeasonId === null) {
            return self::FAILURE;
        }

        $arguments = [
            '--league-season-id' => $leagueSeasonId,
        ];

        if ((bool) $this->option('apply')) {
            $arguments['--apply'] = true;
        }

        if ((bool) $this->option('json')) {
            $arguments['--json'] = true;
        }

        return Artisan::call('team-tiers:sync', $arguments) === self::SUCCESS
            ? $this->relayOutput(self::SUCCESS)
            : $this->relayOutput(self::FAILURE);
    }

    private function relayOutput(int $exitCode): int
    {
        $output = trim(Artisan::output());
        if ($output !== '') {
            $this->line($output);
        }

        return $exitCode;
    }

    private function resolveLeagueSeasonId(): ?int
    {
        $explicitId = (int) ($this->option('league-season-id') ?? 0);
        if ($explicitId > 0) {
            return $explicitId;
        }

        $year = (int) ($this->option('year') ?? 0);
        if ($year <= 0) {
            $this->error('Provide --league-season-id or --year. In V3 the target must resolve to one league season.');
            return null;
        }

        $rows = DB::table('league_seasons as ls')
            ->join('leagues as l', 'l.id', '=', 'ls.league_id')
            ->join('seasons as s', 's.id', '=', 'ls.season_id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->where('s.season_key', $year)
            ->select('ls.id', 'l.name as league_name', 'c.name as country_name', 's.label as season_label')
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->get();

        if ($rows->count() === 1) {
            return (int) $rows->first()->id;
        }

        if ($rows->isEmpty()) {
            $this->error("No league season found for year {$year}.");
            return null;
        }

        $this->error("Year {$year} matches multiple league seasons. Use --league-season-id.");
        $this->table(['ID', 'Country', 'League', 'Season'], $rows->map(fn (object $row): array => [
            $row->id,
            $row->country_name ?? '-',
            $row->league_name,
            $row->season_label,
        ])->all());

        return null;
    }
}
