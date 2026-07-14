<?php

namespace App\Services\Seasons;

use App\Data\Seasons\CanonicalTeamData;
use InvalidArgumentException;

final class TeamPayloadNormalizer
{
    /** @return list<CanonicalTeamData> */
    public function fromFootballData(array $payload): array
    {
        $teams = $payload['teams'] ?? null;
        if (! is_array($teams)) {
            throw new InvalidArgumentException('football-data.org payload does not contain a teams array.');
        }

        return array_values(array_map(
            static fn (array $team): CanonicalTeamData => new CanonicalTeamData(
                provider: 'football_data',
                externalId: (string) ($team['id'] ?? ''),
                name: trim((string) ($team['name'] ?? '')),
                shortName: isset($team['shortName']) ? trim((string) $team['shortName']) : null,
                code: isset($team['tla']) ? trim((string) $team['tla']) : null,
                country: isset($team['area']['name']) ? trim((string) $team['area']['name']) : null,
                crestUrl: isset($team['crest']) ? (string) $team['crest'] : null,
                metadata: $team,
            ),
            $teams,
        ));
    }

    /** @return list<CanonicalTeamData> */
    public function fromApiFootball(array $payload): array
    {
        $items = $payload['response'] ?? null;
        if (! is_array($items)) {
            throw new InvalidArgumentException('API-Football payload does not contain a response array.');
        }

        return array_values(array_map(static function (array $item): CanonicalTeamData {
            $team = is_array($item['team'] ?? null) ? $item['team'] : [];

            return new CanonicalTeamData(
                provider: 'api_football',
                externalId: (string) ($team['id'] ?? ''),
                name: trim((string) ($team['name'] ?? '')),
                shortName: null,
                code: isset($team['code']) ? trim((string) $team['code']) : null,
                country: isset($team['country']) ? trim((string) $team['country']) : null,
                crestUrl: isset($team['logo']) ? (string) $team['logo'] : null,
                metadata: $item,
            );
        }, $items));
    }
}
