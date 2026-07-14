<?php

return [
    'history_fallback' => max(0, (int) env('SEASON_HISTORY_FALLBACK', 4)),
];
