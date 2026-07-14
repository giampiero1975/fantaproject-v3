<?php

namespace Tests\Unit\Seasons;

use App\Data\Seasons\CanonicalTeamData;
use App\Services\Seasons\TeamCongruityValidator;
use PHPUnit\Framework\TestCase;

final class TeamCongruityValidatorTest extends TestCase
{
    public function test_it_passes_when_canonical_team_sets_match(): void
    {
        $validator = new TeamCongruityValidator();

        $report = $validator->compare(
            [new CanonicalTeamData('football_data', '98', 'AC Milan', 'Milan', 'MIL', 'Italy', null)],
            [new CanonicalTeamData('api_football', '489', 'AC Milan', null, 'MIL', 'Italy', null)],
        );

        self::assertSame('pass', $report['status']);
        self::assertCount(1, $report['matched']);
        self::assertSame([], $report['missing_left']);
        self::assertSame([], $report['missing_right']);
    }

    public function test_it_fails_when_a_provider_is_missing_a_team(): void
    {
        $validator = new TeamCongruityValidator();

        $report = $validator->compare(
            [new CanonicalTeamData('football_data', '98', 'AC Milan', 'Milan', 'MIL', 'Italy', null)],
            [],
        );

        self::assertSame('fail', $report['status']);
        self::assertCount(1, $report['missing_right']);
    }
}
