<?php

namespace App\Services\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProviderHttpAuthentication
{
    public function __construct(
        private readonly ProviderConfigurationReader $configurations,
    ) {}

    public function applyHeaders(PendingRequest $request, int $providerId): PendingRequest
    {
        $headers = $this->headers($providerId);

        return $headers === []
            ? $request
            : $request->withHeaders($headers);
    }

    /**
     * @return array<string, string>
     */
    public function headers(int $providerId): array
    {
        $settings = $this->configurations->values($providerId);

        if (($settings['auth_type'] ?? 'none') !== 'header') {
            return [];
        }

        $header = trim((string) ($settings['auth_header_name'] ?? ''));
        $credentialKey = trim((string) ($settings['credential_key'] ?? ''));
        $credential = $this->credential($providerId, $credentialKey);

        return $header !== '' && $credential !== null
            ? [$header => $credential]
            : [];
    }

    /**
     * @return array<string, string>
     */
    public function queryParameters(int $providerId): array
    {
        $settings = $this->configurations->values($providerId);

        if (($settings['auth_type'] ?? 'none') !== 'query') {
            return [];
        }

        $parameter = trim((string) ($settings['auth_query_param'] ?? ''));
        $credentialKey = trim((string) ($settings['credential_key'] ?? ''));
        $credential = $this->credential($providerId, $credentialKey);

        return $parameter !== '' && $credential !== null
            ? [$parameter => $credential]
            : [];
    }

    private function credential(int $providerId, string $credentialKey): ?string
    {
        if ($credentialKey === '') {
            return null;
        }

        $credential = DB::table('data_provider_credentials')
            ->where('data_provider_id', $providerId)
            ->where('environment', app()->environment())
            ->where('credential_key', $credentialKey)
            ->where('is_active', true)
            ->first(['encrypted_value']);

        if (! $credential) {
            return null;
        }

        try {
            $value = Crypt::decryptString((string) $credential->encrypted_value);
        } catch (Throwable) {
            return null;
        }

        return $value !== '' ? $value : null;
    }
}
