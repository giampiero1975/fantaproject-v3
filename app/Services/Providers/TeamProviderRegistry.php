<?php

namespace App\Services\Providers;

use App\Contracts\Providers\TeamDataProvider;

final class TeamProviderRegistry
{
    /** @var list<TeamDataProvider> */
    private array $providers;

    public function __construct(
        FootballDataTeamProvider $footballData,
        ApiFootballTeamProvider $apiFootball,
    ) {
        $this->providers = [$footballData, $apiFootball];
    }

    /** @return list<TeamDataProvider> */
    public function all(): array
    {
        return $this->providers;
    }
}
