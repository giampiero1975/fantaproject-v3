<?php

namespace App\Console\Commands;

use App\Support\Providers\ProviderStatusTable;
use Illuminate\Console\Command;

final class ListProviderStatusCommand extends Command
{
    protected $signature = 'providers:status';

    protected $description = 'List registered providers, installed adapters and runtime readiness.';

    public function handle(ProviderStatusTable $statusTable): int
    {
        $rows = $statusTable->rows();

        $this->table(['Code', 'Provider', 'Registered', 'Adapter installed', 'Runtime', 'State'], $rows);

        if ($rows === []) {
            $this->warn('No provider adapters are declared and no providers are registered.');
        }

        return self::SUCCESS;
    }
}
