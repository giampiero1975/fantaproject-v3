<?php

namespace App\Console\Commands;

use App\Services\ApiFootball\ApiFootballClient;
use App\Services\ApiFootball\LeagueRegistryMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ImportApiFootballLeagueMappings extends Command
{
    protected $signature = 'registry:import-api-football
        {--apply : Persist only uniquely matched league mappings}
        {--overwrite : Update an existing API-Football mapping for the same internal league}
        {--report-dir=registry-audit : Directory inside the configured local storage disk}';

    protected $description = 'Match API-Football leagues to the internal Fanta Oracle league registry.';

    public function handle(ApiFootballClient $client, LeagueRegistryMatcher $matcher): int
    {
        $apply = (bool) $this->option('apply');
        $this->components->info($apply ? 'APPLY mode: safe matches will be persisted.' : 'DRY-RUN mode: the database will not be modified.');

        try {
            $provider = DB::table('data_providers')->where('code', 'api_football')->first();
            if (! $provider) {
                throw new RuntimeException('Provider api_football not found. Run DataProvidersSeeder first.');
            }

            $items = $client->leagues();
            $results = ['matched' => [], 'unmatched' => [], 'ambiguous' => [], 'ignored' => []];

            foreach ($items as $item) {
                $league = is_array($item['league'] ?? null) ? $item['league'] : [];
                $country = is_array($item['country'] ?? null) ? $item['country'] : [];

                $externalId = isset($league['id']) ? (string) $league['id'] : '';
                $externalName = trim((string) ($league['name'] ?? ''));
                $type = trim((string) ($league['type'] ?? ''));
                $countryName = trim((string) ($country['name'] ?? ''));
                $countryCode = trim((string) ($country['code'] ?? ''));

                if ($externalId === '' || $externalName === '') {
                    $results['ignored'][] = $this->row($externalId, $externalName, $type, $countryName, $countryCode, 'invalid_payload');
                    continue;
                }

                if (strcasecmp($type, 'League') !== 0) {
                    $results['ignored'][] = $this->row($externalId, $externalName, $type, $countryName, $countryCode, 'not_a_league');
                    continue;
                }

                if ($this->shouldIgnoreLeagueName($externalName)) {
                    $results['ignored'][] = $this->row(
                        $externalId,
                        $externalName,
                        $type,
                        $countryName,
                        $countryCode,
                        'excluded_league_variant'
                    );
                    continue;
                }

                $match = $matcher->match($externalName, $countryName ?: null, $countryCode ?: null);
                $row = $this->row($externalId, $externalName, $type, $countryName, $countryCode, $match['reason']);
                $row['candidates'] = json_encode($match['candidates'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($match['status'] === 'matched') {
                    $candidate = $match['candidates'][0];
                    $row['internal_league_id'] = $candidate['league_id'];
                    $row['internal_league_name'] = $candidate['league_name'];
                    $row['internal_country'] = $candidate['country_name'];

                    if ($apply) {
                        $this->persistMapping((int) $provider->id, $candidate, $row, $item);
                    }
                }

                $results[$match['status']][] = $row;
            }

            $paths = $this->writeReports($results);
            $this->newLine();
            $this->table(['Status', 'Count'], [
                ['Matched', count($results['matched'])],
                ['Ambiguous', count($results['ambiguous'])],
                ['Unmatched', count($results['unmatched'])],
                ['Ignored', count($results['ignored'])],
            ]);

            foreach ($paths as $status => $path) {
                $this->line(sprintf('%s report: %s', ucfirst($status), $path));
            }

            if ($apply) {
                $this->components->info('Safe mappings persisted. Ambiguous and unmatched rows were not written.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function shouldIgnoreLeagueName(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));

        $patterns = [
            '/\bcup\b/u',
            '/\bplay[ -]?offs?\b/u',
            '/\bapertura\b/u',
            '/\bclausura\b/u',
            '/\bwomen\b/u',
            '/\bwomen\'s\b/u',
            '/\bfemenil\b/u',
            '/\bfeminina\b/u',
            '/\bfeminino\b/u',
            '/\bfrauen\b/u',
            '/\bdamallsvenskan\b/u',
            '/\bu(?:18|19|20|21|23)\b/u',
            '/\breserve\b/u',
            '/\bsummer series\b/u',
            '/\bgroup\s+\d+\b/u',
            '/\b(?:north|south)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $candidate @param array<string,mixed> $row @param array<string,mixed> $raw */
    private function persistMapping(int $providerId, array $candidate, array $row, array $raw): void
    {
        $values = [
            'league_id' => $candidate['league_id'],
            'data_provider_id' => $providerId,
            'external_id' => $row['external_id'],
            'external_name' => $row['external_name'],
            'external_country' => $row['external_country'] ?: null,
            'metadata' => json_encode(['source' => 'api_football', 'raw' => $raw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'verified_at' => now(),
            'updated_at' => now(),
        ];

        $existingExternal = DB::table('league_provider_mappings')
            ->where('data_provider_id', $providerId)
            ->where('external_id', $row['external_id'])
            ->first();

        if ($existingExternal && (int) $existingExternal->league_id !== (int) $candidate['league_id']) {
            throw new RuntimeException("External league {$row['external_id']} is already mapped to another internal league.");
        }

        $existingInternal = DB::table('league_provider_mappings')
            ->where('data_provider_id', $providerId)
            ->where('league_id', $candidate['league_id'])
            ->first();

        if ($existingInternal && ! $this->option('overwrite')) {
            return;
        }

        if ($existingInternal) {
            DB::table('league_provider_mappings')->where('id', $existingInternal->id)->update($values);
            return;
        }

        DB::table('league_provider_mappings')->insert($values + ['created_at' => now()]);
    }

    /** @return array<string,string> */
    private function writeReports(array $results): array
    {
        $dir = trim((string) $this->option('report-dir'), '/');
        $stamp = now()->format('Ymd_His');
        $paths = [];

        foreach ($results as $status => $rows) {
            $relative = "{$dir}/api_football_{$status}_{$stamp}.csv";
            $stream = fopen('php://temp', 'w+');
            fputcsv($stream, [
                'external_id', 'external_name', 'external_type', 'external_country', 'external_country_code',
                'internal_league_id', 'internal_league_name', 'internal_country', 'reason', 'candidates',
            ]);

            foreach ($rows as $row) {
                fputcsv($stream, [
                    $row['external_id'] ?? '', $row['external_name'] ?? '', $row['external_type'] ?? '',
                    $row['external_country'] ?? '', $row['external_country_code'] ?? '',
                    $row['internal_league_id'] ?? '', $row['internal_league_name'] ?? '',
                    $row['internal_country'] ?? '', $row['reason'] ?? '', $row['candidates'] ?? '',
                ]);
            }

            rewind($stream);
            Storage::disk('local')->put($relative, stream_get_contents($stream));
            fclose($stream);
            $paths[$status] = storage_path('app/'.$relative);
        }

        return $paths;
    }

    /** @return array<string,mixed> */
    private function row(string $id, string $name, string $type, string $country, string $countryCode, string $reason): array
    {
        return [
            'external_id' => $id,
            'external_name' => $name,
            'external_type' => $type,
            'external_country' => $country,
            'external_country_code' => $countryCode,
            'internal_league_id' => '',
            'internal_league_name' => '',
            'internal_country' => '',
            'reason' => $reason,
            'candidates' => '',
        ];
    }
}
