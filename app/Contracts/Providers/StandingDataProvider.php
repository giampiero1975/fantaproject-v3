<?php

namespace App\Contracts\Providers;

use App\Data\Providers\ProviderStandingResult;
use App\Data\Providers\StandingDataRequest;

interface StandingDataProvider
{
    public function key(): string;

    public function fetchStandings(StandingDataRequest $request): ProviderStandingResult;
}