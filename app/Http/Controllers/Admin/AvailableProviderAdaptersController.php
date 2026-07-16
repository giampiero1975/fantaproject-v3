<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AvailableProviderAdaptersController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $registeredCodes = DB::table('data_providers')->pluck('code')->all();

        $adapters = collect(config('data_provider_adapters', []))
            ->reject(fn (array $adapter, string $code): bool => in_array($code, $registeredCodes, true))
            ->map(fn (array $adapter, string $code): array => [
                'code' => $code,
                'name' => $adapter['name'] ?? $code,
                'credential_key' => $adapter['credential_key'] ?? null,
                'capabilities' => array_values($adapter['capabilities'] ?? []),
            ])
            ->values();

        return response()->json(['data' => $adapters]);
    }
}
