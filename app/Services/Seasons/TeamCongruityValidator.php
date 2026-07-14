<?php

namespace App\Services\Seasons;

use App\Data\Seasons\CanonicalTeamData;

final class TeamCongruityValidator
{
    /**
     * @param list<CanonicalTeamData> $left
     * @param list<CanonicalTeamData> $right
     * @return array{status:string,left_count:int,right_count:int,matched:list<array<string,mixed>>,missing_left:list<array<string,mixed>>,missing_right:list<array<string,mixed>>,warnings:list<string>}
     */
    public function compare(array $left, array $right): array
    {
        $leftByKey = $this->index($left);
        $rightByKey = $this->index($right);

        $matched = [];
        $missingLeft = [];
        $missingRight = [];
        $warnings = [];

        foreach ($leftByKey as $key => $team) {
            if (! isset($rightByKey[$key])) {
                $missingRight[] = $team->toArray();
                continue;
            }

            $other = $rightByKey[$key];
            $matched[] = [
                'comparison_key' => $key,
                'left' => $team->toArray(),
                'right' => $other->toArray(),
            ];

            if ($team->comparisonKey() !== $other->comparisonKey()) {
                $warnings[] = sprintf('Name mismatch for code %s: %s vs %s.', $team->code ?: $other->code ?: $key, $team->name, $other->name);
            }
        }

        foreach ($rightByKey as $key => $team) {
            if (! isset($leftByKey[$key])) {
                $missingLeft[] = $team->toArray();
            }
        }

        $status = $missingLeft === [] && $missingRight === []
            ? ($warnings === [] ? 'pass' : 'warning')
            : 'fail';

        return [
            'status' => $status,
            'left_count' => count($left),
            'right_count' => count($right),
            'matched' => $matched,
            'missing_left' => $missingLeft,
            'missing_right' => $missingRight,
            'warnings' => $warnings,
        ];
    }

    /** @param list<CanonicalTeamData> $teams @return array<string,CanonicalTeamData> */
    private function index(array $teams): array
    {
        $indexed = [];

        foreach ($teams as $team) {
            $key = $team->code
                ? 'code:'.mb_strtoupper(trim($team->code))
                : 'name:'.$team->comparisonKey();

            if ($key === 'name:') {
                continue;
            }

            $indexed[$key] = $team;
        }

        return $indexed;
    }
}
