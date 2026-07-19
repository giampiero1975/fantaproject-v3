<?php

namespace Tests\Unit\Seasons;

use App\Services\Seasons\SeasonTimelinePlanner;
use PHPUnit\Framework\TestCase;

final class SeasonTimelinePlannerTest extends TestCase
{
    public function test_it_builds_current_plus_configured_history(): void
    {
        $timeline = (new SeasonTimelinePlanner())->build(2026, 4);

        self::assertCount(5, $timeline);
        self::assertSame([2026, 2025, 2024, 2023, 2022], array_column($timeline, 'season_key'));
        self::assertSame('2026/27', $timeline[0]['label']);
        self::assertTrue($timeline[0]['is_current']);
        self::assertFalse($timeline[1]['is_current']);
    }

    public function test_zero_history_returns_only_current_season(): void
    {
        $timeline = (new SeasonTimelinePlanner())->build(2026, 0);

        self::assertCount(1, $timeline);
        self::assertSame(2026, $timeline[0]['season_key']);
        self::assertTrue($timeline[0]['is_current']);
    }
}
