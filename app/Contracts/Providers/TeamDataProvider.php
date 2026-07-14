<?php

namespace App\Contracts\Providers;

use App\Data\Providers\ProviderTeamResult;
use App\Data\Providers\TeamDataRequest;

interface TeamDataProvider
{
    public function key(): string;

    public function fetchTeams(TeamDataRequest $request): ProviderTeamResult;
}
