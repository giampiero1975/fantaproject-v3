<?php

namespace App\Data\Providers;

final readonly class ProviderRuntimeConfiguration
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $providerId,
        public string $code,
        public string $name,
        public bool $enabled,
        public int $priority,
        public string $role,
        public string $baseUrl,
        public int $timeout,
        public int $connectTimeout,
        public int $retryTimes,
        public int $retrySleepMs,
        public ?string $plan,
        public array $metadata,
        private array $credentials,
    ) {}

    public function credential(string $key): string
    {
        return (string) ($this->credentials[$key] ?? '');
    }

    /** @return array<string,bool> */
    public function credentialStatus(): array
    {
        return array_map(static fn (string $value): bool => $value !== '', $this->credentials);
    }
}