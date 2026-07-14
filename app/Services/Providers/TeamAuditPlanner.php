<?php

namespace App\Services\Providers;

use App\Data\Providers\ProviderTeamResult;
use App\Services\Seasons\TeamCongruityValidator;

final class TeamAuditPlanner
{
    public function __construct(private TeamCongruityValidator $validator) {}

    /**
     * @param list<ProviderTeamResult> $results
     * @return array<string,mixed>
     */
    public function plan(array $results): array
    {
        $available = array_values(array_filter($results, fn (ProviderTeamResult $result) => $result->available));

        if (count($available) >= 2) {
            return [
                'status' => $this->validator->compare($available[0]->teams, $available[1]->teams)['status'],
                'mode' => 'multi_provider_congruity',
                'available' => $available,
                'comparison' => $this->validator->compare($available[0]->teams, $available[1]->teams),
                'results' => $results,
            ];
        }

        if (count($available) === 1) {
            return [
                'status' => 'pass',
                'mode' => 'single_provider_validation',
                'available' => $available,
                'results' => $results,
            ];
        }

        return [
            'status' => 'fail',
            'mode' => 'coverage_gap',
            'available' => [],
            'results' => $results,
        ];
    }
}
