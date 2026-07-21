<?php

namespace App\Data\Providers;

use App\Data\Seasons\CanonicalStandingData;

final readonly class ProviderStandingResult
{
    /** @param list<CanonicalStandingData> $standings */
    private function __construct(
        public string $provider,
        public bool $available,
        public array $standings,
        public ?string $reason,
    ) {}

    /** @param list<CanonicalStandingData> $standings */
    public static function available(string $provider, array $standings): self
    {
        return new self($provider, true, $standings, null);
    }

    public static function unavailable(string $provider, string $reason): self
    {
        return new self($provider, false, [], $reason);
    }
}