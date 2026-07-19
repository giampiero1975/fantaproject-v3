<?php

namespace App\Data\Providers;

final readonly class TeamDataRequest
{
    /**
     * @param array<string,string|int> $providerReferences
     */
    public function __construct(
        public int $seasonYear,
        public array $providerReferences,
    ) {}

    public function referenceFor(string $provider): string|int|null
    {
        return $this->providerReferences[$provider] ?? null;
    }
}
