<?php

namespace Tests\Unit\Providers;

use App\Data\Providers\ProviderTeamResult;
use App\Data\Seasons\CanonicalTeamData;
use App\Services\Matching\NameSimilarityMatcher;
use App\Services\Providers\TeamAuditPlanner;
use App\Services\Seasons\TeamCongruityValidator;
use PHPUnit\Framework\TestCase;

final class TeamAuditPlannerTest extends TestCase
{
    public function test_it_uses_multi_provider_congruity_when_two_providers_are_available(): void
    {
        $planner = $this->planner();
        $teamA = new CanonicalTeamData('a', '1', 'Inter', 'Inter', 'INT', 'Italy', null);
        $teamB = new CanonicalTeamData('b', '2', 'Inter', null, 'INT', 'Italy', null);

        $plan = $planner->plan([
            ProviderTeamResult::available('a', [$teamA]),
            ProviderTeamResult::available('b', [$teamB]),
        ]);

        self::assertSame('multi_provider_congruity', $plan['mode']);
        self::assertSame('pass', $plan['status']);
    }

    public function test_it_falls_back_to_single_provider_without_hardcoding_competitions(): void
    {
        $plan = $this->planner()->plan([
            ProviderTeamResult::unavailable('football_data', 'unavailable_for_current_credentials_or_plan'),
            ProviderTeamResult::available('api_football', [
                new CanonicalTeamData('api_football', '1', 'Palermo', null, 'PAL', 'Italy', null),
            ]),
        ]);

        self::assertSame('single_provider_validation', $plan['mode']);
        self::assertSame('pass', $plan['status']);
        self::assertSame('api_football', $plan['available'][0]->provider);
    }

    public function test_it_reports_a_coverage_gap_when_no_provider_is_available(): void
    {
        $plan = $this->planner()->plan([
            ProviderTeamResult::unavailable('a', 'plan'),
            ProviderTeamResult::unavailable('b', 'plan'),
        ]);

        self::assertSame('coverage_gap', $plan['mode']);
        self::assertSame('fail', $plan['status']);
    }

    private function planner(): TeamAuditPlanner
    {
        return new TeamAuditPlanner(
            new TeamCongruityValidator(new NameSimilarityMatcher()),
        );
    }
}
