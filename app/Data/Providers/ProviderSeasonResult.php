<?php

namespace App\Data\Providers;

use App\Data\Seasons\CanonicalSeasonData;

final readonly class ProviderSeasonResult
{
    private function __construct(
        public string $provider,
        public bool $available,
        public ?CanonicalSeasonData $season,
        public ?string $reason,
    ) {}

    public static function available(string $provider, CanonicalSeasonData $season): self
    {
        return new self($provider, true, $season, null);
    }

    public static function unavailable(string $provider, string $reason): self
    {
        return new self($provider, false, null, $reason);
    }
}
