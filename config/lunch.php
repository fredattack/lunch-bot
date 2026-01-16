<?php

return [
    'channel_id' => env('LUNCH_CHANNEL_ID'),
    'post_time' => env('LUNCH_POST_TIME', '11:00'),
    'deadline_time' => env('LUNCH_DEADLINE_TIME', '11:30'),
    'timezone' => env('LUNCH_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
];
