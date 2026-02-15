<?php

namespace App\Actions\Slack;

use App\Models\Vendor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SyncVendorEmojiToSlack
{
    private const MAX_FILE_SIZE = 128 * 1024;

    private const EMOJI_PREFIX = 'lb_';

    private const SLACK_EMOJI_ADD = 'https://slack.com/api/admin.emoji.add';

    private const SLACK_EMOJI_REMOVE = 'https://slack.com/api/admin.emoji.remove';

    public function handle(Vendor $vendor): Vendor
    {
        $media = $vendor->getFirstMedia('logo');

        if (! $media) {
            return $vendor;
        }

        $emojiName = self::generateEmojiName($vendor->name);
        $tempPath = $this->convertToEmojiImage($media);

        if (! $tempPath) {
            Log::error('Slack emoji sync: failed to convert image', [
                'vendor_id' => $vendor->id,
            ]);

            return $vendor;
        }

        try {
            $publicUrl = $this->storeTemporarily($tempPath, $emojiName);
            $this->uploadEmoji($emojiName, $publicUrl);

            $vendor->emoji_name = $emojiName;
            $vendor->save();

            Log::info('Slack emoji sync: success', [
                'vendor_id' => $vendor->id,
                'emoji_name' => $emojiName,
            ]);
        } catch (\Throwable $e) {
            Log::error('Slack emoji sync: failed', [
                'vendor_id' => $vendor->id,
                'emoji_name' => $emojiName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->cleanupTemporaryFile($emojiName);
            @unlink($tempPath);
        }

        return $vendor;
    }

    public static function generateEmojiName(string $vendorName): string
    {
        $slug = Str::of($vendorName)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $name = self::EMOJI_PREFIX.$slug;

        return Str::limit($name, 100, '');
    }

    private function convertToEmojiImage(Media $media): ?string
    {
        $sourcePath = $media->getPath();

        if (! file_exists($sourcePath)) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'emoji_').'.png';

        Image::load($sourcePath)
            ->fit(Fit::Crop, 128, 128)
            ->format('png')
            ->save($tempPath);

        if (filesize($tempPath) > self::MAX_FILE_SIZE) {
            @unlink($tempPath);
            $tempPath = tempnam(sys_get_temp_dir(), 'emoji_').'.png';

            Image::load($sourcePath)
                ->fit(Fit::Crop, 64, 64)
                ->format('png')
                ->save($tempPath);
        }

        if (filesize($tempPath) > self::MAX_FILE_SIZE) {
            @unlink($tempPath);

            return null;
        }

        return $tempPath;
    }

    private function storeTemporarily(string $tempPath, string $emojiName): string
    {
        $storagePath = "emoji-temp/{$emojiName}.png";

        Storage::disk('public')->put($storagePath, file_get_contents($tempPath));

        return Storage::disk('public')->url($storagePath);
    }

    private function cleanupTemporaryFile(string $emojiName): void
    {
        Storage::disk('public')->delete("emoji-temp/{$emojiName}.png");
    }

    private function uploadEmoji(string $name, string $url): void
    {
        $token = config('services.slack.admin_token');

        if (! $token) {
            throw new \RuntimeException('SLACK_ADMIN_TOKEN is not configured.');
        }

        $response = Http::withToken($token)
            ->timeout(15)
            ->post(self::SLACK_EMOJI_ADD, [
                'name' => $name,
                'url' => $url,
            ]);

        $data = $response->json();

        if (($data['ok'] ?? false) === true) {
            return;
        }

        $error = $data['error'] ?? 'unknown';

        if ($error === 'error_name_taken') {
            $this->removeEmoji($name, $token);
            $this->retryAddEmoji($name, $url, $token);

            return;
        }

        throw new \RuntimeException("Slack admin.emoji.add failed: {$error}");
    }

    private function removeEmoji(string $name, string $token): void
    {
        $response = Http::withToken($token)
            ->timeout(15)
            ->post(self::SLACK_EMOJI_REMOVE, [
                'name' => $name,
            ]);

        $data = $response->json();

        if (($data['ok'] ?? false) !== true) {
            $error = $data['error'] ?? 'unknown';

            throw new \RuntimeException("Slack admin.emoji.remove failed: {$error}");
        }
    }

    private function retryAddEmoji(string $name, string $url, string $token): void
    {
        $response = Http::withToken($token)
            ->timeout(15)
            ->post(self::SLACK_EMOJI_ADD, [
                'name' => $name,
                'url' => $url,
            ]);

        $data = $response->json();

        if (($data['ok'] ?? false) !== true) {
            $error = $data['error'] ?? 'unknown';

            throw new \RuntimeException("Slack admin.emoji.add (retry) failed: {$error}");
        }
    }
}
