<?php

namespace App\Services\Slack\Handlers;

use App\Authorization\Actor;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseInteractionHandler
{
    public function __construct(
        protected readonly SlackService $slack,
        protected readonly SlackMessenger $messenger,
        protected readonly SlackBlockBuilder $blocks
    ) {}

    protected function ensureSessionOpen(?LunchSession $session, string $channelId, string $userId): bool
    {
        if (! $session) {
            return false;
        }

        if (! $session->isOpen()) {
            $this->messenger->postEphemeral($channelId, $userId, 'Les commandes sont verrouillees.');

            return false;
        }

        return true;
    }

    protected function buildActor(string $userId): Actor
    {
        return new Actor($userId, $this->messenger->isAdmin($userId));
    }

    protected function stateValue(array $state, string $blockId, string $actionId): ?string
    {
        return Arr::get($state, "{$blockId}.{$actionId}.value")
            ?? Arr::get($state, "{$blockId}.{$actionId}.selected_option.value")
            ?? Arr::get($state, "{$blockId}.{$actionId}.selected_user");
    }

    protected function stateCheckboxHasValue(array $state, string $blockId, string $actionId, string $value): bool
    {
        $selectedOptions = Arr::get($state, "{$blockId}.{$actionId}.selected_options", []);

        foreach ($selectedOptions as $option) {
            if (($option['value'] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    protected function stateCheckboxValues(array $state, string $blockId, string $actionId): array
    {
        $selectedOptions = Arr::get($state, "{$blockId}.{$actionId}.selected_options", []);

        return array_map(fn ($option) => $option['value'] ?? '', $selectedOptions);
    }

    protected function stateFiles(array $state, string $blockId, string $actionId): array
    {
        return Arr::get($state, "{$blockId}.{$actionId}.files", []);
    }

    protected function decodeMetadata(string $metadata): array
    {
        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function parsePrice(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    protected function viewErrorResponse(array $errors): Response
    {
        return response()->json([
            'response_action' => 'errors',
            'errors' => $errors,
        ], 200);
    }

    protected function viewUpdateResponse(array $view): Response
    {
        return response()->json([
            'response_action' => 'update',
            'view' => $view,
        ], 200);
    }

    protected function viewClearResponse(): Response
    {
        return response()->json(['response_action' => 'clear'], 200);
    }

    protected function canManageFinalPrices(VendorProposal $proposal, string $userId): bool
    {
        $actor = $this->buildActor($userId);

        if ($actor->isAdmin) {
            return true;
        }

        return $proposal->runner_user_id === $actor->providerUserId
            || $proposal->orderer_user_id === $actor->providerUserId;
    }

    protected function processFileUpload(Vendor $vendor, array $fileData): void
    {
        $urlPrivate = $fileData['url_private'] ?? null;
        $mimetype = $fileData['mimetype'] ?? '';
        $filename = $fileData['name'] ?? 'file';

        if (! $urlPrivate) {
            Log::warning('Vendor file upload: missing url_private', [
                'vendor_id' => $vendor->id,
                'file_data' => $fileData,
            ]);

            return;
        }

        $tempPath = $this->slack->downloadFile($urlPrivate);
        if (! $tempPath) {
            Log::warning('Vendor file upload: download failed', [
                'vendor_id' => $vendor->id,
                'url' => $urlPrivate,
            ]);

            return;
        }

        $collection = str_starts_with($mimetype, 'image/') ? 'logo' : 'menu';

        try {
            $vendor->addMedia($tempPath)
                ->usingFileName($filename)
                ->toMediaCollection($collection);

            Log::info('Vendor file upload: success', [
                'vendor_id' => $vendor->id,
                'collection' => $collection,
                'filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            Log::error('Vendor file upload: media library error', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    protected function postOptionalFeedback(array $payload, string $userId, string $message): void
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $sessionId = $metadata['lunch_session_id'] ?? null;
        $channelId = config('lunch.channel_id');
        $threadTs = null;

        if ($sessionId) {
            $session = LunchSession::find($sessionId);
            if ($session) {
                $channelId = $session->provider_channel_id;
                $threadTs = $session->provider_message_ts;
            }
        }

        if ($channelId) {
            $this->messenger->postEphemeral($channelId, $userId, $message, $threadTs);
        }
    }
}
