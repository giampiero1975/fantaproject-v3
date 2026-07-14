<?php

namespace App\Services\Seasons;

use App\Data\Seasons\CanonicalTeamData;
use App\Services\Matching\NameSimilarityMatcher;

final class TeamCongruityValidator
{
    public function __construct(
        private readonly NameSimilarityMatcher $nameMatcher = new NameSimilarityMatcher(),
    ) {}

    /**
     * @param list<CanonicalTeamData> $left
     * @param list<CanonicalTeamData> $right
     * @return array{status:string,left_count:int,right_count:int,matched:list<array<string,mixed>>,missing_left:list<array<string,mixed>>,missing_right:list<array<string,mixed>>,warnings:list<string>}
     */
    public function compare(array $left, array $right): array
    {
        $matched = [];
        $warnings = [];
        $usedRight = [];
        $missingRight = [];

        foreach ($left as $leftTeam) {
            $rightIndex = $this->findMatchIndex($leftTeam, $right, $usedRight);

            if ($rightIndex === null) {
                $missingRight[] = $leftTeam->toArray();
                continue;
            }

            $usedRight[$rightIndex] = true;
            $rightTeam = $right[$rightIndex];

            $matched[] = [
                'comparison_key' => $this->comparisonLabel($leftTeam, $rightTeam),
                'left' => $leftTeam->toArray(),
                'right' => $rightTeam->toArray(),
            ];
        }

        $missingLeft = [];
        foreach ($right as $index => $rightTeam) {
            if (! isset($usedRight[$index])) {
                $missingLeft[] = $rightTeam->toArray();
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

    /** @param list<CanonicalTeamData> $right @param array<int,bool> $usedRight */
    private function findMatchIndex(CanonicalTeamData $leftTeam, array $right, array $usedRight): ?int
    {
        if ($leftTeam->code) {
            foreach ($right as $index => $rightTeam) {
                if (isset($usedRight[$index]) || ! $rightTeam->code) {
                    continue;
                }

                if (strcasecmp(trim($leftTeam->code), trim($rightTeam->code)) === 0) {
                    return $index;
                }
            }
        }

        $leftOperationalName = $leftTeam->shortName ?: $leftTeam->name;

        foreach ($right as $index => $rightTeam) {
            if (isset($usedRight[$index])) {
                continue;
            }

            $rightOperationalName = $rightTeam->shortName ?: $rightTeam->name;

            if ($this->nameMatcher->matches($leftOperationalName, $rightOperationalName)) {
                return $index;
            }
        }

        foreach ($right as $index => $rightTeam) {
            if (isset($usedRight[$index])) {
                continue;
            }

            if ($this->nameMatcher->matches($leftTeam->name, $rightTeam->name)) {
                return $index;
            }
        }

        return null;
    }

    private function comparisonLabel(CanonicalTeamData $left, CanonicalTeamData $right): string
    {
        if ($left->code && $right->code && strcasecmp($left->code, $right->code) === 0) {
            return 'code:'.mb_strtoupper(trim($left->code));
        }

        return 'name:'.$this->nameMatcher->normalize($left->shortName ?: $left->name);
    }
}
