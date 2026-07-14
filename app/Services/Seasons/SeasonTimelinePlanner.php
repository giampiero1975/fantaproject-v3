<?php

namespace App\Services\Seasons;

final class SeasonTimelinePlanner
{
    /** @return list<array{season_key:int,label:string,is_current:bool}> */
    public function build(int $currentSeason, int $historyFallback): array
    {
        $historyFallback = max(0, $historyFallback);
        $timeline = [];

        for ($offset = 0; $offset <= $historyFallback; $offset++) {
            $seasonKey = $currentSeason - $offset;
            $timeline[] = [
                'season_key' => $seasonKey,
                'label' => sprintf('%d/%02d', $seasonKey, ($seasonKey + 1) % 100),
                'is_current' => $offset === 0,
            ];
        }

        return $timeline;
    }
}
