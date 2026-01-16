# Slack Lunch Bot MVP

Laravel-based Slack bot to manage daily lunch orders in a single channel and thread.

## Requirements

- PHP 8.3+
- Composer
- SQLite or Postgres
- Slack App with Events API + Interactivity enabled

## Setup

1) Install dependencies
```
composer install
```

2) Configure environment
```
cp .env.example .env
php artisan key:generate
```

3) Set Slack and lunch settings in `.env`
- `SLACK_BOT_TOKEN`
- `SLACK_SIGNING_SECRET`
- `SLACK_ADMIN_USER_IDS` (comma-separated, optional)
- `LUNCH_CHANNEL_ID`
- `LUNCH_POST_TIME`
- `LUNCH_DEADLINE_TIME`
- `LUNCH_TIMEZONE`

4) Run migrations
```
php artisan migrate
```

5) Run scheduler
```
php artisan schedule:work
```

## Slack configuration

Enable the following:
- Events API: request URL `https://<your-host>/api/slack/events`
- Interactivity: request URL `https://<your-host>/api/slack/interactivity`

Suggested OAuth scopes:
- `chat:write`
- `chat:write.public`
- `commands`
- `users:read`
- `channels:read`
- `groups:read`

## Daily flow

- Scheduler posts the daily message at `LUNCH_POST_TIME`.
- Users propose an enseigne, claim runner/orderer, and submit orders via modals.
- Summary and closure are posted in the daily thread.

## Key files

- `app/Services/SlackInteractionService.php`
- `app/Services/SlackBlockBuilder.php`
- `app/Services/LunchManager.php`
- `routes/api.php`
- `app/Http/Middleware/VerifySlackSignature.php`

## Testing

Run the default suite:
```
php artisan test
```
