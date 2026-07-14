<?php

namespace App\Services\Providers;

use App\Contracts\Providers\TeamDataProvider;
use App\Data\Providers\ProviderTeamResult;
use App\Data\Providers\TeamDataRequest;
use App\Services\FootballData\FootballDataClient;
use App\Services\Seasons\TeamPayloadNormalizer;
use Illuminate\Http\Client\RequestException;

final class FootballDataTeamProvider implements TeamDataProvider
{
    public function __construct(
        private FootballDataClient $client,
        private TeamPayloadNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'football_data';
    }

    public function fetchTeams(TeamDataRequest $request): ProviderTeamResult
    {
        $reference = $request->referenceFor($this->key());
        if ($reference === null || trim((string) $reference) === '') {
            return ProviderTeamResult::unavailable($this->key(), 'missing_provider_reference');
        }

        try {
            $payload = $this->client->teams((string) $reference, $request->seasonYear);

            return ProviderTeamResult::available(
                $this->key(),
                $this->normalizer->fromFootballData($payload),
            );
        } catch (RequestException $e) {
            $status = $e->response->status();
            if (in_array($status, [401, 403], true)) {
                return ProviderTeamResult::unavailable($this->key(), 'unavailable_for_current_credentials_or_plan');
            }

            throw $e;
        }
    }
}
