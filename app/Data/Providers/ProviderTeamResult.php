<?php

namespace App\Data\Providers;

use App\Data\Seasons\CanonicalTeamData;

final readonly class ProviderTeamResult
{
    /**
     * @param list<CanonicalTeamData> $teams
     */
    private function __construct(
        public string $provider,
        public bool $available,
        public array $teams,
        public ?string $reason,
    ) {}

    /** @param list<CanonicalTeamData> $teams */
    public static function available(string $provider, array $teams): self
    {
        return new self($provider, true, $teams, null);
    }

    public static function unavailable(string $provider, string $reason): self
    {
        return new self($provider, false, [], $reason);
    }
}
