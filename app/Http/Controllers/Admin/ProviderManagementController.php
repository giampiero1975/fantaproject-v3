<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Providers\ProviderConfigurationReader;
use App\Services\Providers\ProviderConfigurationWriter;
use App\Services\Providers\HttpProviderPayloadMapper;
use App\Services\Providers\ProviderHttpAuthentication;
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

                $provider->credentials = $credentials;
                $httpMappings = DB::table('data_provider_http_endpoints as e')
                    ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
                    ->where('data_provider_id', $provider->id)
                    ->orderBy('e.capability')
                    ->orderBy('e.operation')
                    ->get([
                        'e.id',
                        'e.capability',
                        'e.operation',
                        'e.label',
                        'e.method',
                        'e.endpoint',
                        'e.query_params',
                        'e.items_path',
                        'e.is_enabled',
                        'e.validation_status',
                        'e.last_status_code',
                        'm.field_mappings',
                        'm.validation_status as mapping_validation_status',
                    ])
                    ->map(function (object $endpoint): object {
                        $endpoint->query_params_decoded = json_decode((string) $endpoint->query_params, true) ?: [];
                        $endpoint->field_mappings_decoded = json_decode((string) $endpoint->field_mappings, true) ?: [];

                        return $endpoint;
                    });

                $provider->http_mappings = $httpMappings;
                $provider->http_mappings_count = $httpMappings->count();
                $provider->metadata_decoded = json_decode((string) $provider->metadata, true) ?: [];
                $settings = app(ProviderConfigurationReader::class)->values((int) $provider->id);

                foreach (['base_url', 'priority', 'role', 'timeout', 'connect_timeout', 'retry_times', 'retry_sleep_ms', 'plan', 'auth_type', 'credential_key', 'auth_header_name', 'auth_query_param', 'http_headers'] as $key) {
                    if (array_key_exists($key, $settings)) {
                        $provider->{$key} = $settings[$key];
                    }
                }

                $provider->auth_type ??= 'none';
                $provider->credential_key ??= $provider->metadata_decoded['credential_key'] ?? null;
                $provider->auth_header_name ??= null;
                $provider->auth_query_param ??= null;
                $provider->http_headers ??= [];
                $provider->http_headers_text = $this->formatKeyValueLines(is_array($provider->http_headers) ? $provider->http_headers : []);

                return $provider;
            });

        return view('admin.providers.index', compact('providers', 'environment'));
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
            'capabilities.*' => ['string', 'in:countries,competitions,seasons,teams,fixtures,standings,players,statistics'],
        ]);

        $credentialKey = $data['credential_required'] ? ($data['credential_key'] ?? null) : null;
        $capabilities = array_values(array_unique($data['capabilities'] ?? []));

        $this->providerLog('provider_registration', 'debug', 'Provider registration validated.', [
            'code' => $data['code'],
            'credential_required' => (bool) $data['credential_required'],
            'credential_key' => $credentialKey,
            'capabilities' => $capabilities,
        ]);

        $providerId = null;

        DB::transaction(function () use ($data, $credentialKey, $capabilities, &$providerId): void {
            $providerId = DB::table('data_providers')->insertGetId([
                'code' => $data['code'],
                'name' => $data['name'],
                'base_url' => $data['base_url'],
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('data_provider_runtime_configs')->insert([
                'data_provider_id' => $providerId,
                'is_enabled' => false,
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
                    'onboarding_state' => 'configure_runtime',
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
            'runtime_enabled' => false,
            'onboarding_state' => 'configure_runtime',
        ]);

        return back()->with(
            'status',
            'Provider registrato. Configura le chiamate runtime da Provider Management per renderlo operativo.'
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
            'auth_type' => ['required', 'in:none,header,query'],
            'credential_key' => ['nullable', 'string', 'max:120', 'required_unless:auth_type,none'],
            'auth_header_name' => ['nullable', 'string', 'max:120', 'required_if:auth_type,header'],
            'auth_query_param' => ['nullable', 'string', 'max:120', 'required_if:auth_type,query'],
            'http_headers' => ['nullable', 'string', 'max:2000'],
        ]);

        $httpHeaders = $this->parseKeyValueLines($data['http_headers'] ?? '');

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
            'auth_type' => $data['auth_type'],
            'credential_key' => $data['credential_key'] ?? null,
            'auth_header_name' => $data['auth_header_name'] ?? null,
            'auth_query_param' => $data['auth_query_param'] ?? null,
            'http_header_keys' => array_keys($httpHeaders),
        ]);

        DB::transaction(function () use ($provider, $data, $httpHeaders): void {
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
                'auth_type' => $data['auth_type'],
                'credential_key' => $data['credential_key'] ?? null,
                'auth_header_name' => $data['auth_header_name'] ?? null,
                'auth_query_param' => $data['auth_query_param'] ?? null,
                'http_headers' => $httpHeaders,
            ]);
        });

        $this->providerLog('provider_configuration', 'info', 'Provider configuration updated.', [
            'provider_id' => $provider,
            'name' => $data['name'],
        ]);

        return back()->with('status', 'Configurazione provider aggiornata.');
    }

    public function destroy(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('provider_deletion', 'info', 'Provider deletion requested.', [
            'provider_id' => $provider,
        ]);

        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        $confirmationCode = trim((string) $request->input('confirmation_code'));

        if ($confirmationCode === '') {
            $this->providerLog('provider_deletion', 'warning', 'Provider deletion blocked: confirmation code missing.', [
                'provider_id' => $provider,
                'provider_code' => $catalogProvider->code,
            ]);

            return back()->withErrors([
                'provider_deletion' => "Per eliminare {$catalogProvider->name} devi prima digitare il codice provider: {$catalogProvider->code}.",
            ]);
        }

        if ($confirmationCode !== $catalogProvider->code) {
            $this->providerLog('provider_deletion', 'warning', 'Provider deletion blocked: confirmation code mismatch.', [
                'provider_id' => $provider,
                'provider_code' => $catalogProvider->code,
                'confirmation_code' => $confirmationCode,
            ]);

            return back()->withErrors([
                'provider_deletion' => "Per eliminare {$catalogProvider->name} devi digitare esattamente il codice provider: {$catalogProvider->code}.",
            ]);
        }

        $deleted = [
            'league_season_provider_mappings' => 0,
            'league_provider_mappings' => 0,
            'league_aliases' => 0,
            'data_provider_payload_mappings' => 0,
            'data_provider_http_endpoints' => 0,
            'data_provider_credentials' => 0,
            'data_provider_runtime_configs' => 0,
            'data_provider_configurations' => 0,
            'data_providers' => 0,
        ];

        DB::transaction(function () use ($provider, &$deleted): void {
            $endpointIds = DB::table('data_provider_http_endpoints')
                ->where('data_provider_id', $provider)
                ->pluck('id')
                ->all();

            $deleted['league_season_provider_mappings'] = DB::table('league_season_provider_mappings')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['league_provider_mappings'] = DB::table('league_provider_mappings')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['league_aliases'] = DB::table('league_aliases')
                ->where('data_provider_id', $provider)
                ->delete();

            if ($endpointIds !== []) {
                $deleted['data_provider_payload_mappings'] = DB::table('data_provider_payload_mappings')
                    ->whereIn('data_provider_http_endpoint_id', $endpointIds)
                    ->delete();
            }

            $deleted['data_provider_http_endpoints'] = DB::table('data_provider_http_endpoints')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['data_provider_credentials'] = DB::table('data_provider_credentials')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['data_provider_runtime_configs'] = DB::table('data_provider_runtime_configs')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['data_provider_configurations'] = DB::table('data_provider_configurations')
                ->where('data_provider_id', $provider)
                ->delete();

            $deleted['data_providers'] = DB::table('data_providers')
                ->where('id', $provider)
                ->delete();
        });

        $this->providerLog('provider_deletion', 'info', 'Provider deleted with related runtime configuration.', [
            'provider_id' => $provider,
            'provider_code' => $catalogProvider->code,
            'provider_name' => $catalogProvider->name,
            'deleted' => $deleted,
        ]);

        return redirect()
            ->route('admin.providers.index')
            ->with('status', "Provider {$catalogProvider->name} eliminato con chiamate, mapping runtime, credenziali e collegamenti.");
    }

    public function toggle(Request $request, int $provider): RedirectResponse
    {
        $this->providerLog('provider_runtime', 'info', 'Provider runtime toggle requested.', [
            'provider_id' => $provider,
        ]);

        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        $hasConfiguredHttpRuntime = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $provider)
            ->where('is_enabled', true)
            ->exists();

        if (! $hasConfiguredHttpRuntime) {
            $this->providerLog('provider_runtime', 'warning', 'Provider runtime toggle blocked: no runtime configuration.', [
                'provider_id' => $provider,
                'provider_code' => $catalogProvider->code,
            ]);

            return back()->withErrors([
                'provider' => 'Impossibile attivare il provider: nessuna configurazione runtime disponibile.',
            ]);
        }

        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->first();
        abort_unless($runtime, 404);

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $data['is_enabled'];

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

        $runtime = DB::table('data_provider_runtime_configs')->where('data_provider_id', $provider)->first();
        $metadata = json_decode((string) ($runtime->metadata ?? ''), true) ?: [];
        $settings = app(ProviderConfigurationReader::class)->values($provider);
        $credentialKey = $request->input('credential_key')
            ?: ($settings['credential_key'] ?? null)
            ?: ($metadata['credential_key'] ?? null);

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

    public function configureHttpAdapter(Request $request, int $provider): View
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
                'e.label',
                'e.method',
                'e.endpoint',
                'e.query_params',
                'e.body_template',
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
                $endpoint->body_template_decoded = json_decode((string) $endpoint->body_template, true) ?: [];
                $endpoint->field_mappings_decoded = json_decode((string) $endpoint->field_mappings, true) ?: [];
                $endpoint->required_fields_decoded = json_decode((string) $endpoint->required_fields, true) ?: [];

                return $endpoint;
            });
        $isNewForm = $request->boolean('new');
        $isLoadingSavedEndpoint = ! $isNewForm
            && $request->filled('capability')
            && $request->filled('operation');
        $sessionInput = $isNewForm ? [] : session('http_adapter_test_input', []);

        $currentCapability = (string) ($isLoadingSavedEndpoint
            ? $request->query('capability')
            : ($sessionInput['capability'] ?? 'competitions'));
        $currentOperation = (string) ($isLoadingSavedEndpoint
            ? $request->query('operation')
            : ($sessionInput['operation'] ?? 'list'));
        $currentEndpoint = $isLoadingSavedEndpoint
            ? $savedEndpoints
                ->where('capability', $currentCapability)
                ->firstWhere('operation', $currentOperation)
            : null;
        $providerPreset = $this->httpAdapterPreset();
        $formInput = $this->httpAdapterFormInput(
            $sessionInput,
            $currentEndpoint,
            $providerPreset,
        );
        $formInput['loaded_endpoint_id'] = (string) ($currentEndpoint?->id ?? ($sessionInput['loaded_endpoint_id'] ?? ''));

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
            'is_new_form' => $isNewForm,
            'is_loading_saved_endpoint' => $isLoadingSavedEndpoint,
        ]);

        return view('admin.providers.http-adapter', [
            'provider' => $providerRow,
            'metadata' => $metadata,
            'capabilities' => $this->httpAdapterCapabilities(),
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

        $testVariables = $this->parseKeyValueLines($data['test_variables'] ?? '');
        $endpoint = $this->renderTemplate($data['endpoint'], $testVariables);
        $url = $this->buildProviderUrl((string) $providerRow->base_url, $endpoint);
        $bodyTemplate = $this->renderTemplateArray($this->parseJsonBody($data['body_template'] ?? ''), $testVariables);
        $query = array_merge(
            app(ProviderHttpAuthentication::class)->queryParameters((int) $providerRow->id),
            $this->renderTemplateArray($this->parseKeyValueLines($data['query_params'] ?? ''), $testVariables),
        );
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

        $unresolvedVariables = $this->unresolvedTemplateVariables([
            'endpoint' => $endpoint,
            'query_params' => Arr::except($query, array_keys(app(ProviderHttpAuthentication::class)->queryParameters((int) $providerRow->id))),
            'body_template' => $bodyTemplate,
        ]);

        if ($unresolvedVariables !== []) {
            $this->providerLog('http_adapter_test', 'warning', 'HTTP adapter test blocked: unresolved template variables.', [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'unresolved_variables' => $unresolvedVariables,
                'test_variable_keys' => array_keys($testVariables),
            ]);

            return back()
                ->withErrors([
                    'test_variables' => 'Mancano valori test per queste variabili: '.implode(', ', $unresolvedVariables).'. Inseriscili in Valori test variabili, ad esempio provider_country_id=6.',
                ])
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
            'template_endpoint' => $data['endpoint'],
            'test_variable_keys' => array_keys($testVariables),
            'items_path' => $data['items_path'] ?? '',
            'mapped_fields' => array_keys($fieldMappings),
        ]);

        try {
            $pendingRequest = $this->httpAdapterRequest($providerRow);
            $response = $data['method'] === 'POST'
                ? $pendingRequest->post($url, $bodyTemplate)
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
                'resolved_query' => $query,
                'template_endpoint' => $data['endpoint'],
                'test_variables' => $testVariables,
                'status' => $response->status(),
                'items_count' => count($items),
                'first_item' => $firstItem,
                'normalized_preview' => is_array($firstItem)
                    ? $this->mapFields($firstItem, $fieldMappings)
                    : null,
                'raw_preview' => $this->limitPayload($json),
                'warning' => $warning,
            ];

            if (! $response->successful()) {
                $warning = 'La chiamata HTTP ha restituito stato '.$response->status().'. Controlla il log http_adapter_test per il dettaglio della risposta.';
                $result['warning'] = $warning;

                $this->providerLog('http_adapter_test', 'warning', 'HTTP adapter test returned an unsuccessful response.', [
                    'provider_id' => $provider,
                    'provider_code' => $providerRow->code,
                    'capability' => $data['capability'],
                    'operation' => $data['operation'],
                    'status' => $response->status(),
                    'content_type' => $response->header('content-type'),
                    'body_preview' => Str::limit($response->body(), 1000),
                    'resolved_query_keys' => array_keys($query),
                ]);
            }

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
        $existingEndpoint = DB::table('data_provider_http_endpoints')
            ->where('data_provider_id', $provider)
            ->where('capability', $data['capability'])
            ->where('operation', $data['operation'])
            ->first(['id', 'label', 'capability', 'operation']);
        $loadedEndpointId = filled($data['loaded_endpoint_id'] ?? null)
            ? (int) $data['loaded_endpoint_id']
            : null;

        if ($existingEndpoint !== null && $loadedEndpointId !== (int) $existingEndpoint->id) {
            $label = $existingEndpoint->label ?: "{$existingEndpoint->capability} · {$existingEndpoint->operation}";

            $this->providerLog('http_adapter_mapping', 'warning', 'HTTP adapter mapping save blocked: existing configuration was not loaded.', [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'existing_endpoint_id' => $existingEndpoint->id,
                'loaded_endpoint_id' => $loadedEndpointId,
            ]);

            return back()
                ->withErrors([
                    'configuration' => "Esiste gia la configurazione {$label}. Usa Carica nel form prima di aggiornarla, oppure scegli un'altra operation.",
                ])
                ->with('http_adapter_test_input', $data)
                ->withInput();
        }

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
                    'label' => ($data['label'] ?? null) ?: null,
                    'method' => $data['method'],
                    'endpoint' => $data['endpoint'],
                    'query_params' => $query !== [] ? json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'body_template' => filled($data['body_template'] ?? '') ? json_encode($this->parseJsonBody($data['body_template']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'items_path' => $data['items_path'] ?: null,
                    'is_enabled' => true,
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
            'is_enabled' => true,
            'last_status_code' => $testBelongsToCurrentForm ? ($testResult['status'] ?? null) : null,
        ]);

        return back()
            ->with('status', 'HTTP adapter salvato nel runtime provider.')
            ->with('http_adapter_test_input', $data)
            ->with('http_adapter_test_result', $testResult);
    }

    public function destroyHttpAdapter(int $provider, int $endpoint): RedirectResponse
    {
        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $endpointRow = DB::table('data_provider_http_endpoints')
            ->where('id', $endpoint)
            ->where('data_provider_id', $provider)
            ->first();

        abort_unless($endpointRow, 404);

        $this->providerLog('http_adapter_mapping', 'info', 'HTTP adapter mapping delete requested.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'endpoint_id' => $endpoint,
            'capability' => $endpointRow->capability,
            'operation' => $endpointRow->operation,
            'endpoint' => $endpointRow->endpoint,
        ]);

        DB::transaction(function () use ($endpoint): void {
            DB::table('data_provider_payload_mappings')
                ->where('data_provider_http_endpoint_id', $endpoint)
                ->delete();

            DB::table('data_provider_http_endpoints')
                ->where('id', $endpoint)
                ->delete();
        });

        $this->providerLog('http_adapter_mapping', 'info', 'HTTP adapter mapping deleted.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'endpoint_id' => $endpoint,
            'capability' => $endpointRow->capability,
            'operation' => $endpointRow->operation,
        ]);

        return redirect()
            ->route('admin.providers.http-adapter.configure', $provider)
            ->with('status', "Mapping {$endpointRow->capability} · {$endpointRow->operation} eliminato.");
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
            'capability' => ['required', 'in:'.implode(',', $this->httpAdapterCapabilities())],
            'operation' => ['required', 'in:list,detail,search,by_country,by_competition,by_season,by_team'],
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
            'capability' => ['required', 'in:'.implode(',', $this->httpAdapterCapabilities())],
            'operation' => ['required', 'in:list,detail,search,by_country,by_competition,by_season,by_team'],
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

    public function destroyContractField(Request $request, int $provider, string $fieldKey): RedirectResponse
    {
        $providerRow = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($providerRow, 404);

        $data = $request->validate([
            'capability' => ['required', 'in:'.implode(',', $this->httpAdapterCapabilities())],
            'operation' => ['required', 'in:list,detail,search,by_country,by_competition,by_season,by_team'],
        ]);

        $fieldKey = $this->normalizeContractFieldKey($fieldKey);

        $exists = DB::table('data_provider_contract_fields')
            ->where('capability', $data['capability'])
            ->where('operation', $data['operation'])
            ->where('field_key', $fieldKey)
            ->exists();

        abort_unless($exists, 404);

        if ($this->contractFieldIsUsedByMapping($provider, $data['capability'], $data['operation'], $fieldKey)) {
            $this->providerLog('contract_fields', 'warning', 'Provider contract field delete blocked: field is still used by a mapping.', [
                'provider_id' => $provider,
                'provider_code' => $providerRow->code,
                'capability' => $data['capability'],
                'operation' => $data['operation'],
                'field_key' => $fieldKey,
            ]);

            return back()->withErrors([
                'contract_field' => "Il campo {$fieldKey} e ancora usato da un mapping salvato. Elimina prima il mapping runtime oppure togli il campo dal Field mapping.",
            ]);
        }

        DB::table('data_provider_contract_fields')
            ->where('capability', $data['capability'])
            ->where('operation', $data['operation'])
            ->where('field_key', $fieldKey)
            ->delete();

        $this->providerLog('contract_fields', 'info', 'Provider contract field deleted.', [
            'provider_id' => $provider,
            'provider_code' => $providerRow->code,
            'capability' => $data['capability'],
            'operation' => $data['operation'],
            'field_key' => $fieldKey,
        ]);

        return redirect()
            ->route('admin.providers.http-adapter.configure', $provider)
            ->with('status', "Campo contratto {$fieldKey} eliminato.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateHttpAdapterInput(Request $request): array
    {
        return $request->validate([
            'capability' => ['required', 'in:'.implode(',', $this->httpAdapterCapabilities())],
            'operation' => ['required', 'in:list,detail,search,by_country,by_competition,by_season,by_team'],
            'label' => ['nullable', 'string', 'max:150'],
            'method' => ['required', 'in:GET,POST'],
            'endpoint' => ['required', 'string', 'max:250'],
            'query_params' => ['nullable', 'string', 'max:4000'],
            'body_template' => ['nullable', 'string', 'max:8000'],
            'items_path' => ['nullable', 'string', 'max:250'],
            'field_mappings' => ['nullable', 'string', 'max:4000'],
            'test_variables' => ['nullable', 'string', 'max:4000'],
            'loaded_endpoint_id' => ['nullable', 'integer'],
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

    private function contractFieldIsUsedByMapping(int $provider, string $capability, string $operation, string $fieldKey): bool
    {
        return DB::table('data_provider_http_endpoints as e')
            ->join('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->where('e.data_provider_id', $provider)
            ->where('e.capability', $capability)
            ->where('e.operation', $operation)
            ->get(['m.field_mappings'])
            ->contains(function (object $mapping) use ($fieldKey): bool {
                $fieldMappings = json_decode((string) $mapping->field_mappings, true);

                return is_array($fieldMappings) && array_key_exists($fieldKey, $fieldMappings);
            });
    }

    /**
     * @return array<string, string>
     */
    private function httpAdapterCapabilities(): array
    {
        return [
            'countries',
            'competitions',
            'seasons',
            'teams',
        ];
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
            'by_country' => 'Per nazione',
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
            'by_country' => [
                'when' => 'Quando la richiesta dipende da una nazione gia mappata.',
                'example' => 'league/?country_id={provider_country_id} -> competizioni della nazione',
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
    private function httpAdapterPreset(): array
    {
        return [
            'capability' => 'competitions',
            'operation' => 'list',
            'label' => '',
            'method' => 'GET',
            'endpoint' => '',
            'query_params' => '',
            'body_template' => '',
            'test_variables' => '',
            'items_path' => '',
            'field_mappings' => '',
        ];
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
                'label' => (string) ($savedEndpoint->label ?? ''),
                'method' => (string) $savedEndpoint->method,
                'endpoint' => (string) $savedEndpoint->endpoint,
                'query_params' => $this->keyValueText($savedEndpoint->query_params_decoded),
                'body_template' => $this->jsonText($savedEndpoint->body_template_decoded ?? []),
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
     * @param  array<string, mixed>  $values
     */
    private function jsonText(array $values): string
    {
        if ($values === []) {
            return '';
        }

        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
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
        foreach (['capability', 'operation', 'method', 'endpoint', 'query_params', 'body_template', 'items_path', 'field_mappings', 'test_variables', 'loaded_endpoint_id'] as $key) {
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
        $path = "{$directory}/{$functionality}.log";

        File::ensureDirectoryExists($directory);

        $requestAttribute = "provider_management_log_initialized_{$functionality}";

        if (! request()->attributes->get($requestAttribute, false)) {
            File::put($path, '');

            request()->attributes->set($requestAttribute, true);
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

    private function buildProviderUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        return "{$baseUrl}/{$endpoint}";
    }

    private function renderTemplate(string $value, array $variables): string
    {
        foreach ($variables as $key => $replacement) {
            $value = str_replace('{'.$key.'}', (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function renderTemplateArray(array $value, array $variables): array
    {
        return collect($value)
            ->map(function (mixed $item) use ($variables): mixed {
                if (is_array($item)) {
                    return $this->renderTemplateArray($item, $variables);
                }

                return is_string($item) ? $this->renderTemplate($item, $variables) : $item;
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function unresolvedTemplateVariables(array $values): array
    {
        $variables = [];

        array_walk_recursive($values, function (mixed $value) use (&$variables): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match_all('/\{([A-Za-z0-9_]+)\}/', $value, $matches) > 0) {
                $variables = array_merge($variables, $matches[1]);
            }
        });

        return array_values(array_unique($variables));
    }

    private function httpAdapterRequest(object $provider): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::timeout(15)->acceptJson();

        return app(ProviderHttpAuthentication::class)->applyHeaders($request, (int) $provider->id);
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
     * @param  array<string, mixed>  $value
     */
    private function formatKeyValueLines(array $value): string
    {
        return collect($value)
            ->map(fn (mixed $item, string $key): string => "{$key}={$item}")
            ->implode("\n");
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
        return app(HttpProviderPayloadMapper::class)->extractItems($payload, $itemsPath);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $fieldMappings
     * @return array<string, mixed>
     */
    private function mapFields(array $item, array $fieldMappings): array
    {
        return app(HttpProviderPayloadMapper::class)->mapFields($item, $fieldMappings);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mappedValue(array $item, string $sourcePath): mixed
    {
        return app(HttpProviderPayloadMapper::class)->mappedValue($item, $sourcePath);
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
