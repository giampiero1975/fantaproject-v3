<?php

namespace Tests\Unit\Seasons;

use App\Services\Seasons\TeamPayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class TeamPayloadNormalizerTest extends TestCase
{
    public function test_it_normalizes_both_provider_payloads_to_the_same_contract(): void
    {
        $normalizer = new TeamPayloadNormalizer();

        $footballData = $normalizer->fromFootballData([
            'teams' => [[
                'id' => 108,
                'name' => 'FC Internazionale Milano',
                'shortName' => 'Inter',
                'tla' => 'INT',
                'crest' => 'https://example.test/inter.png',
                'area' => ['name' => 'Italy'],
            ]],
        ]);

        $apiFootball = $normalizer->fromApiFootball([
            'response' => [[
                'team' => [
                    'id' => 505,
                    'name' => 'Inter',
                    'code' => 'INT',
                    'country' => 'Italy',
                    'logo' => 'https://example.test/inter-api.png',
                ],
            ]],
        ]);

        self::assertSame('football_data', $footballData[0]->provider);
        self::assertSame('api_football', $apiFootball[0]->provider);
        self::assertSame('INT', $footballData[0]->code);
        self::assertSame('INT', $apiFootball[0]->code);
        self::assertSame('Italy', $footballData[0]->country);
        self::assertSame('Italy', $apiFootball[0]->country);
    }
}
