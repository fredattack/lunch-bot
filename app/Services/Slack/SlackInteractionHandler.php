<?php

namespace App\Services\Slack;

use App\Enums\SlackAction;
use App\Services\Slack\Handlers\OrderInteractionHandler;
use App\Services\Slack\Handlers\ProposalInteractionHandler;
use App\Services\Slack\Handlers\SessionInteractionHandler;
use App\Services\Slack\Handlers\VendorInteractionHandler;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SlackInteractionHandler
{
    public function __construct(
        private readonly SlackBlockBuilder $blocks,
        private readonly OrderInteractionHandler $orderHandler,
        private readonly ProposalInteractionHandler $proposalHandler,
        private readonly SessionInteractionHandler $sessionHandler,
        private readonly VendorInteractionHandler $vendorHandler
    ) {}

    public function handleEvent(array $payload): void
    {
        Log::info('Slack event received.', ['type' => $payload['type'] ?? null]);
    }

    public function handleLunchDashboard(string $userId, string $channelId, string $triggerId, ?string $dateOverride = null): void
    {
        $this->sessionHandler->handleLunchDashboard($userId, $channelId, $triggerId, $dateOverride);
    }

    public function handleInteractivity(array $payload): Response
    {
        $type = $payload['type'] ?? '';

        if ($type === 'block_actions') {
            $this->handleBlockActions($payload);

            return response('', 200);
        }

        if ($type === 'view_submission') {
            return $this->handleViewSubmission($payload);
        }

        return response('', 200);
    }

    private function handleBlockActions(array $payload): void
    {
        $action = $payload['actions'][0] ?? [];
        $actionId = $action['action_id'] ?? '';
        $value = $action['value'] ?? '';
        $userId = $payload['user']['id'] ?? '';
        $triggerId = $payload['trigger_id'] ?? '';
        $channelId = $payload['channel']['id'] ?? config('lunch.channel_id');

        $slackAction = SlackAction::tryFrom($actionId);
        if (! $slackAction) {
            return;
        }

        if ($slackAction->isSession()) {
            $this->sessionHandler->handleBlockAction($actionId, $value, $userId, $triggerId, $channelId);

            return;
        }

        if ($slackAction->isOrder()) {
            $this->orderHandler->handleBlockAction($actionId, $value, $userId, $triggerId, $channelId);

            return;
        }

        if ($slackAction->isDev()) {
            $this->vendorHandler->handleBlockAction($actionId, $value, $userId, $triggerId, $channelId, $payload);

            return;
        }

        if ($slackAction->isVendor()) {
            $this->vendorHandler->handleBlockAction($actionId, $value, $userId, $triggerId, $channelId, $payload);

            return;
        }

        if ($slackAction->isProposal()) {
            $this->proposalHandler->handleBlockAction($actionId, $value, $userId, $triggerId, $channelId);

            return;
        }

        Log::warning('Unrouted Slack block action', ['action_id' => $actionId]);
    }

    private function handleViewSubmission(array $payload): Response
    {
        $callbackId = $payload['view']['callback_id'] ?? '';
        $userId = $payload['user']['id'] ?? '';

        Log::info('View submission received', [
            'callback_id' => $callbackId,
            'user_id' => $userId,
        ]);

        try {
            return match ($callbackId) {
                SlackAction::CallbackProposalCreate->value, 'proposal.create' => $this->proposalHandler->handleProposalSubmission($payload, $userId),
                SlackAction::CallbackRestaurantPropose->value, 'restaurant.propose' => $this->proposalHandler->handleRestaurantPropose($payload, $userId),
                SlackAction::CallbackEnseigneCreate->value, 'enseigne.create' => $this->vendorHandler->handleVendorCreate($payload, $userId),
                SlackAction::CallbackEnseigneUpdate->value, 'enseigne.update' => $this->vendorHandler->handleVendorUpdate($payload, $userId),
                SlackAction::CallbackOrderCreate->value, 'order.create' => $this->orderHandler->handleOrderCreate($payload, $userId),
                SlackAction::CallbackOrderEdit->value, 'order.edit' => $this->orderHandler->handleOrderEdit($payload, $userId),
                SlackAction::CallbackRoleDelegate->value, 'role.delegate' => $this->proposalHandler->handleRoleDelegate($payload, $userId),
                SlackAction::CallbackOrderAdjustPrice->value, 'order.adjust_price' => $this->orderHandler->handleAdjustPrice($payload, $userId),
                default => response('', 200),
            };
        } catch (InvalidArgumentException $e) {
            Log::warning('Slack view submission business error', ['message' => $e->getMessage()]);

            return response()->json([
                'response_action' => 'update',
                'view' => $this->blocks->errorModal('Erreur', $e->getMessage()),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Slack view submission error', ['exception' => $e->getMessage()]);

            return response()->json([
                'response_action' => 'update',
                'view' => $this->blocks->errorModal('Erreur', 'Une erreur est survenue. Veuillez reessayer.'),
            ], 200);
        }
    }
}
