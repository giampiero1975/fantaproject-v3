<?php

namespace Tests\Unit\Seasons;

use App\Data\Seasons\CanonicalTeamData;
use App\Services\Seasons\TeamCongruityValidator;
use PHPUnit\Framework\TestCase;

final class TeamCongruityValidatorTest extends TestCase
{
    public function test_it_passes_when_codes_match(): void
    {
        $validator = new TeamCongruityValidator();

        $report = $validator->compare(
            [new CanonicalTeamData('football_data', '98', 'AC Milan', 'Milan', 'MIL', 'Italy', null)],
            [new CanonicalTeamData('api_football', '489', 'AC Milan', null, 'MIL', 'Italy', null)],
        );

        self::assertSame('pass', $report['status']);
        self::assertCount(1, $report['matched']);
    }

    public function test_it_uses_short_name_when_provider_codes_differ(): void
    {
        $validator = new TeamCongruityValidator();

        $report = $validator->compare(
            [new CanonicalTeamData('football_data', '450', 'Hellas Verona FC', 'Verona', 'HVE', 'Italy', null)],
            [new CanonicalTeamData('api_football', '504', 'Hellas Verona', null, 'VER', 'Italy', null)],
        );

        self::assertSame('pass', $report['status']);
        self::assertCount(1, $report['matched']);
        self::assertSame([], $report['missing_left']);
        self::assertSame([], $report['missing_right']);
    }

    public function test_it_uses_operational_short_name_for_lecce(): void
    {
        $validator = new TeamCongruityValidator();

        $report = $validator->compare(
            [new CanonicalTeamData('football_data', '5890', 'US Lecce', 'Lecce', 'USL', 'Italy', null)],
            [new CanonicalTeamData('api_football', '867', 'Lecce', null, 'LEC', 'Italy', null)],
        );

        self::assertSame('pass', $report['status']);
        self::assertCount(1, $report['matched']);
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
