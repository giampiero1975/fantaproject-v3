<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class SeasonManagementController extends Controller
{
    /** @var list<string> */
    private const REQUIRED_CAPABILITIES = ['competitions', 'seasons', 'teams'];

    public function index(): View
    {
        $rows = DB::table('leagues as l')
            ->join('league_provider_mappings as lpm', 'lpm.league_id', '=', 'l.id')
            ->join('data_providers as p', 'p.id', '=', 'lpm.data_provider_id')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select(
                'l.id',
                'l.name',
                'l.country_id',
                'c.name as country_name',
                'p.id as provider_id',
                'p.code as provider_code',
                'p.name as provider_name',
                'lpm.external_id',
                'lpm.external_name',
                'rc.is_enabled',
                'rc.role',
                'rc.priority',
                'rc.plan'
            )
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->get();

        $capabilities = $this->providerCapabilities(
            $rows->pluck('provider_id')->map(fn ($id): int => (int) $id)->unique()->values()->all()
        );

        $leagues = $rows
            ->map(function (object $row) use ($capabilities): object {
                $row->capabilities = $capabilities[(int) $row->provider_id] ?? $this->emptyCapabilities();
                $row->required_capabilities_ready = collect($row->capabilities)
                    ->where('ready', true)
                    ->count();

                return $row;
            })
            ->groupBy('id')
            ->map(function ($rows) {
                $first = $rows->first();
                $providers = $rows->values();

                return (object) [
                    'id' => $first->id,
                    'name' => $first->name,
                    'country_id' => $first->country_id,
                    'country_name' => $first->country_name,
                    'providers' => $providers,
                    'ready_providers' => $providers
                        ->filter(fn (object $provider): bool => (bool) $provider->is_enabled && $provider->required_capabilities_ready > 0)
                        ->count(),
                ];
            })
            ->values();

        $countries = $leagues
            ->filter(fn ($league) => $league->country_id !== null)
            ->unique('country_id')
            ->sortBy('country_name')
            ->values();

        return view('admin.seasons.index', [
            'leagues' => $leagues,
            'internalLeagues' => $this->internalLeagues(),
            'mappableProviders' => $this->mappableProviders(),
            'countries' => $countries,
            'requiredCapabilities' => self::REQUIRED_CAPABILITIES,
            'historyFallback' => (int) config('seasons.history_fallback', 4),
            'lastReport' => session('season_sync_report'),
            'lastReportData' => session('season_sync_report_data'),
            'lastMode' => session('season_sync_mode'),
            'lastExitCode' => session('season_sync_exit_code'),
            'lastParameters' => session('season_sync_parameters', []),
        ]);
    }

    private function internalLeagues()
    {
        return DB::table('leagues as l')
            ->leftJoin('countries as c', 'c.id', '=', 'l.country_id')
            ->select('l.id', 'l.name', 'l.country_id', 'c.name as country_name')
            ->orderBy('c.name')
            ->orderBy('l.name')
            ->get();
    }

    private function mappableProviders()
    {
        $providers = DB::table('data_providers as p')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('data_provider_http_endpoints as e')
                    ->whereColumn('e.data_provider_id', 'p.id')
                    ->where('e.capability', 'competitions')
                    ->where('e.is_enabled', true);
            })
            ->select('p.id', 'p.code', 'p.name', 'rc.is_enabled', 'rc.role', 'rc.priority')
            ->orderByRaw('COALESCE(rc.priority, 9999)')
            ->orderBy('p.name')
            ->get();

        $readyProviderIds = DB::table('data_provider_http_endpoints as e')
            ->join('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->where('e.capability', 'competitions')
            ->where('e.is_enabled', true)
            ->where('m.validation_status', 'mapping_validated')
            ->pluck('e.data_provider_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        return $providers->map(function (object $provider) use ($readyProviderIds): object {
            $provider->competitions_ready = in_array((int) $provider->id, $readyProviderIds, true);

            return $provider;
        });
    }

    /**
     * @param  list<int>  $providerIds
     * @return array<int, array<string, array{configured:bool,enabled:bool,mapping_validated:bool,ready:bool,operations:list<string>}>>
     */
    private function providerCapabilities(array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }

        $endpoints = DB::table('data_provider_http_endpoints as e')
            ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->whereIn('e.data_provider_id', $providerIds)
            ->whereIn('e.capability', self::REQUIRED_CAPABILITIES)
            ->orderBy('e.capability')
            ->orderBy('e.operation')
            ->get([
                'e.data_provider_id',
                'e.capability',
                'e.operation',
                'e.is_enabled',
                'e.validation_status',
                'm.validation_status as mapping_validation_status',
            ])
            ->groupBy('data_provider_id');

        $result = [];

        foreach ($providerIds as $providerId) {
            $capabilityRows = $endpoints->get($providerId, collect())->groupBy('capability');
            $result[$providerId] = collect(self::REQUIRED_CAPABILITIES)
                ->mapWithKeys(function (string $capability) use ($capabilityRows): array {
                    $rows = $capabilityRows->get($capability, collect());
                    $enabled = $rows->contains(fn (object $row): bool => (bool) $row->is_enabled);
                    $mappingValidated = $rows->contains(
                        fn (object $row): bool => ($row->mapping_validation_status ?? $row->validation_status) === 'mapping_validated'
                    );

                    return [
                        $capability => [
                            'configured' => $rows->isNotEmpty(),
                            'enabled' => $enabled,
                            'mapping_validated' => $mappingValidated,
                            'ready' => $enabled && $mappingValidated,
                            'operations' => $rows->pluck('operation')->map(fn ($operation): string => (string) $operation)->values()->all(),
                        ],
                    ];
                })
                ->all();
        }

        return $result;
    }

    /**
     * @return array<string, array{configured:bool,enabled:bool,mapping_validated:bool,ready:bool,operations:list<string>}>
     */
    private function emptyCapabilities(): array
    {
        return collect(self::REQUIRED_CAPABILITIES)
            ->mapWithKeys(fn (string $capability): array => [
                $capability => [
                    'configured' => false,
                    'enabled' => false,
                    'mapping_validated' => false,
                    'ready' => false,
                    'operations' => [],
                ],
            ])
            ->all();
    }

    public function analyze(Request $request): RedirectResponse
    {
        return $this->runSync($this->validateRequest($request), false);
    }

    public function apply(Request $request): RedirectResponse
    {
        return $this->runSync($this->validateRequest($request, true), true);
    }

    public function storeProviderMapping(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'data_provider_id' => ['required', 'integer', 'exists:data_providers,id'],
            'external_id' => ['required', 'string', 'max:100'],
            'external_name' => ['required', 'string', 'max:180'],
            'external_country' => ['nullable', 'string', 'max:120'],
        ]);

        $externalId = trim((string) $validated['external_id']);
        $externalName = trim((string) $validated['external_name']);
        $externalCountry = trim((string) ($validated['external_country'] ?? ''));

        if ($externalId === '' || $externalName === '') {
            throw ValidationException::withMessages([
                'external_id' => 'Codice provider e nome esterno sono obbligatori.',
            ]);
        }

        $conflictingMapping = DB::table('league_provider_mappings')
            ->where('data_provider_id', (int) $validated['data_provider_id'])
            ->where('external_id', $externalId)
            ->where('league_id', '!=', (int) $validated['league_id'])
            ->first();

        if ($conflictingMapping !== null) {
            throw ValidationException::withMessages([
                'external_id' => 'Questo codice provider è già collegato a un’altra competizione interna.',
            ]);
        }

        $existingMapping = DB::table('league_provider_mappings')
            ->where('league_id', (int) $validated['league_id'])
            ->where('data_provider_id', (int) $validated['data_provider_id'])
            ->first();

        $payload = [
            'external_id' => $externalId,
            'external_name' => $externalName,
            'external_country' => $externalCountry !== '' ? $externalCountry : null,
            'metadata' => json_encode(['source' => 'season_management_ui']),
            'verified_at' => now(),
            'updated_at' => now(),
        ];

        if ($existingMapping === null) {
            DB::table('league_provider_mappings')->insert($payload + [
                'league_id' => (int) $validated['league_id'],
                'data_provider_id' => (int) $validated['data_provider_id'],
                'created_at' => now(),
            ]);
        } else {
            DB::table('league_provider_mappings')
                ->where('id', $existingMapping->id)
                ->update($payload);
        }

        return redirect()
            ->route('admin.seasons.index')
            ->with('status', $existingMapping === null
                ? 'Mapping provider collegato alla competizione interna.'
                : 'Mapping provider aggiornato per la competizione interna.');
    }

    /** @return array{league_id:int,history:?int,confirmation?:string} */
    private function validateRequest(Request $request, bool $apply = false): array
    {
        $rules = [
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
            'history' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];

        if ($apply) {
            $rules['confirmation'] = ['required', 'in:APPLICA'];
        }

        return $request->validate($rules, [
            'confirmation.in' => 'Per applicare la sincronizzazione devi digitare APPLICA.',
        ]);
    }

    /** @param array{league_id:int,history:?int,confirmation?:string} $validated */
    private function runSync(array $validated, bool $apply): RedirectResponse
    {
        $mappings = DB::table('league_provider_mappings as lpm')
            ->join('data_providers as p', 'p.id', '=', 'lpm.data_provider_id')
            ->leftJoin('data_provider_runtime_configs as rc', 'rc.data_provider_id', '=', 'p.id')
            ->where('lpm.league_id', $validated['league_id'])
            ->where('rc.is_enabled', true)
            ->get(['p.code', 'p.name', 'lpm.external_id', 'lpm.external_name', 'rc.is_enabled', 'rc.priority', 'rc.role', 'rc.plan']);

        if ($mappings->isEmpty()) {
            throw ValidationException::withMessages([
                'league_id' => 'La competizione selezionata deve avere almeno un mapping provider attivo prima della sincronizzazione.',
            ]);
        }

        $providerReferences = $mappings
            ->mapWithKeys(fn (object $mapping): array => [(string) $mapping->code => (string) $mapping->external_id])
            ->all();

        $parameters = [
            'league_id' => (int) $validated['league_id'],
            'provider_references' => $providerReferences,
            'history' => $validated['history'] !== null ? (int) $validated['history'] : null,
            'providers' => $mappings->map(fn ($row) => (array) $row)->all(),
        ];

        $arguments = [
            '--league-id' => $parameters['league_id'],
            '--provider-ref' => collect($providerReferences)
                ->map(fn (string $externalId, string $provider): string => "{$provider}={$externalId}")
                ->values()
                ->all(),
            '--json' => true,
        ];

        if ($parameters['history'] !== null) {
            $arguments['--history'] = $parameters['history'];
        }

        if ($apply) {
            $arguments['--apply'] = true;
        }

        $exitCode = Artisan::call('season:sync', $arguments);
        $output = trim(Artisan::output());
        $reportData = $this->extractJsonReport($output);

        return redirect()
            ->route('admin.seasons.index')
            ->with('season_sync_report', $output)
            ->with('season_sync_report_data', $reportData)
            ->with('season_sync_mode', $apply ? 'apply' : 'dry_run')
            ->with('season_sync_exit_code', $exitCode)
            ->with('season_sync_parameters', $parameters)
            ->with(
                $exitCode === 0 ? 'status' : 'error',
                $exitCode === 0
                    ? ($apply ? 'Sincronizzazione applicata correttamente.' : 'Analisi completata senza scritture sul database.')
                    : 'La sincronizzazione non è stata completata. Controlla il report.'
            );
    }

    /** @return array<string,mixed>|null */
    private function extractJsonReport(string $output): ?array
    {
        $position = strpos($output, '{');
        if ($position === false) {
            return null;
        }

        $decoded = json_decode(substr($output, $position), true);

        return is_array($decoded) ? $decoded : null;
    }
}
