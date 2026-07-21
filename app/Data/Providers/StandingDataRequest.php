<?php

namespace App\Data\Providers;

final readonly class StandingDataRequest
{
    /** @param array<string,string|int> $providerReferences */
    public function __construct(
        public int $seasonYear,
        public string $seasonLabel,
        public array $providerReferences,
    ) {}

    public function referenceFor(string $provider): string|int|null
    {
        return $this->providerReferences[$provider] ?? null;
    }
}