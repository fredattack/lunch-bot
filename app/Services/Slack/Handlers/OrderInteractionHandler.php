<?php

namespace App\Services\Slack\Handlers;

use App\Actions\Order\CreateOrder;
use App\Actions\Order\DeleteOrder;
use App\Actions\Order\UpdateOrder;
use App\Enums\SlackAction;
use App\Models\Order;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class OrderInteractionHandler extends BaseInteractionHandler
{
    public function __construct(
        SlackService $slack,
        SlackMessenger $messenger,
        SlackBlockBuilder $blocks,
        private readonly CreateOrder $createOrder,
        private readonly UpdateOrder $updateOrder,
        private readonly DeleteOrder $deleteOrder
    ) {
        parent::__construct($slack, $messenger, $blocks);
    }

    public function handleBlockAction(string $actionId, string $value, string $userId, string $triggerId, string $channelId): void
    {
        match ($actionId) {
            SlackAction::OpenOrderForProposal->value => $this->openOrderForProposal($value, $userId, $triggerId),
            SlackAction::OrderOpenEdit->value => $this->openEditOrder($value, $userId, $triggerId),
            SlackAction::OrderDelete->value => $this->deleteUserOrder($value, $userId, $channelId),
            SlackAction::DashboardJoinProposal->value => $this->joinProposal($value, $userId, $triggerId),
            SlackAction::DashboardOrderHere->value => $this->orderHere($value, $userId, $triggerId),
            SlackAction::DashboardMyOrder->value => $this->myOrder($value, $userId, $triggerId),
            SlackAction::OpenOrderModal->value => $this->openOrderModal($value, $userId, $channelId, $triggerId),
            SlackAction::OpenEditOrderModal->value => $this->openEditOrderModal($value, $userId, $channelId, $triggerId),
            default => null,
        };
    }

    public function handleOrderCreate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = VendorProposal::with('lunchSession')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if (! $this->ensureSessionOpen($proposal->lunchSession, $proposal->lunchSession->provider_channel_id, $userId)) {
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $data = $this->orderStateData($state, false);
        if ($data instanceof Response) {
            return $data;
        }

        $existingOrder = Order::query()
            ->where('vendor_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();

        $isFirstOrderForProposal = ! $proposal->provider_message_ts
            && $proposal->orders()->count() === 0;

        if ($existingOrder) {
            $this->updateOrder->handle($existingOrder, $data, $userId);
        } else {
            $order = $this->createOrder->handle($proposal, $userId, $data);
        }

        if ($isFirstOrderForProposal && ! $existingOrder) {
            $hasOtherOrderInSession = Order::query()
                ->whereHas('proposal', fn ($q) => $q->where('lunch_session_id', $proposal->lunch_session_id))
                ->where('provider_user_id', $userId)
                ->where('vendor_proposal_id', '!=', $proposal->id)
                ->exists();

            $this->messenger->postOrderCreatedMessage($proposal, $userId, $hasOtherOrderInSession);
        } elseif (! $existingOrder && isset($order)) {
            $this->messenger->notifyProposalCreator($proposal, $order);
        }

        $this->postOptionalFeedback($payload, $userId, 'Commande enregistree.');

        return $this->viewClearResponse();
    }

    public function handleOrderEdit(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = VendorProposal::with('lunchSession')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if ($proposal->lunchSession->isClosed()) {
            $this->messenger->postEphemeral($proposal->lunchSession->provider_channel_id, $userId, 'La journee est cloturee.');

            return response('', 200);
        }

        $order = Order::query()
            ->where('vendor_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();
        if (! $order) {
            return response('', 200);
        }

        $allowFinal = $this->canManageFinalPrices($proposal, $userId);
        if (! $proposal->lunchSession->isOpen() && ! $allowFinal) {
            $this->messenger->postEphemeral($proposal->lunchSession->provider_channel_id, $userId, 'Les commandes sont verrouillees.');

            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $data = $this->orderStateData($state, $allowFinal);
        if ($data instanceof Response) {
            return $data;
        }

        $this->updateOrder->handle($order, $data, $userId);
        $this->messenger->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Commande mise a jour.');

        return response('', 200);
    }

    public function handleAdjustPrice(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = VendorProposal::with('lunchSession')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if ($proposal->lunchSession->isClosed()) {
            return response('', 200);
        }

        if (! $this->canManageFinalPrices($proposal, $userId)) {
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $orderId = $this->stateValue($state, 'order', 'order_id');
        $priceFinalRaw = $this->stateValue($state, 'price_final', 'price_final');
        $priceFinal = $this->parsePrice($priceFinalRaw);
        if ($priceFinal === null) {
            return $this->viewErrorResponse(['price_final' => 'Prix final invalide.']);
        }

        $order = Order::find($orderId);
        if (! $order || $order->vendor_proposal_id !== $proposal->id) {
            return response('', 200);
        }

        $this->updateOrder->handle($order, ['price_final' => $priceFinal], $userId);
        $this->messenger->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Prix final mis a jour.');

        return response('', 200);
    }

    private function openOrderForProposal(string $value, string $userId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $sessionChannel = $proposal->lunchSession->provider_channel_id;
        if (! $this->ensureSessionOpen($proposal->lunchSession, $sessionChannel, $userId)) {
            return;
        }

        $existingOrder = Order::query()
            ->where('vendor_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();

        if ($existingOrder) {
            $allowFinal = $this->canManageFinalPrices($proposal, $userId);
            $view = $this->blocks->orderModal($proposal, $existingOrder, $allowFinal, true);
        } else {
            $view = $this->blocks->orderModal($proposal, null, false, false);
        }

        $this->messenger->openModal($triggerId, $view);
    }

    private function openEditOrder(string $value, string $userId, string $triggerId): void
    {
        $order = Order::with('proposal.lunchSession')->find($value);
        if (! $order) {
            return;
        }

        $proposal = $order->proposal;
        $allowFinal = $this->canManageFinalPrices($proposal, $userId);
        $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function deleteUserOrder(string $value, string $userId, string $channelId): void
    {
        $order = Order::with('proposal.lunchSession')->find($value);
        if (! $order) {
            return;
        }

        $sessionChannel = $order->proposal->lunchSession->provider_channel_id;

        try {
            $proposal = $order->proposal;
            $this->deleteOrder->handle($order, $userId);
            $this->messenger->updateProposalMessage($proposal);
            $this->messenger->postEphemeral($sessionChannel, $userId, 'Commande supprimee.');
        } catch (InvalidArgumentException $e) {
            $this->messenger->postEphemeral($sessionChannel, $userId, $e->getMessage());
        }
    }

    private function joinProposal(string $value, string $userId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $sessionChannel = $proposal->lunchSession->provider_channel_id;
        if (! $this->ensureSessionOpen($proposal->lunchSession, $sessionChannel, $userId)) {
            return;
        }

        $view = $this->blocks->orderModal($proposal, null, false, false);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function orderHere(string $value, string $userId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $sessionChannel = $proposal->lunchSession->provider_channel_id;
        if (! $this->ensureSessionOpen($proposal->lunchSession, $sessionChannel, $userId)) {
            return;
        }

        $view = $this->blocks->orderModal($proposal, null, false, false);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function myOrder(string $value, string $userId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $order = Order::query()
            ->where('vendor_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();

        if (! $order) {
            $sessionChannel = $proposal->lunchSession->provider_channel_id;
            $this->messenger->postEphemeral($sessionChannel, $userId, 'Aucune commande a modifier.');

            return;
        }

        $allowFinal = $this->canManageFinalPrices($proposal, $userId);
        $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function openOrderModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
            return;
        }

        $view = $this->blocks->orderModal($proposal, null, false, false);
        $this->messenger->openModal($triggerId, $view);
    }

    private function openEditOrderModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $order = Order::query()
            ->where('vendor_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();

        if (! $order) {
            $this->messenger->postEphemeral($channelId, $userId, 'Aucune commande a modifier.');

            return;
        }

        $allowFinal = $this->canManageFinalPrices($proposal, $userId);
        $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
        $this->messenger->openModal($triggerId, $view);
    }

    private function orderStateData(array $state, bool $allowFinal): array|Response
    {
        $description = $this->stateValue($state, 'description', 'description');
        $priceEstimatedRaw = $this->stateValue($state, 'price_estimated', 'price_estimated');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (! $description) {
            return $this->viewErrorResponse(['description' => 'Description requise.']);
        }

        $priceEstimated = null;
        if ($priceEstimatedRaw !== null && $priceEstimatedRaw !== '') {
            $priceEstimated = $this->parsePrice($priceEstimatedRaw);
            if ($priceEstimated === null) {
                return $this->viewErrorResponse(['price_estimated' => 'Prix estime invalide.']);
            }
        }

        $data = [
            'description' => $description,
            'price_estimated' => $priceEstimated,
            'notes' => $notes ?: null,
        ];

        if ($allowFinal) {
            $priceFinalRaw = $this->stateValue($state, 'price_final', 'price_final');
            if ($priceFinalRaw !== null && $priceFinalRaw !== '') {
                $priceFinal = $this->parsePrice($priceFinalRaw);
                if ($priceFinal === null) {
                    return $this->viewErrorResponse(['price_final' => 'Prix final invalide.']);
                }
                $data['price_final'] = $priceFinal;
            }
        }

        return $data;
    }
}
