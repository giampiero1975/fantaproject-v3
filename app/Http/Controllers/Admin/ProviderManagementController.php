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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
                $provider->http_mappings_count = DB::table('data_provider_http_endpoints')
                    ->where('data_provider_id', $provider->id)
                    ->count();
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
        $this->providerLog('provider_registration', 'info', 'Provider registration requested.', [
            'raw_code' => $request->input('code'),
            'name' => $request->input('name'),
            'base_url' => $request->input('base_url'),
        ]);

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

        $this->providerLog('provider_registration', 'debug', 'Provider registration validated.', [
            'code' => $data['code'],
            'adapter_supported' => $adapterSupported,
            'credential_required' => (bool) $data['credential_required'],
            'credential_key' => $credentialKey,
            'capabilities' => $capabilities,
        ]);

        $providerId = null;

        DB::transaction(function () use ($data, $adapterSupported, $credentialKey, $capabilities, &$providerId): void {
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

        $this->providerLog('provider_registration', 'info', 'Provider registration completed.', [
            'code' => $data['code'],
            'provider_id' => $providerId,
            'runtime_enabled' => $adapterSupported,
            'onboarding_state' => $adapterSupported ? 'ready' : 'adapter_required',
        ]);

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

    private function normalizeContractFieldKey(string $fieldKey): string
    {
        $fieldKey = Str::snake(trim($fieldKey));
        $fieldKey = strtolower($fieldKey);
        $fieldKey = preg_replace('/[^a-z0-9_]+/', '_', $fieldKey) ?? '';

        return trim($fieldKey, '_');
    }

    public function update(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('provider_configuration', 'info', 'Provider configuration update requested.', [
            'provider_id' => $provider,
        ]);

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

        $this->providerLog('provider_configuration', 'debug', 'Provider configuration validated.', [
            'provider_id' => $provider,
            'name' => $data['name'],
            'base_url' => $data['base_url'],
            'role' => $data['role'],
            'priority' => (int) $data['priority'],
            'plan' => $data['plan'] ?: null,
            'timeout' => (int) $data['timeout'],
            'connect_timeout' => (int) $data['connect_timeout'],
            'retry_times' => (int) $data['retry_times'],
            'retry_sleep_ms' => (int) $data['retry_sleep_ms'],
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

        $this->providerLog('provider_configuration', 'info', 'Provider configuration updated.', [
            'provider_id' => $provider,
            'name' => $data['name'],
        ]);

        return back()->with('status', 'Configurazione provider aggiornata.');
    }

    public function toggle(int $provider): RedirectResponse
    {
        $this->providerLog('provider_runtime', 'info', 'Provider runtime toggle requested.', [
            'provider_id' => $provider,
        ]);

        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        if (app(ProviderAdapterDefinitionRepository::class)->findInstalled($catalogProvider->code) === null) {
            $this->providerLog('provider_runtime', 'warning', 'Provider runtime toggle blocked: adapter missing.', [
                'provider_id' => $provider,
                'provider_code' => $catalogProvider->code,
            ]);

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

        $this->providerLog('provider_runtime', 'info', 'Provider runtime toggled.', [
            'provider_id' => $provider,
            'provider_code' => $catalogProvider->code,
            'runtime_enabled' => $enabled,
        ]);

        return back()->with('status', $enabled ? 'Provider attivato.' : 'Provider disattivato. Mapping e storico sono stati conservati.');
    }

    public function rotateCredential(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('provider_credentials', 'info', 'Provider credential rotation requested.', [
            'provider_id' => $provider,
        ]);

        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        $adapter = app(ProviderAdapterDefinitionRepository::class)->findInstalled($catalogProvider->code);
        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->first();
        $metadata = json_decode((string) ($runtime->metadata ?? ''), true) ?: [];
        $credentialKey = is_array($adapter)
            ? ($adapter['credential_key'] ?? null)
            : ($metadata['credential_key'] ?? null);

        if (empty($credentialKey)) {
            $this->providerLog('provider_credentials', 'warning', 'Provider credential rotation blocked: credential key missing.', [
                'provider_id' => $provider,
                'provider_code' => $catalogProvider->code,
            ]);

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

        $this->providerLog('provider_credentials', 'info', 'Provider credential rotated.', [
            'provider_id' => $provider,
            'provider_code' => $catalogProvider->code,
            'credential_key' => $credentialKey,
            'environment' => app()->environment(),
        ]);

        return back()->with('status', 'Credenziale cifrata salvata e ruotata per l’ambiente corrente.');
    }

    public function configureHttpAdapter(int $provider): View
    {
        $this->providerLog('http_adapter_configuration', 'info', 'HTTP adapter configuration page requested.', [
            'provider_id' => $provider,
        ]);

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
        $savedEndpoints = DB::table('data_provider_http_endpoints as e')
            ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->where('e.data_provider_id', $provider)
            ->orderBy('e.capability')
            ->orderBy('e.operation')
            ->get([
                'e.id',
                'e.capability',
                'e.operation',
                'e.method',
                'e.endpoint',
                'e.query_params',
                'e.items_path',
                'e.is_enabled',
                'e.validation_status',
                'e.last_status_code',
                'e.last_tested_at',
                'm.field_mappings',
                'm.required_fields',
                'm.validation_status as mapping_validation_status',
            ])
            ->map(function (object $endpoint): object {
                $endpoint->query_params_decoded = json_decode((string) $endpoint->query_params, true) ?: [];
                $endpoint->field_mappings_decoded = json_decode((string) $endpoint->field_mappings, true) ?: [];
                $endpoint->required_fields_decoded = json_decode((string) $endpoint->required_fields, true) ?: [];

                return $endpoint;
            });
        $currentCapability = (string) session('http_adapter_test_input.capability', 'competitions');
        $currentOperation = (string) session('http_adapter_test_input.operation', 'list');
        $currentEndpoint = $savedEndpoints
            ->where('capability', $currentCapability)
            ->firstWhere('operation', $currentOperation);
        $providerPreset = $this->httpAdapterPreset($providerRow->code);
        $formInput = $this->httpAdapterFormInput(
            session('http_adapter_test_input', []),
            $currentEndpoint,
            $providerPreset,
        );

        $contractCapability = $formInput['capability'] ?? 'competitions';
        $contractOperation = $formInput['operation'] ?? 'list';
        $operations = $this->operations();
        $internalFieldsByOperation = collect(array_keys($operations))
            ->mapWithKeys(fn (string $operation): array => [
                $operation => $this->internalFieldsFor($contractCapability, $operation),
            ])
            ->all();

        $this->providerLog('http_adapter_configuration', 'debug', 'HTTP adapter configuration page resolved.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'saved_endpoints_count' => $savedEndpoints->count(),
            'current_capability' => $currentCapability,
            'current_operation' => $currentOperation,
            'current_endpoint_id' => $currentEndpoint?->id,
        ]);

        return view('admin.providers.http-adapter', [
            'provider' => $providerRow,
            'metadata' => $metadata,
            'capabilities' => ['competitions', 'seasons', 'teams'],
            'operations' => $operations,
            'operationDescriptions' => $this->operationDescriptions(),
            'savedEndpoints' => $savedEndpoints,
            'contractCapability' => $contractCapability,
            'contractOperation' => $contractOperation,
            'internalFieldsByOperation' => $internalFieldsByOperation,
            'unknownContractFields' => session('unknown_contract_fields', []),
            'formInput' => $formInput,
            'testResult' => session('http_adapter_test_result'),
        ]);
    }

    public function testHttpAdapter(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('http_adapter_test', 'info', 'HTTP adapter test requested.', [
            'provider_id' => $provider,
        ]);

        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $data = $this->validateHttpAdapterInput($request);

        $url = $this->buildProviderUrl((string) $providerRow->base_url, $data['endpoint']);
        $query = $this->parseKeyValueLines($data['query_params'] ?? '');
        $fieldMappings = $this->parseKeyValueLines($data['field_mappings'] ?? '');
        $unknownFields = $this->unknownContractFields($data['capability'], $data['operation'], $fieldMappings);

        if ($unknownFields !== []) {
            return back()
                ->withErrors([
                    'field_mappings' => 'Campi non presenti nel contratto: '.implode(', ', $unknownFields).'. Aggiungili prima a Campi interni oppure correggi il mapping.',
                ])
                ->with('unknown_contract_fields', $unknownFields)
                ->with('http_adapter_test_input', $data)
                ->withInput();
        }

        $this->providerLog('http_adapter_test', 'debug', 'HTTP adapter test input parsed.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'method' => $data['method'],
            'url' => $url,
            'query_keys' => array_keys($query),
            'items_path' => $data['items_path'] ?? '',
            'mapped_fields' => array_keys($fieldMappings),
        ]);

        try {
            $pendingRequest = $this->httpAdapterRequest($providerRow);
            $response = $data['method'] === 'POST'
                ? $pendingRequest->post($url, $this->parseJsonBody($data['body_template'] ?? ''))
                : $pendingRequest->get($url, $query);

            $json = $response->json();
            $items = $this->extractItems($json, $data['items_path'] ?? '');
            $firstItem = $items[0] ?? null;
            $warning = null;

            if ($response->successful() && $json === null) {
                $warning = 'Risposta HTTP 200, ma il corpo non e JSON valido o risulta vuoto.';
            } elseif ($response->successful() && count($items) === 0) {
                $warning = filled($data['items_path'] ?? '')
                    ? "Risposta HTTP 200, ma nessun item trovato nel percorso items_path '{$data['items_path']}'."
                    : 'Risposta HTTP 200, ma il payload non contiene un oggetto o una lista mappabile.';
            }

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
                'warning' => $warning,
            ];

            $this->providerLog('http_adapter_test', 'info', 'HTTP adapter test completed.', [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'status' => $response->status(),
                'successful' => $response->successful(),
                'items_count' => count($items),
                'first_item_keys' => is_array($firstItem) ? array_keys($firstItem) : [],
                'normalized_fields' => is_array($result['normalized_preview']) ? array_keys($result['normalized_preview']) : [],
            ]);

            if ($warning !== null) {
                $this->providerLog('http_adapter_test', 'warning', 'HTTP adapter test returned no mappable payload.', [
                    'provider_id' => $provider,
                    'provider_code' => $providerRow->code,
                    'capability' => $data['capability'],
                    'operation' => $data['operation'],
                    'status' => $response->status(),
                    'items_path' => $data['items_path'] ?? '',
                    'json_decoded' => $json !== null,
                    'content_type' => $response->header('content-type'),
                    'body_preview' => Str::limit($response->body(), 500),
                    'warning' => $warning,
                ]);
            }
        } catch (ConnectionException | RequestException | Throwable $e) {
            $result = [
                'ok' => false,
                'resolved_url' => $url,
                'status' => null,
                'items_count' => 0,
                'first_item' => null,
                'normalized_preview' => null,
                'raw_preview' => null,
                'warning' => null,
                'error' => $e->getMessage(),
            ];

            $context = [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'url' => $url,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ];

            $this->providerLog('http_adapter_test', 'error', 'HTTP adapter test failed.', $context);
            Log::error('Administration provider management HTTP adapter test failed.', $context);
        }

        return back()
            ->with('http_adapter_test_result', $result)
            ->with('http_adapter_test_input', $data);
    }

    public function saveHttpAdapter(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('http_adapter_mapping', 'info', 'HTTP adapter mapping save requested.', [
            'provider_id' => $provider,
        ]);

        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $data = $this->validateHttpAdapterInput($request);
        $query = $this->parseKeyValueLines($data['query_params'] ?? '');
        $fieldMappings = $this->parseKeyValueLines($data['field_mappings'] ?? '');
        $unknownFields = $this->unknownContractFields($data['capability'], $data['operation'], $fieldMappings);

        if ($unknownFields !== []) {
            $this->providerLog('http_adapter_mapping', 'warning', 'HTTP adapter mapping save blocked: unknown contract fields.', [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'unknown_fields' => $unknownFields,
            ]);

            return back()
                ->withErrors([
                    'field_mappings' => 'Campi non presenti nel contratto: '.implode(', ', $unknownFields).'. Aggiungili prima a Campi interni oppure correggi il mapping.',
                ])
                ->with('unknown_contract_fields', $unknownFields)
                ->with('http_adapter_test_input', $data)
                ->withInput();
        }

        $requiredFields = $this->requiredFieldsFor($data['capability'], $data['operation']);
        $mappingStatus = $this->mappingStatus($fieldMappings, $requiredFields);
        $testResult = session('http_adapter_test_result');
        $testInput = session('http_adapter_test_input', []);
        $testBelongsToCurrentForm = is_array($testResult)
            && is_array($testInput)
            && $this->sameHttpAdapterInput($data, $testInput);

        $this->providerLog('http_adapter_mapping', 'debug', 'HTTP adapter mapping save input parsed.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'method' => $data['method'],
            'endpoint' => $data['endpoint'],
            'query_keys' => array_keys($query),
            'items_path' => $data['items_path'] ?? '',
            'mapped_fields' => array_keys($fieldMappings),
            'required_fields' => $requiredFields,
            'mapping_status' => $mappingStatus,
            'test_belongs_to_current_form' => $testBelongsToCurrentForm,
        ]);

        DB::transaction(function () use ($provider, $data, $query, $fieldMappings, $requiredFields, $mappingStatus, $testResult, $testBelongsToCurrentForm): void {
            DB::table('data_provider_http_endpoints')->updateOrInsert(
                [
                    'data_provider_id' => $provider,
                    'capability' => $data['capability'],
                    'operation' => $data['operation'],
                ],
                [
                    'method' => $data['method'],
                    'endpoint' => $data['endpoint'],
                    'query_params' => $query !== [] ? json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'body_template' => filled($data['body_template'] ?? '') ? json_encode($this->parseJsonBody($data['body_template']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'items_path' => $data['items_path'] ?: null,
                    'is_enabled' => $mappingStatus === 'mapping_validated',
                    'validation_status' => $testBelongsToCurrentForm && ($testResult['ok'] ?? false)
                        ? 'test_passed'
                        : 'saved_not_tested',
                    'last_status_code' => $testBelongsToCurrentForm ? ($testResult['status'] ?? null) : null,
                    'last_tested_at' => $testBelongsToCurrentForm ? now() : null,
                    'sample_payload' => $testBelongsToCurrentForm ? json_encode($testResult['first_item'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'sample_normalized' => $testBelongsToCurrentForm ? json_encode($testResult['normalized_preview'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $endpointId = DB::table('data_provider_http_endpoints')
                ->where('data_provider_id', $provider)
                ->where('capability', $data['capability'])
                ->where('operation', $data['operation'])
                ->value('id');

            DB::table('data_provider_payload_mappings')->updateOrInsert(
                ['data_provider_http_endpoint_id' => $endpointId],
                [
                    'field_mappings' => json_encode($fieldMappings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'required_fields' => json_encode($requiredFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'validation_status' => $mappingStatus,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        $this->providerLog('http_adapter_mapping', 'info', 'HTTP adapter mapping saved.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'mapping_status' => $mappingStatus,
            'is_enabled' => $mappingStatus === 'mapping_validated',
            'last_status_code' => $testBelongsToCurrentForm ? ($testResult['status'] ?? null) : null,
        ]);

        return back()
            ->with('status', 'HTTP adapter salvato nel runtime provider.')
            ->with('http_adapter_test_input', $data)
            ->with('http_adapter_test_result', $testResult);
    }

    public function storeContractField(Request $request, int $provider): RedirectResponse
    {
        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);
        $rawFieldKey = (string) $request->input('field_key');

        $request->merge([
            'field_key' => $this->normalizeContractFieldKey($rawFieldKey),
        ]);

        $data = $request->validate([
            'capability' => ['required', 'in:competitions,seasons,teams'],
            'operation' => ['required', 'in:list,detail,search,by_competition,by_season,by_team'],
            'field_key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'data_type' => ['required', 'in:string,integer,float,boolean,date,datetime,url,json'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $exists = DB::table('data_provider_contract_fields')
            ->where('capability', $data['capability'])
            ->where('operation', $data['operation'])
            ->where('field_key', $data['field_key'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['contract_field' => "Il campo {$data['field_key']} esiste gia nel contratto {$data['capability']} · {$data['operation']}."])
                ->withInput();
        }

        DB::table('data_provider_contract_fields')->insert([
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'field_key' => $data['field_key'],
            'label' => $data['label'],
            'description' => $data['description'] ?: null,
            'data_type' => $data['data_type'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) $data['sort_order'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->providerLog('contract_fields', 'info', 'Provider contract field created.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'raw_field_key' => $rawFieldKey,
            'field_key' => $data['field_key'],
            'data_type' => $data['data_type'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) $data['sort_order'],
        ]);

        $status = $rawFieldKey !== $data['field_key']
            ? "Campo contratto {$data['field_key']} aggiunto. Nota: {$rawFieldKey} e stato normalizzato in {$data['field_key']}."
            : "Campo contratto {$data['field_key']} aggiunto.";

        return redirect()
            ->route('admin.providers.http-adapter.configure', $provider)
            ->with('status', $status);
    }

    public function updateContractField(Request $request, int $provider, string $fieldKey): RedirectResponse
    {
        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $data = $request->validate([
            'capability' => ['required', 'in:competitions,seasons,teams'],
            'operation' => ['required', 'in:list,detail,search,by_competition,by_season,by_team'],
            'label' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'data_type' => ['required', 'in:string,integer,float,boolean,date,datetime,url,json'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $fieldKey = $this->normalizeContractFieldKey($fieldKey);

        $updated = DB::table('data_provider_contract_fields')
            ->where('capability', $data['capability'])
            ->where('operation', $data['operation'])
            ->where('field_key', $fieldKey)
            ->update([
                'label' => $data['label'],
                'description' => $data['description'] ?: null,
                'data_type' => $data['data_type'],
                'is_required' => (bool) ($data['is_required'] ?? false),
                'sort_order' => (int) $data['sort_order'],
                'updated_at' => now(),
            ]);

        abort_if($updated === 0, 404);

        $this->providerLog('contract_fields', 'info', 'Provider contract field updated.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'field_key' => $fieldKey,
            'data_type' => $data['data_type'],
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) $data['sort_order'],
        ]);

        return redirect()
            ->route('admin.providers.http-adapter.configure', $provider)
            ->with('status', "Campo contratto {$fieldKey} aggiornato.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHttpAdapterInput(Request $request): array
    {
        return $request->validate([
            'capability' => ['required', 'in:competitions,seasons,teams'],
            'operation' => ['required', 'in:list,detail,search,by_competition,by_season,by_team'],
            'method' => ['required', 'in:GET,POST'],
            'endpoint' => ['required', 'string', 'max:250'],
            'query_params' => ['nullable', 'string', 'max:4000'],
            'body_template' => ['nullable', 'string', 'max:8000'],
            'items_path' => ['nullable', 'string', 'max:250'],
            'field_mappings' => ['nullable', 'string', 'max:4000'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function requiredFieldsFor(string $capability, string $operation): array
    {
        return $this->contractFieldsFor($capability, $operation)
            ->filter(fn (object $field): bool => (bool) $field->is_required)
            ->pluck('field_key')
            ->all();
    }

    /**
     * @return array<string, array{required: bool, description: string}>
     */
    private function internalFieldsFor(string $capability, string $operation): array
    {
        return $this->contractFieldsFor($capability, $operation)
            ->mapWithKeys(fn (object $field): array => [
                $field->field_key => [
                    'required' => (bool) $field->is_required,
                    'label' => (string) $field->label,
                    'description' => (string) ($field->description ?? ''),
                    'data_type' => (string) $field->data_type,
                    'sort_order' => (int) $field->sort_order,
                ],
            ])
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function contractFieldsFor(string $capability, string $operation): \Illuminate\Support\Collection
    {
        return DB::table('data_provider_contract_fields')
            ->where('capability', $capability)
            ->where('operation', $operation)
            ->orderBy('sort_order')
            ->orderBy('field_key')
            ->get(['field_key', 'label', 'description', 'data_type', 'is_required', 'sort_order']);
    }

    /**
     * @return array<string, string>
     */
    private function operations(): array
    {
        return [
            'list' => 'Lista / collezione',
            'detail' => 'Dettaglio singolo',
            'search' => 'Ricerca',
            'by_competition' => 'Per competizione',
            'by_season' => 'Per stagione',
            'by_team' => 'Per squadra',
        ];
    }

    /**
     * @return array<string, array{when: string, example: string}>
     */
    private function operationDescriptions(): array
    {
        return [
            'list' => [
                'when' => 'Quando l endpoint restituisce una lista di record della capability scelta.',
                'example' => 'competitions -> lista competizioni, items_path = competitions',
            ],
            'detail' => [
                'when' => 'Quando l endpoint restituisce un singolo oggetto identificato da un codice o ID esterno.',
                'example' => 'competitions/SA -> dettaglio Serie A, items_path vuoto',
            ],
            'search' => [
                'when' => 'Quando l endpoint cerca record usando parametri liberi o filtri testuali.',
                'example' => 'search_all_leagues.php?c={country_name} -> competizioni filtrate per paese',
            ],
            'by_competition' => [
                'when' => 'Quando la richiesta dipende da una competizione gia mappata.',
                'example' => 'teams?competition=SA oppure seasons?league=135',
            ],
            'by_season' => [
                'when' => 'Quando la richiesta dipende da una stagione gia scelta o mappata.',
                'example' => 'teams?season=2025 oppure fixtures?season=2025',
            ],
            'by_team' => [
                'when' => 'Quando la richiesta dipende da una squadra gia mappata.',
                'example' => 'players?team=123 oppure fixtures?team=123',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function httpAdapterPreset(string $providerCode): array
    {
        return match ($providerCode) {
            'football_data' => [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'competitions',
                'query_params' => '',
                'body_template' => '',
                'items_path' => '',
                'field_mappings' => '',
            ],
            'api_football' => [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'leagues',
                'query_params' => 'id=135',
                'body_template' => '',
                'items_path' => '',
                'field_mappings' => '',
            ],
            'thesportsdb' => [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => 'search_all_leagues.php',
                'query_params' => 'c=Italy',
                'body_template' => '',
                'items_path' => '',
                'field_mappings' => '',
            ],
            default => [
                'capability' => 'competitions',
                'operation' => 'list',
                'method' => 'GET',
                'endpoint' => '',
                'query_params' => '',
                'body_template' => '',
                'items_path' => '',
                'field_mappings' => '',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $sessionInput
     * @param  array<string, string>  $preset
     * @return array<string, string>
     */
    private function httpAdapterFormInput(array $sessionInput, ?object $savedEndpoint, array $preset): array
    {
        $savedInput = [];

        if ($savedEndpoint !== null) {
            $savedInput = [
                'capability' => (string) $savedEndpoint->capability,
                'operation' => (string) $savedEndpoint->operation,
                'method' => (string) $savedEndpoint->method,
                'endpoint' => (string) $savedEndpoint->endpoint,
                'query_params' => $this->keyValueText($savedEndpoint->query_params_decoded),
                'body_template' => '',
                'items_path' => (string) ($savedEndpoint->items_path ?? ''),
                'field_mappings' => $this->keyValueText($savedEndpoint->field_mappings_decoded),
            ];
        }

        return array_merge($preset, $savedInput, array_filter($sessionInput, fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function keyValueText(array $values): string
    {
        return collect($values)
            ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
            ->implode("\n");
    }

    /**
     * @param  array<string, string>  $fieldMappings
     * @param  list<string>  $requiredFields
     */
    private function mappingStatus(array $fieldMappings, array $requiredFields): string
    {
        foreach ($requiredFields as $field) {
            if (blank($fieldMappings[$field] ?? null)) {
                return 'mapping_incomplete';
            }
        }

        return 'mapping_validated';
    }

    /**
     * @param  array<string, string>  $fieldMappings
     * @return list<string>
     */
    private function unknownContractFields(string $capability, string $operation, array $fieldMappings): array
    {
        $allowedFields = $this->contractFieldsFor($capability, $operation)
            ->pluck('field_key')
            ->all();

        return array_values(array_diff(array_keys($fieldMappings), $allowedFields));
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $previous
     */
    private function sameHttpAdapterInput(array $current, array $previous): bool
    {
        foreach (['capability', 'operation', 'method', 'endpoint', 'query_params', 'body_template', 'items_path', 'field_mappings'] as $key) {
            if (($current[$key] ?? null) !== ($previous[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function providerLog(string $functionality, string $level, string $message, array $context = []): void
    {
        $functionality = preg_replace('/[^a-z0-9_]+/', '_', strtolower($functionality)) ?: 'general';
        $level = preg_replace('/[^a-z]+/', '', strtolower($level)) ?: 'info';
        $directory = storage_path('logs/administration/provider_managment');
        $path = "{$directory}/provider_management.log";

        File::ensureDirectoryExists($directory);

        if (! request()->attributes->get('provider_management_log_initialized', false)) {
            if (! $this->shouldPreserveProviderManagementLog()) {
                File::put($path, '');
            }

            request()->attributes->set('provider_management_log_initialized', true);
        }

        $logger = Log::build([
            'driver' => 'single',
            'path' => $path,
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ]);

        $context = array_merge([
            'menu' => 'Administration',
            'section' => 'Provider Management',
            'functionality' => $functionality,
            'level' => $level,
            'user_id' => auth()->id(),
            'request_method' => request()->method(),
            'request_path' => request()->path(),
        ], $context);

        $logger->log($level, "[{$functionality}][{$level}] {$message}", $context);
    }

    private function shouldPreserveProviderManagementLog(): bool
    {
        if (! request()->isMethod('GET')) {
            return false;
        }

        return session()->has('http_adapter_test_result')
            || session()->has('http_adapter_test_input')
            || session()->has('unknown_contract_fields')
            || session()->has('errors')
            || session()->has('status');
    }

    private function buildProviderUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    private function httpAdapterRequest(object $provider): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout(15)->acceptJson();
        $adapter = app(ProviderAdapterDefinitionRepository::class)->findInstalled((string) $provider->code);

        if (! is_array($adapter) || blank($adapter['credential_key'] ?? null)) {
            return $request;
        }

        $credential = DB::table('data_provider_credentials')
            ->where('data_provider_id', $provider->id)
            ->where('environment', app()->environment())
            ->where('credential_key', $adapter['credential_key'])
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            return $request;
        }

        try {
            $value = Crypt::decryptString($credential->encrypted_value);
        } catch (Throwable) {
            return $request;
        }

        if ($value === '') {
            return $request;
        }

        return match ((string) $provider->code) {
            'football_data' => $request->withHeaders(['X-Auth-Token' => $value]),
            'api_football' => $request->withHeaders(['x-apisports-key' => $value]),
            default => $request,
        };
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
