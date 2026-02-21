<?php

return [
    'bot_token' => env('SLACK_BOT_TOKEN'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),
    'admin_user_ids' => array_values(array_filter(array_map('trim', explode(',', env('SLACK_ADMIN_USER_IDS', ''))))),
    'dev_user_id' => env('SLACK_DEV_USER_ID'),
    'team_id' => env('SLACK_TEAM_ID'),
];
