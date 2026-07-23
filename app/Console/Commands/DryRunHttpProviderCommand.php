<?php

namespace App\Console\Commands;

use App\Services\Providers\HttpProviderPayloadMapper;
use App\Services\Providers\ProviderHttpAuthentication;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DryRunHttpProviderCommand extends Command
{
    protected $signature = 'providers:http-dry-run
        {provider : Provider code or database ID}
        {--endpoint-id= : Exact data_provider_http_endpoints ID to test}
        {--capability= : Capability to test, for example competitions or seasons}
        {--operation= : Operation to test, for example list or by_season}
        {--var=* : Template variable as key=value, repeatable}
        {--show-body : Print raw response preview}';

    protected $description = 'Dry-run a DB-configured HTTP provider endpoint using the same authentication, templates, items path and mapping used by the UI.';

    private bool $logInitialized = false;

    public function handle(
        ProviderHttpAuthentication $authentication,
        HttpProviderPayloadMapper $mapper,
    ): int {
        $this->initializeLog();

        $provider = $this->provider();
        if (! $provider) {
            $this->error('Provider non trovato.');
            $this->dryRunLog('error', 'Provider not found.', [
                'provider_argument' => $this->argument('provider'),
            ]);

            return self::FAILURE;
        }

        $endpoint = $this->endpoint((int) $provider->id);
        if (! $endpoint) {
            return self::FAILURE;
        }

        $variables = $this->variables();
        $endpointPath = $this->render((string) $endpoint->endpoint, $variables);
        $endpointQuery = $this->renderArray($this->jsonArray($endpoint->query_params), $variables);
        $bodyTemplate = $this->renderArray($this->jsonArray($endpoint->body_template), $variables);

        if ($this->hasUnresolvedPlaceholders([$endpointPath, $endpointQuery, $bodyTemplate])) {
            $this->error('La configurazione contiene variabili non risolte. Passale con --var=nome=valore.');
            $this->dryRunLog('warning', 'Dry-run blocked by unresolved template variables.', [
                'provider_id' => $provider->id,
                'provider_code' => $provider->code,
                'endpoint_id' => $endpoint->id,
                'endpoint' => $endpointPath,
                'query' => $endpointQuery,
                'body_template' => $bodyTemplate,
                'variable_keys' => array_keys($variables),
            ]);

            return self::FAILURE;
        }

        $authMode = (string) ($endpoint->auth_mode ?? 'default');
        $authQuery = $authMode === 'none' ? [] : $authentication->queryParameters((int) $provider->id);
        $query = array_merge($authQuery, $endpointQuery);
        $url = $this->buildUrl((string) $provider->base_url, $endpointPath);
        $fieldMappings = $this->jsonArray($endpoint->field_mappings);

        $this->dryRunLog('info', 'HTTP provider dry-run started.', [
            'provider_id' => $provider->id,
            'provider_code' => $provider->code,
            'endpoint_id' => $endpoint->id,
            'capability' => $endpoint->capability,
            'operation' => $endpoint->operation,
            'method' => $endpoint->method,
            'auth_mode' => $authMode,
            'url' => $url,
            'query_keys' => array_keys($query),
            'auth_query_keys' => array_keys($authQuery),
            'items_path' => $endpoint->items_path,
            'mapped_fields' => array_keys($fieldMappings),
            'variable_keys' => array_keys($variables),
        ]);

        try {
            $request = Http::timeout(15)->acceptJson();
            if ($authMode !== 'none') {
                $request = $authentication->applyHeaders($request, (int) $provider->id);
            }
            $response = strtoupper((string) $endpoint->method) === 'POST'
                ? $request->post($url, $bodyTemplate)
                : $request->get($url, $query);

            $payload = $response->json();
            $items = $mapper->extractItems($payload, $endpoint->items_path);
            $firstItem = $items[0] ?? null;
            $normalized = is_array($firstItem) ? $mapper->mapFields($firstItem, $fieldMappings) : null;

            $this->line('');
            $this->info('HTTP provider dry-run');
            $this->table(
                ['Dato', 'Valore'],
                [
                    ['Provider', "{$provider->name} ({$provider->code})"],
                    ['Endpoint', "#{$endpoint->id} {$endpoint->label}"],
                    ['Capability', "{$endpoint->capability} · {$endpoint->operation}"],
                    ['Metodo', $endpoint->method],
                    ['Auth', $authMode],
                    ['URL', $url],
                    ['Query', json_encode($this->maskedQuery($query, array_keys($authQuery)), JSON_UNESCAPED_SLASHES)],
                    ['Status', (string) $response->status()],
                    ['Items path', filled($endpoint->items_path) ? $endpoint->items_path : 'root object'],
                    ['Items trovati', (string) count($items)],
                    ['Campi mappati', (string) count($fieldMappings)],
                ],
            );

            if (! $response->successful()) {
                $this->warn('La chiamata ha risposto con stato '.$response->status().'. Vedi http_adapter_dry_run.log per il body.');
            }

            if ($normalized !== null) {
                $this->line('Preview normalizzata:');
                $this->line(json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }

            if ($this->option('show-body')) {
                $this->line('Body preview:');
                $this->line(Str::limit($response->body(), 2000));
            }

            $this->dryRunLog($response->successful() ? 'info' : 'warning', 'HTTP provider dry-run completed.', [
                'provider_id' => $provider->id,
                'provider_code' => $provider->code,
                'endpoint_id' => $endpoint->id,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'content_type' => $response->header('content-type'),
                'items_count' => count($items),
                'first_item_keys' => is_array($firstItem) ? array_keys($firstItem) : [],
                'normalized_fields' => is_array($normalized) ? array_keys($normalized) : [],
                'body_preview' => Str::limit($response->body(), 1000),
            ]);

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        } catch (ConnectionException | RequestException | Throwable $e) {
            $this->error($e->getMessage());
            $this->dryRunLog('error', 'HTTP provider dry-run failed.', [
                'provider_id' => $provider->id,
                'provider_code' => $provider->code,
                'endpoint_id' => $endpoint->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function provider(): ?object
    {
        $argument = (string) $this->argument('provider');

        return DB::table('data_providers')
            ->where('code', $argument)
            ->orWhere('id', ctype_digit($argument) ? (int) $argument : 0)
            ->first();
    }

    private function endpoint(int $providerId): ?object
    {
        $query = DB::table('data_provider_http_endpoints as e')
            ->leftJoin('data_provider_payload_mappings as m', 'm.data_provider_http_endpoint_id', '=', 'e.id')
            ->where('e.data_provider_id', $providerId)
            ->select([
                'e.id',
                'e.label',
                'e.capability',
                'e.operation',
                'e.method',
                'e.auth_mode',
                'e.endpoint',
                'e.query_params',
                'e.body_template',
                'e.items_path',
                'e.validation_status',
                'm.field_mappings',
            ]);

        if (filled($this->option('endpoint-id'))) {
            $query->where('e.id', (int) $this->option('endpoint-id'));
        }

        if (filled($this->option('capability'))) {
            $query->where('e.capability', (string) $this->option('capability'));
        }

        if (filled($this->option('operation'))) {
            $query->where('e.operation', (string) $this->option('operation'));
        }

        $endpoints = $query->orderBy('e.capability')->orderBy('e.operation')->get();

        if ($endpoints->count() === 1) {
            return $endpoints->first();
        }

        if ($endpoints->isEmpty()) {
            $this->error('Nessuna configurazione HTTP trovata per i criteri indicati.');
            $this->dryRunLog('warning', 'No HTTP endpoint found for dry-run criteria.', [
                'provider_id' => $providerId,
                'endpoint_id' => $this->option('endpoint-id'),
                'capability' => $this->option('capability'),
                'operation' => $this->option('operation'),
            ]);

            return null;
        }

        $this->warn('Trovate piu configurazioni HTTP. Specifica --endpoint-id oppure capability + operation.');
        $this->table(
            ['ID', 'Label', 'Capability', 'Operation', 'Metodo', 'Endpoint'],
            $endpoints
                ->map(fn (object $endpoint): array => [
                    $endpoint->id,
                    $endpoint->label,
                    $endpoint->capability,
                    $endpoint->operation,
                    $endpoint->method,
                    $endpoint->endpoint,
                ])
                ->all(),
        );

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function variables(): array
    {
        return collect($this->option('var'))
            ->mapWithKeys(function (string $line): array {
                [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
                $key = trim($key);

                return $key !== '' ? [$key => trim($value)] : [];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function renderArray(array $values, array $variables): array
    {
        return collect($values)
            ->map(function (mixed $value) use ($variables): mixed {
                if (is_array($value)) {
                    return $this->renderArray($value, $variables);
                }

                return is_string($value) ? $this->render($value, $variables) : $value;
            })
            ->all();
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function render(string $value, array $variables): string
    {
        foreach ($variables as $key => $replacement) {
            $value = str_replace('{'.$key.'}', $replacement, $value);
        }

        return $value;
    }

    private function hasUnresolvedPlaceholders(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $item): bool => $this->hasUnresolvedPlaceholders($item));
        }

        return is_string($value) && preg_match('/\{[A-Za-z0-9_]+\}/', $value) === 1;
    }

    private function buildUrl(string $baseUrl, string $endpoint): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    private function maskedQuery(array $query, array $sensitiveKeys): array
    {
        return collect($query)
            ->mapWithKeys(fn (mixed $value, string $key): array => [
                $key => in_array($key, $sensitiveKeys, true) ? '***' : $value,
            ])
            ->all();
    }

    private function initializeLog(): void
    {
        if ($this->logInitialized) {
            return;
        }

        $path = storage_path('logs/administration/provider_managment/http_adapter_dry_run.log');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');
        $this->logInitialized = true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dryRunLog(string $level, string $message, array $context = []): void
    {
        $path = storage_path('logs/administration/provider_managment/http_adapter_dry_run.log');
        $level = preg_replace('/[^a-z]+/', '', strtolower($level)) ?: 'info';

        Log::build([
            'driver' => 'single',
            'path' => $path,
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ])->log($level, "[http_adapter_dry_run][{$level}] {$message}", array_merge([
            'menu' => 'Administration',
            'section' => 'Provider Management',
            'functionality' => 'http_adapter_dry_run',
            'level' => $level,
        ], $context));
    }
}
