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
        $headers = $this->staticHeaders($settings);

        if (($settings['auth_type'] ?? 'none') !== 'header') {
            return $headers;
        }

        $header = trim((string) ($settings['auth_header_name'] ?? ''));
        $credentialKey = trim((string) ($settings['credential_key'] ?? ''));
        $credential = $this->credential($providerId, $credentialKey);

        if ($header !== '' && $credential !== null) {
            $headers[$header] = $credential;
        }

        return $headers;
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

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function staticHeaders(array $settings): array
    {
        $headers = $settings['http_headers'] ?? [];

        if (! is_array($headers)) {
            return [];
        }

        return collect($headers)
            ->mapWithKeys(function (mixed $value, string $key): array {
                $key = trim($key);
                $value = trim((string) $value);

                return $key !== '' && $value !== ''
                    ? [$key => $value]
                    : [];
            })
            ->all();
    }
}
