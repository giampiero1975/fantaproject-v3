<?php

namespace App\Console\Commands;

use App\Support\Providers\ProviderStatusTable;
use Illuminate\Console\Command;

final class ListProviderAdaptersCommand extends Command
{
    protected $signature = 'providers:adapters';

    protected $description = 'Alias of providers:status. Lists provider DB registration and adapter availability.';

    public function handle(ProviderStatusTable $statusTable): int
    {
        $rows = $statusTable->rows();

        $this->table(['Code', 'Provider', 'Registered', 'Configured', 'Runtime', 'State'], $rows);

        if ($rows === []) {
            $this->warn('No provider adapters are declared and no providers are registered.');
        }

        return self::SUCCESS;
    }
}
