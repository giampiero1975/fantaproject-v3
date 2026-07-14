<?php

namespace App\Services\Providers;

use App\Contracts\Providers\TeamDataProvider;
use App\Data\Providers\ProviderTeamResult;
use App\Data\Providers\TeamDataRequest;
use App\Services\ApiFootball\ApiFootballClient;
use App\Services\Seasons\TeamPayloadNormalizer;
use RuntimeException;

final class ApiFootballTeamProvider implements TeamDataProvider
{
    public function __construct(
        private ApiFootballClient $client,
        private TeamPayloadNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'api_football';
    }

    public function fetchTeams(TeamDataRequest $request): ProviderTeamResult
    {
        $reference = $request->referenceFor($this->key());
        if ($reference === null || ! is_numeric($reference)) {
            return ProviderTeamResult::unavailable($this->key(), 'missing_provider_reference');
        }

        try {
            $payload = $this->client->teams((int) $reference, $request->seasonYear);

            return ProviderTeamResult::available(
                $this->key(),
                $this->normalizer->fromApiFootball($payload),
            );
        } catch (RuntimeException $e) {
            $message = mb_strtolower($e->getMessage());
            if (str_contains($message, 'plan') || str_contains($message, 'permission') || str_contains($message, 'access')) {
                return ProviderTeamResult::unavailable($this->key(), 'unavailable_for_current_credentials_or_plan');
            }

            throw $e;
        }
    }
}
