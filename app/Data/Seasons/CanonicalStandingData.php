<?php

namespace App\Data\Seasons;

final readonly class CanonicalStandingData
{
    public function __construct(
        public string $provider,
        public string $providerTeamId,
        public string $teamName,
        public ?string $teamCode,
        public ?int $position,
        public ?int $playedGames,
        public ?int $won,
        public ?int $draw,
        public ?int $lost,
        public ?int $points,
        public ?int $goalsFor,
        public ?int $goalsAgainst,
        public ?int $goalDifference,
        public ?string $stageName,
        public ?string $groupName,
        public array $metadata = [],
    ) {}

    public function comparisonKey(): string
    {
        $value = mb_strtolower(trim($this->teamName));
        $value = preg_replace('/\b(fc|calcio|club|ac|as|ss|us)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_team_id' => $this->providerTeamId,
            'team_name' => $this->teamName,
            'team_code' => $this->teamCode,
            'position' => $this->position,
            'played_games' => $this->playedGames,
            'won' => $this->won,
            'draw' => $this->draw,
            'lost' => $this->lost,
            'points' => $this->points,
            'goals_for' => $this->goalsFor,
            'goals_against' => $this->goalsAgainst,
            'goal_difference' => $this->goalDifference,
            'stage_name' => $this->stageName,
            'group_name' => $this->groupName,
            'metadata' => $this->metadata,
        ];
    }
}