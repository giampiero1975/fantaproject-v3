<?php

namespace App\Data\Seasons;

final readonly class CanonicalSeasonData
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $provider,
        public ?string $externalId,
        public ?string $startDate,
        public ?string $endDate,
        public array $metadata = [],
    ) {}
}
