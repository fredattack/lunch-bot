<?php

namespace App\Actions\Slack;

use App\Models\Vendor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoveVendorEmojiFromSlack
{
    private const SLACK_EMOJI_REMOVE = 'https://slack.com/api/admin.emoji.remove';

    public function handle(Vendor $vendor): void
    {
        if (! $vendor->emoji_name) {
            return;
        }

        $token = config('services.slack.admin_token');

        if (! $token) {
            Log::warning('Slack emoji remove: SLACK_ADMIN_TOKEN not configured', [
                'vendor_id' => $vendor->id,
            ]);

            return;
        }

        $emojiName = $vendor->emoji_name;

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post(self::SLACK_EMOJI_REMOVE, [
                    'name' => $emojiName,
                ]);

            $data = $response->json();

            if (($data['ok'] ?? false) !== true) {
                $error = $data['error'] ?? 'unknown';

                if ($error !== 'emoji_not_found') {
                    Log::warning('Slack emoji remove: API error', [
                        'vendor_id' => $vendor->id,
                        'emoji_name' => $emojiName,
                        'error' => $error,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Slack emoji remove: failed', [
                'vendor_id' => $vendor->id,
                'emoji_name' => $emojiName,
                'error' => $e->getMessage(),
            ]);
        }

        $vendor->emoji_name = null;
        $vendor->save();
    }
}
