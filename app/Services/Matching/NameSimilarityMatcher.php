<?php

namespace App\Services\Matching;

use Illuminate\Support\Str;

final class NameSimilarityMatcher
{
    public function matches(string $left, string $right): bool
    {
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);

        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        if ($leftTokens === $rightTokens) {
            return true;
        }

        $shortSet = count($leftTokens) <= count($rightTokens) ? $leftTokens : $rightTokens;
        $longSet = $shortSet === $leftTokens ? $rightTokens : $leftTokens;
        $remaining = $longSet;

        foreach ($shortSet as $token) {
            $foundIndex = $this->findToken($token, $remaining, count($leftTokens), count($rightTokens));

            if ($foundIndex === null) {
                return false;
            }

            unset($remaining[$foundIndex]);
        }

        return true;
    }

    public function normalize(string $value): string
    {
        return implode(' ', $this->tokens($value));
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $value = mb_strtolower(Str::ascii($value));
        $value = str_replace(["'", '-'], ' ', $value);
        $value = preg_replace('/[^a-z0-9. ]/', '', $value) ?? $value;

        return array_values(array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($value)) ?? $value)));
    }

    /** @param array<int,string> $candidates */
    private function findToken(string $token, array $candidates, int $leftCount, int $rightCount): ?int
    {
        foreach ($candidates as $index => $candidate) {
            if ($token === $candidate) {
                return $index;
            }

            $initialMatch = (str_ends_with($token, '.') && str_starts_with($candidate, rtrim($token, '.')))
                || (str_ends_with($candidate, '.') && str_starts_with($token, rtrim($candidate, '.')));

            if ($initialMatch && $leftCount > 1 && $rightCount > 1) {
                return $index;
            }
        }

        $bestIndex = null;
        $bestScore = 0.0;

        foreach ($candidates as $index => $candidate) {
            similar_text($token, $candidate, $score);
            $threshold = strlen($token) < 4 ? 95.0 : 85.0;

            if ($score >= $threshold && $score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }
}
