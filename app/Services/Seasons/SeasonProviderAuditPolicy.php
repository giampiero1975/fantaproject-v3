<?php

namespace App\Services\Seasons;

final class SeasonProviderAuditPolicy
{
    /**
     * @return array{mode:string,providers:list<string>,reason:?string}
     */
    public function forCompetition(string $competition): array
    {
        $competition = strtoupper(trim($competition));

        return match ($competition) {
            'SA' => [
                'mode' => 'comparison',
                'providers' => ['football_data', 'api_football'],
                'reason' => null,
            ],
            'SB' => [
                'mode' => 'single_provider',
                'providers' => ['api_football'],
                'reason' => 'football-data.org is not available for Serie B on the configured plan.',
            ],
            default => [
                'mode' => 'comparison',
                'providers' => ['football_data', 'api_football'],
                'reason' => null,
            ],
        };
    }
}
