<?php

namespace App\Data\Seasons;

final readonly class CanonicalTeamData
{
    public function __construct(
        public string $provider,
        public string $externalId,
        public string $name,
        public ?string $shortName,
        public ?string $code,
        public ?string $country,
        public ?string $crestUrl,
        public array $metadata = [],
    ) {}

    public function comparisonKey(): string
    {
        $value = mb_strtolower(trim($this->name));
        $value = preg_replace('/\b(fc|calcio|club|ac|as|ss|us)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'code' => $this->code,
            'country' => $this->country,
            'crest_url' => $this->crestUrl,
            'metadata' => $this->metadata,
        ];
    }
}
