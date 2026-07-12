<?php

namespace Tests\Unit;

use App\Services\ApiFootball\LeagueNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeagueNameNormalizerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_normalizes_names(string $input, string $expected): void
    {
        $this->assertSame($expected, (new LeagueNameNormalizer())->normalize($input));
    }

    public static function names(): array
    {
        return [
            ['Série A', 'serie a'],
            ['  Premier-League ', 'premier league'],
            ['Football Championship', 'championship'],
            ['Liga Portugal 2', 'liga portugal 2'],
        ];
    }
}
