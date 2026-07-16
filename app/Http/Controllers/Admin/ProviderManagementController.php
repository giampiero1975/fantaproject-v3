<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Providers\ProviderAdapterDefinitionRepository;
use App\Services\Providers\ProviderConfigurationReader;
use App\Services\Providers\ProviderConfigurationWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class ProviderManagementController extends Controller
{
    public function index(): View
    {
        $environment = app()->environment();
        $registeredCodes = DB::table('data_providers')->pluck('code')->all();
        $adapterDefinitions = app(ProviderAdapterDefinitionRepository::class)->installed();
        $availableAdapters = $adapterDefinitions
            ->reject(fn (array $adapter, string $code): bool => in_array($code, $registeredCodes, true))
            ->map(fn (array $adapter, string $code): array => [
                'code' => $code,
                'name' => $adapter['name'] ?? $code,
                'credential_key' => $adapter['credential_key'] ?? null,
                'capabilities' => array_values($adapter['capabilities'] ?? []),
            ])
            ->values();

        $providers = DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->select([
                'p.id', 'p.code', 'p.name', 'p.active as catalog_active',
                'rc.is_enabled', 'rc.priority', 'rc.role', 'rc.base_url', 'rc.timeout',
                'rc.connect_timeout', 'rc.retry_times', 'rc.retry_sleep_ms', 'rc.plan', 'rc.metadata',
            ])
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->orderBy('p.name')
            ->get()
            ->map(function ($provider) use ($environment) {
                $credentials = DB::table('data_provider_credentials')
                    ->where('data_provider_id', $provider->id)
                    ->where('environment', $environment)
                    ->orderBy('credential_key')
                    ->get(['id', 'credential_key', 'encrypted_value', 'is_active', 'rotated_at'])
                    ->map(function ($credential) {
                        try {
                            $credential->current_value = Crypt::decryptString($credential->encrypted_value);
                        } catch (Throwable) {
                            $credential->current_value = null;
                        }

                        unset($credential->encrypted_value);

                        return $credential;
                    });

                $mappings = DB::table('league_provider_mappings as lpm')
                    ->join('leagues as l', 'l.id', '=', 'lpm.league_id')
                    ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
                    ->where('lpm.data_provider_id', $provider->id)
                    ->orderBy('c.name')
                    ->orderBy('l.name')
                    ->get([
                        'l.id as league_id',
                        'l.name as league_name',
                        'l.country_id',
                        'c.name as country_name',
                        'lpm.external_id',
                        'lpm.external_name',
                    ]);

                $provider->credentials = $credentials;
                $provider->mappings = $mappings;
                $provider->adapter_supported = app(ProviderAdapterDefinitionRepository::class)->findInstalled($provider->code) !== null;
                $provider->metadata_decoded = json_decode((string) $provider->metadata, true) ?: [];
                $settings = app(ProviderConfigurationReader::class)->values((int) $provider->id);

                foreach (['base_url', 'priority', 'role', 'timeout', 'connect_timeout', 'retry_times', 'retry_sleep_ms', 'plan'] as $key) {
                    if (array_key_exists($key, $settings)) {
                        $provider->{$key} = $settings[$key];
                    }
                }

                return $provider;
            });

        return view('admin.providers.index', compact('providers', 'environment', 'availableAdapters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'code' => $this->normalizeProviderCode((string) $request->input('code')),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:data_providers,code'],
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:500'],
            'role' => ['required', 'in:primary,fallback,audit,statistics'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'plan' => ['nullable', 'string', 'max:100'],
            'credential_required' => ['required', 'boolean'],
            'credential_key' => ['nullable', 'string', 'max:120', 'required_if:credential_required,1'],
            'credential_value' => ['nullable', 'string', 'max:5000', 'required_if:credential_required,1'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'in:competitions,seasons,teams,fixtures,standings,players,statistics'],
        ]);

        $adapter = app(ProviderAdapterDefinitionRepository::class)->findInstalled($data['code']);
        $adapterSupported = is_array($adapter);
        $credentialKey = $data['credential_required'] ? ($data['credential_key'] ?? null) : null;
        $capabilities = array_values(array_unique($data['capabilities'] ?? []));

        DB::transaction(function () use ($data, $adapterSupported, $credentialKey, $capabilities): void {
            $providerId = DB::table('data_providers')->insertGetId([
                'code' => $data['code'],
                'name' => $data['name'],
                'base_url' => $data['base_url'],
                'active' => $adapterSupported,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('data_provider_runtime_configs')->insert([
                'data_provider_id' => $providerId,
                'is_enabled' => $adapterSupported,
                'priority' => $data['priority'],
                'role' => $data['role'],
                'base_url' => $data['base_url'],
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_sleep_ms' => 500,
                'plan' => $data['plan'] ?: null,
                'metadata' => json_encode([
                    'capabilities' => $capabilities,
                    'credential_required' => (bool) $data['credential_required'],
                    'credential_key' => $credentialKey,
                    'adapter_supported' => $adapterSupported,
                    'onboarding_state' => $adapterSupported ? 'ready' : 'adapter_required',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(ProviderConfigurationWriter::class)->writeMany($providerId, [
                'base_url' => $data['base_url'],
                'priority' => (int) $data['priority'],
                'role' => $data['role'],
                'plan' => $data['plan'] ?: null,
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_sleep_ms' => 500,
                'credential_required' => (bool) $data['credential_required'],
                'credential_key' => $credentialKey,
                'capabilities' => $capabilities,
                'adapter_supported' => $adapterSupported,
            ]);

            if ($credentialKey !== null) {
                DB::table('data_provider_credentials')->insert([
                    'data_provider_id' => $providerId,
                    'environment' => app()->environment(),
                    'credential_key' => $credentialKey,
                    'encrypted_value' => Crypt::encryptString($data['credential_value']),
                    'is_active' => true,
                    'rotated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with(
            'status',
            $adapterSupported
                ? 'Provider registrato e attivato nel runtime.'
                : 'Provider registrato. Rimane disattivato finché non viene installato il relativo adapter applicativo.'
        );
    }

    private function normalizeProviderCode(string $code): string
    {
        $code = strtolower(trim($code));
        $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?? '';

        return trim($code, '_');
    }

    public function update(Request $request, int $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'url', 'max:500'],
            'role' => ['required', 'in:primary,fallback,audit,statistics'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'plan' => ['nullable', 'string', 'max:100'],
            'timeout' => ['required', 'integer', 'min:1', 'max:300'],
            'connect_timeout' => ['required', 'integer', 'min:1', 'max:120'],
            'retry_times' => ['required', 'integer', 'min:0', 'max:10'],
            'retry_sleep_ms' => ['required', 'integer', 'min:0', 'max:60000'],
        ]);

        DB::transaction(function () use ($provider, $data): void {
            DB::table('data_providers')->where('id', $provider)->update([
                'name' => $data['name'],
                'base_url' => $data['base_url'],
                'updated_at' => now(),
            ]);

            DB::table('data_provider_runtime_configs')->updateOrInsert(
                ['data_provider_id' => $provider],
                [
                    'priority' => $data['priority'],
                    'role' => $data['role'],
                    'base_url' => $data['base_url'],
                    'timeout' => $data['timeout'],
                    'connect_timeout' => $data['connect_timeout'],
                    'retry_times' => $data['retry_times'],
                    'retry_sleep_ms' => $data['retry_sleep_ms'],
                    'plan' => $data['plan'] ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            app(ProviderConfigurationWriter::class)->writeMany($provider, [
                'base_url' => $data['base_url'],
                'priority' => (int) $data['priority'],
                'role' => $data['role'],
                'plan' => $data['plan'] ?: null,
                'timeout' => (int) $data['timeout'],
                'connect_timeout' => (int) $data['connect_timeout'],
                'retry_times' => (int) $data['retry_times'],
                'retry_sleep_ms' => (int) $data['retry_sleep_ms'],
            ]);
        });

        return back()->with('status', 'Configurazione provider aggiornata.');
    }

    public function toggle(int $provider): RedirectResponse
    {
        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        if (app(ProviderAdapterDefinitionRepository::class)->findInstalled($catalogProvider->code) === null) {
            return back()->withErrors([
                'provider' => 'Impossibile attivare il provider: adapter applicativo non installato.',
            ]);
        }

        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->first();
        abort_unless($runtime, 404);

        $enabled = ! (bool) $runtime->is_enabled;

        DB::transaction(function () use ($provider, $enabled): void {
            DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->update([
                'is_enabled' => $enabled,
                'updated_at' => now(),
            ]);
            DB::table('data_providers')->where('id', $provider)->update([
                'active' => $enabled,
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', $enabled ? 'Provider attivato.' : 'Provider disattivato. Mapping e storico sono stati conservati.');
    }

    public function rotateCredential(Request $request, int $provider): RedirectResponse
    {
        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        $adapter = app(ProviderAdapterDefinitionRepository::class)->findInstalled($catalogProvider->code);
        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->first();
        $metadata = json_decode((string) ($runtime->metadata ?? ''), true) ?: [];
        $credentialKey = is_array($adapter)
            ? ($adapter['credential_key'] ?? null)
            : ($metadata['credential_key'] ?? null);

        if (empty($credentialKey)) {
            return back()->withErrors([
                'provider' => 'Questo provider non richiede una credenziale oppure il nome tecnico non è configurato.',
            ]);
        }

        $data = $request->validate([
            'credential_value' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('data_provider_credentials')->updateOrInsert(
            [
                'data_provider_id' => $provider,
                'environment' => app()->environment(),
                'credential_key' => $credentialKey,
            ],
            [
                'encrypted_value' => Crypt::encryptString($data['credential_value']),
                'is_active' => true,
                'rotated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('status', 'Credenziale cifrata salvata e ruotata per l’ambiente corrente.');
    }

    public function configureHttpAdapter(int $provider): View
    {
        $providerRow = DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->where('p.id', $provider)
            ->select([
                'p.id',
                'p.code',
                'p.name',
                'p.base_url',
                'p.active',
                'rc.is_enabled',
                'rc.metadata',
            ])
            ->first();

        abort_unless($providerRow, 404);

        $metadata = json_decode((string) ($providerRow->metadata ?? ''), true) ?: [];

        return view('admin.providers.http-adapter', [
            'provider' => $providerRow,
            'metadata' => $metadata,
            'capabilities' => ['competitions', 'seasons', 'teams'],
            'testResult' => session('http_adapter_test_result'),
            'testInput' => session('http_adapter_test_input', []),
        ]);
    }

    public function testHttpAdapter(Request $request, int $provider): RedirectResponse
    {
        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $data = $request->validate([
            'capability' => ['required', 'in:competitions,seasons,teams'],
            'method' => ['required', 'in:GET,POST'],
            'endpoint' => ['required', 'string', 'max:250'],
            'query_params' => ['nullable', 'string', 'max:4000'],
            'body_template' => ['nullable', 'string', 'max:8000'],
            'items_path' => ['nullable', 'string', 'max:250'],
            'field_mappings' => ['nullable', 'string', 'max:4000'],
        ]);

        $url = $this->buildProviderUrl((string) $providerRow->base_url, $data['endpoint']);
        $query = $this->parseKeyValueLines($data['query_params'] ?? '');
        $fieldMappings = $this->parseKeyValueLines($data['field_mappings'] ?? '');

        try {
            $response = $data['method'] === 'POST'
                ? Http::timeout(15)->acceptJson()->post($url, $this->parseJsonBody($data['body_template'] ?? ''))
                : Http::timeout(15)->acceptJson()->get($url, $query);

            $json = $response->json();
            $items = $this->extractItems($json, $data['items_path'] ?? '');
            $firstItem = $items[0] ?? null;

            $result = [
                'ok' => $response->successful(),
                'resolved_url' => $url,
                'status' => $response->status(),
                'items_count' => count($items),
                'first_item' => $firstItem,
                'normalized_preview' => is_array($firstItem)
                    ? $this->mapFields($firstItem, $fieldMappings)
                    : null,
                'raw_preview' => $this->limitPayload($json),
            ];
        } catch (ConnectionException | RequestException | Throwable $e) {
            $result = [
                'ok' => false,
                'resolved_url' => $url,
                'status' => null,
                'items_count' => 0,
                'first_item' => null,
                'normalized_preview' => null,
                'raw_preview' => null,
                'error' => $e->getMessage(),
            ];
        }

        return back()
            ->with('http_adapter_test_result', $result)
            ->with('http_adapter_test_input', $data);
    }

    private function buildProviderUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueLines(string $value): array
    {
        return collect(preg_split('/\R/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

                return [trim($key) => trim($value)];
            })
            ->filter(fn (string $value, string $key): bool => $key !== '')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonBody(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  mixed  $payload
     * @return list<mixed>
     */
    private function extractItems(mixed $payload, ?string $itemsPath): array
    {
        $items = filled($itemsPath)
            ? data_get($payload, (string) $itemsPath)
            : $payload;

        if (! is_array($items)) {
            return [];
        }

        return Arr::isAssoc($items) ? [$items] : array_values($items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMappings
     * @return array<string, mixed>
     */
    private function mapFields(array $item, array $fieldMappings): array
    {
        return collect($fieldMappings)
            ->mapWithKeys(fn (string $sourcePath, string $targetField): array => [
                $targetField => data_get($item, $sourcePath),
            ])
            ->all();
    }

    private function limitPayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        return collect($payload)
            ->map(fn (mixed $value): mixed => is_array($value) && ! Arr::isAssoc($value)
                ? array_slice($value, 0, 3)
                : $value)
            ->all();
    }
}
