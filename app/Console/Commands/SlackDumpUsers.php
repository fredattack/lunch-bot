<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SlackDumpUsers extends Command
{
    /**
     * Example:
     * php artisan slack:dump-users --disk=local --path=slack/users.json --pretty --exclude-bots --exclude-deleted
     */
    protected $signature = 'slack:dump-users
        {--disk=local : Storage disk to write to (e.g., local, s3)}
        {--path=slack/users.json : Output path inside the storage disk}
        {--pretty : Pretty-print JSON}
        {--exclude-bots : Exclude bot users}
        {--exclude-deleted : Exclude deleted users}
        {--limit=200 : Page size (Slack max is typically 200 for users.list)}
        {--token= : Slack token override (otherwise uses SLACK_BOT_TOKEN env var)}';

    protected $description = 'Dump Slack workspace users to a JSON file (one-shot).';

    public function handle(): int
    {
        $token = $this->option('token') ?: env('SLACK_BOT_TOKEN');

        if (!$token) {
            $this->error('Missing Slack token. Set SLACK_BOT_TOKEN in .env or pass --token=...');
            return self::FAILURE;
        }

        $disk = (string) $this->option('disk');
        $path = (string) $this->option('path');
        $limit = (int) $this->option('limit');

        $excludeBots = (bool) $this->option('exclude-bots');
        $excludeDeleted = (bool) $this->option('exclude-deleted');

        $cursor = null;
        $users = [];

        $this->info('Fetching Slack users...');

        do {
            $query = ['limit' => $limit];
            if ($cursor) {
                $query['cursor'] = $cursor;
            }

            $response = Http::withToken($token)
                ->timeout(30)
                ->retry(3, 500)
                ->get('https://slack.com/api/users.list', $query);

            if (!$response->ok()) {
                $this->error('HTTP error from Slack: ' . $response->status());
                $this->line($response->body());
                return self::FAILURE;
            }

            $payload = $response->json();

            if (!is_array($payload) || !($payload['ok'] ?? false)) {
                $this->error('Slack API returned ok=false.');
                $this->line(json_encode($payload, JSON_PRETTY_PRINT));
                return self::FAILURE;
            }

            $members = $payload['members'] ?? [];
            if (!is_array($members)) {
                $this->error('Unexpected Slack payload: members is not an array.');
                return self::FAILURE;
            }

            foreach ($members as $u) {
                if (!is_array($u)) {
                    continue;
                }

                // Skip deleted users if requested
                if ($excludeDeleted && (($u['deleted'] ?? false) === true)) {
                    continue;
                }

                // Skip bots if requested (Slack also has "is_app_user" sometimes)
                if ($excludeBots && (($u['is_bot'] ?? false) === true)) {
                    continue;
                }

                $profile = $u['profile'] ?? [];

                $users[] = [
                    'id' => $u['id'] ?? null,
                    'team_id' => $u['team_id'] ?? null,
                    'name' => $u['name'] ?? null,
                    'real_name' => $u['real_name'] ?? null,
                    'display_name' => is_array($profile) ? ($profile['display_name'] ?? null) : null,
                    'email' => is_array($profile) ? ($profile['email'] ?? null) : null,
                    'image_48' => is_array($profile) ? ($profile['image_48'] ?? null) : null,
                    'deleted' => $u['deleted'] ?? false,
                    'is_bot' => $u['is_bot'] ?? false,
                ];
            }

            $cursor = $payload['response_metadata']['next_cursor'] ?? null;
            $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

            $this->line(sprintf('Fetched %d users so far%s', count($users), $cursor ? ' (next page...)' : ''));

        } while ($cursor);

        // Build final JSON
        $data = [
            'fetched_at' => now()->toIso8601String(),
            'count' => count($users),
            'users' => $users,
        ];

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($this->option('pretty')) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $jsonFlags);
        if ($json === false) {
            $this->error('Failed to encode JSON.');
            return self::FAILURE;
        }

        Storage::disk($disk)->put($path, $json);

        $fullPath = Storage::disk($disk)->path($path);
        $this->info("Done. Wrote {$data['count']} users to: {$fullPath}");

        return self::SUCCESS;
    }
}
