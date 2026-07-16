<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
                $provider->adapter_supported = array_key_exists($provider->code, config('data_provider_adapters', []));

                return $provider;
            });

        $registeredCodes = $providers->pluck('code')->all();
        $availableAdapters = collect(config('data_provider_adapters', []))
            ->reject(fn (array $adapter, string $code) => in_array($code, $registeredCodes, true));

        return view('admin.providers.index', compact('providers', 'environment', 'availableAdapters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $adapterCatalog = config('data_provider_adapters', []);

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::in(array_keys($adapterCatalog)),
                'unique:data_providers,code',
            ],
            'base_url' => ['required', 'url', 'max:500'],
            'role' => ['required', 'in:primary,fallback,audit,statistics'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'plan' => ['nullable', 'string', 'max:100'],
            'credential_value' => ['nullable', 'string', 'max:5000'],
        ], [
            'code.in' => 'Il provider selezionato non dispone di un adapter applicativo installato.',
        ]);

        $adapter = $adapterCatalog[$data['code']];
        $credentialKey = $adapter['credential_key'] ?? null;

        DB::transaction(function () use ($data, $adapter, $credentialKey): void {
            $providerId = DB::table('data_providers')->insertGetId([
                'code' => $data['code'],
                'name' => $adapter['name'],
                'base_url' => $data['base_url'],
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('data_provider_runtime_configs')->insert([
                'data_provider_id' => $providerId,
                'is_enabled' => true,
                'priority' => $data['priority'],
                'role' => $data['role'],
                'base_url' => $data['base_url'],
                'timeout' => 30,
                'connect_timeout' => 10,
                'retry_times' => 3,
                'retry_sleep_ms' => 500,
                'plan' => $data['plan'] ?: null,
                'metadata' => json_encode([
                    'capabilities' => $adapter['capabilities'] ?? [],
                    'adapter_supported' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($credentialKey !== null && ($data['credential_value'] ?? '') !== '') {
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

        return back()->with('status', 'Provider supportato registrato e inserito nel runtime.');
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
        });

        return back()->with('status', 'Configurazione provider aggiornata.');
    }

    public function toggle(int $provider): RedirectResponse
    {
        $catalogProvider = DB::table('data_providers')->where('id', $provider)->first();
        abort_unless($catalogProvider, 404);

        if (! array_key_exists($catalogProvider->code, config('data_provider_adapters', []))) {
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
        $data = $request->validate([
            'credential_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'credential_value' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('data_provider_credentials')->updateOrInsert(
            [
                'data_provider_id' => $provider,
                'environment' => app()->environment(),
                'credential_key' => Str::lower($data['credential_key']),
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
}
