<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ListProviderAdaptersCommand extends Command
{
    protected $signature = 'providers:adapters';

    protected $description = 'List installed provider adapters and their runtime registration state';

    public function handle(): int
    {
        $catalog = config('data_provider_adapters', []);
        $registered = DB::table('data_providers')
            ->pluck('active', 'code');

        $rows = collect($catalog)
            ->map(function (array $adapter, string $code) use ($registered): array {
                $isRegistered = $registered->has($code);

                return [
                    $code,
                    $adapter['name'] ?? $code,
                    $adapter['credential_key'] ?? '—',
                    implode(', ', $adapter['capabilities'] ?? []),
                    $isRegistered ? 'YES' : 'NO',
                    $isRegistered ? ((bool) $registered[$code] ? 'ACTIVE' : 'DISABLED') : 'AVAILABLE',
                ];
            })
            ->values()
            ->all();

        $this->table(
            ['Code', 'Provider', 'Credential', 'Capabilities', 'Registered', 'State'],
            $rows
        );

        if ($rows === []) {
            $this->warn('No provider adapters are declared in config/data_provider_adapters.php.');
        }

        return self::SUCCESS;
    }
}
