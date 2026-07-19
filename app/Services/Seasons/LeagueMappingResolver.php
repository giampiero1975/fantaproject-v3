<?php

namespace App\Services\Seasons;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LeagueMappingResolver
{
    /** @param array<string,string|int|null> $references */
    public function resolve(array $references): int
    {
        $resolved = [];

        foreach ($references as $providerCode => $externalId) {
            if ($externalId === null || $externalId === '') {
                continue;
            }

            $leagueId = DB::table('league_provider_mappings')
                ->join('data_providers', 'data_providers.id', '=', 'league_provider_mappings.data_provider_id')
                ->where('data_providers.code', $providerCode)
                ->where('league_provider_mappings.external_id', (string) $externalId)
                ->value('league_provider_mappings.league_id');

            if ($leagueId !== null) {
                $resolved[$providerCode] = (int) $leagueId;
            }
        }

        $unique = array_values(array_unique(array_values($resolved)));

        if ($unique === []) {
            throw new RuntimeException('No provider mapping resolves the requested competition to an internal league.');
        }

        if (count($unique) !== 1) {
            throw new RuntimeException('Provider mappings resolve to different internal leagues: '.json_encode($resolved));
        }

        return $unique[0];
    }
}
