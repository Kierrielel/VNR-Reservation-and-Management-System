<?php

return [
    'hold_minutes' => max(1, (int) env('RESERVATION_HOLD_MINUTES', 30)),
];
