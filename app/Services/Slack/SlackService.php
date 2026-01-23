<?php

namespace App\Services\Slack;

use App\Models\Organization;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    public function postMessage(string $channel, string $text, array $blocks = [], ?string $threadTs = null): array
    {
        $payload = [
            'channel' => $channel,
            'text' => $text,
            'blocks' => $blocks,
        ];

        if ($threadTs) {
            $payload['thread_ts'] = $threadTs;
        }

        return $this->api('chat.postMessage', $payload);
    }

    public function updateMessage(string $channel, string $ts, string $text, array $blocks = []): array
    {
        return $this->api('chat.update', [
            'channel' => $channel,
            'ts' => $ts,
            'text' => $text,
            'blocks' => $blocks,
        ]);
    }

    public function postEphemeral(string $channel, string $user, string $text, array $blocks = [], ?string $threadTs = null): array
    {
        $payload = [
            'channel' => $channel,
            'user' => $user,
            'text' => $text,
            'blocks' => $blocks,
        ];

        if ($threadTs) {
            $payload['thread_ts'] = $threadTs;
        }

        return $this->api('chat.postEphemeral', $payload);
    }

    public function openModal(string $triggerId, array $view): array
    {
        return $this->api('views.open', [
            'trigger_id' => $triggerId,
            'view' => $view,
        ]);
    }

    public function pushModal(string $triggerId, array $view): array
    {
        return $this->api('views.push', [
            'trigger_id' => $triggerId,
            'view' => $view,
        ]);
    }

    public function updateModal(string $viewId, array $view): array
    {
        return $this->api('views.update', [
            'view_id' => $viewId,
            'view' => $view,
        ]);
    }

    public function usersInfo(string $userId): ?array
    {
        $response = $this->api('users.info', ['user' => $userId]);

        return $response['user'] ?? null;
    }

    public function teamInfo(): ?array
    {
        $response = $this->api('team.info', []);

        return $response['team'] ?? null;
    }

    public function isAdmin(string $userId): bool
    {
        $adminIds = config('slack.admin_user_ids', []);

        if (in_array($userId, $adminIds, true)) {
            return true;
        }

        $user = $this->usersInfo($userId);

        return (bool) ($user['is_admin'] ?? false) || (bool) ($user['is_owner'] ?? false);
    }

    public function getFileInfo(string $fileId): ?array
    {
        $response = $this->api('files.info', ['file' => $fileId]);

        return $response['file'] ?? null;
    }

    public function downloadFile(string $urlPrivate): ?string
    {
        $token = $this->resolveToken();
        if (! $token) {
            return null;
        }

        $response = Http::withToken($token)->get($urlPrivate);

        if (! $response->ok()) {
            Log::error('Slack file download failed.', [
                'url' => $urlPrivate,
                'status' => $response->status(),
            ]);

            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'slack_file_');
        file_put_contents($tempPath, $response->body());

        return $tempPath;
    }

    private function resolveToken(): ?string
    {
        $organization = Organization::current();

        if ($organization?->installation?->bot_token) {
            return $organization->installation->bot_token;
        }

        return config('slack.bot_token');
    }

    private function api(string $method, array $payload): array
    {
        $token = $this->resolveToken();

        if (! $token) {
            Log::error('Slack bot token missing.');

            return ['ok' => false, 'error' => 'missing_token'];
        }

        $response = $this->client($token)->post('https://slack.com/api/'.$method, $payload);

        if (! $response->ok()) {
            Log::error('Slack API HTTP error.', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'error' => 'http_error'];
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            Log::warning('Slack API error.', [
                'method' => $method,
                'error' => $data['error'] ?? 'unknown',
            ]);
        }

        return $data ?? ['ok' => false];
    }

    private function client(string $token): PendingRequest
    {
        return Http::withToken($token)->asJson();
    }
}
