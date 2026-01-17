<?php

namespace App\Services\Slack;

use App\Actions\LunchSession\CloseLunchSession;
use App\Actions\Order\CreateOrder;
use App\Actions\Order\UpdateOrder;
use App\Actions\Vendor\CreateVendor;
use App\Actions\Vendor\UpdateVendor;
use App\Actions\VendorProposal\AssignRole;
use App\Actions\VendorProposal\DelegateRole;
use App\Actions\VendorProposal\ProposeVendor;
use App\Authorization\Actor;
use App\Enums\FulfillmentType;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SlackInteractionHandler
{
    public function __construct(
        private readonly SlackMessenger $messenger,
        private readonly SlackBlockBuilder $blocks,
        private readonly CloseLunchSession $closeLunchSession,
        private readonly ProposeVendor $proposeVendor,
        private readonly AssignRole $assignRole,
        private readonly DelegateRole $delegateRole,
        private readonly CreateOrder $createOrder,
        private readonly UpdateOrder $updateOrder,
        private readonly CreateVendor $createVendor,
        private readonly UpdateVendor $updateVendor
    ) {}

    public function handleEvent(array $payload): void
    {
        Log::info('Slack event received.', ['type' => $payload['type'] ?? null]);
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

        switch ($actionId) {
            case SlackActions::OPEN_PROPOSAL_MODAL:
                $session = LunchSession::find($value);
                if (! $session || ! $this->ensureSessionOpen($session, $channelId, $userId)) {
                    return;
                }
                $vendors = Vendor::query()->where('active', true)->orderBy('name')->get()->all();
                if (empty($vendors)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Aucune enseigne active pour le moment.');

                    return;
                }
                $view = $this->blocks->proposalModal($session, $vendors);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_ADD_ENSEIGNE_MODAL:
                $session = LunchSession::find($value);
                $metadata = $session ? ['lunch_session_id' => $session->id] : [];
                $view = $this->blocks->addVendorModal($metadata);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::CLOSE_DAY:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                if (! $this->canCloseSession($session, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer ou un admin peut cloturer.');

                    return;
                }
                $this->closeLunchSession->handle($session);
                $this->messenger->postClosureSummary($session);

                return;
            case SlackActions::CLAIM_RUNNER:
            case SlackActions::CLAIM_ORDERER:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
                    return;
                }
                $role = $actionId === SlackActions::CLAIM_RUNNER ? 'runner' : 'orderer';
                $assigned = $this->assignRole->handle($proposal, $role, $userId);
                if ($assigned) {
                    $this->messenger->updateProposalMessage($proposal);
                } else {
                    $this->messenger->postEphemeral($channelId, $userId, 'Role deja attribue.');
                }

                return;
            case SlackActions::OPEN_ORDER_MODAL:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
                    return;
                }
                $view = $this->blocks->orderModal($proposal, null, false, false);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_EDIT_ORDER_MODAL:
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

                return;
            case SlackActions::OPEN_SUMMARY:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->canManageFinalPrices($proposal, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut voir le recapitulatif.');

                    return;
                }
                $this->messenger->postSummary($proposal);

                return;
            case SlackActions::OPEN_DELEGATE_MODAL:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                $role = $this->roleForUser($proposal, $userId);
                if (! $role) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Vous n\'avez pas de role a deleguer.');

                    return;
                }
                $view = $this->blocks->delegateModal($proposal, $role);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_ADJUST_PRICE_MODAL:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->canManageFinalPrices($proposal, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut ajuster les prix.');

                    return;
                }
                $orders = $proposal->orders()->orderBy('created_at')->get()->all();
                if (empty($orders)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Aucune commande a ajuster.');

                    return;
                }
                $view = $this->blocks->adjustPriceModal($proposal, $orders);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_MANAGE_ENSEIGNE_MODAL:
                $proposal = VendorProposal::with('vendor')->find($value);
                if (! $proposal) {
                    return;
                }
                $vendor = $proposal->vendor;
                if (! $this->canManageVendor($vendor, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Vous ne pouvez pas modifier cette enseigne.');

                    return;
                }
                $metadata = $proposal->lunch_session_id ? ['lunch_session_id' => $proposal->lunch_session_id] : [];
                $view = $this->blocks->editVendorModal($vendor, $metadata);
                $this->messenger->openModal($triggerId, $view);

                return;
            default:
                return;
        }
    }

    private function handleViewSubmission(array $payload): Response
    {
        $callbackId = $payload['view']['callback_id'] ?? '';
        $userId = $payload['user']['id'] ?? '';

        switch ($callbackId) {
            case SlackActions::CALLBACK_PROPOSAL_CREATE:
                return $this->handleProposalSubmission($payload, $userId);
            case SlackActions::CALLBACK_ENSEIGNE_CREATE:
                return $this->handleVendorCreate($payload, $userId);
            case SlackActions::CALLBACK_ENSEIGNE_UPDATE:
                return $this->handleVendorUpdate($payload, $userId);
            case SlackActions::CALLBACK_ORDER_CREATE:
                return $this->handleOrderCreate($payload, $userId);
            case SlackActions::CALLBACK_ORDER_EDIT:
                return $this->handleOrderEdit($payload, $userId);
            case SlackActions::CALLBACK_ROLE_DELEGATE:
                return $this->handleRoleDelegate($payload, $userId);
            case SlackActions::CALLBACK_ORDER_ADJUST_PRICE:
                return $this->handleAdjustPrice($payload, $userId);
            default:
                return response('', 200);
        }
    }

    private function handleProposalSubmission(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $session = LunchSession::find($metadata['lunch_session_id'] ?? null);
        if (! $session) {
            return response('', 200);
        }

        if (! $session->isOpen()) {
            $this->messenger->postEphemeral($session->provider_channel_id, $userId, 'Les commandes sont verrouillees.');

            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $vendorId = $this->stateValue($state, 'enseigne', 'enseigne_id');
        $fulfillment = $this->stateValue($state, 'fulfillment', 'fulfillment_type');
        $platform = $this->stateValue($state, 'platform', 'platform');

        if ($fulfillment && ! in_array($fulfillment, [FulfillmentType::Pickup->value, FulfillmentType::Delivery->value], true)) {
            return $this->viewErrorResponse(['fulfillment' => 'Type invalide.']);
        }

        $vendor = Vendor::query()->where('active', true)->find($vendorId);
        if (! $vendor) {
            return $this->viewErrorResponse(['enseigne' => 'Enseigne invalide.']);
        }

        try {
            $proposal = $this->proposeVendor->handle(
                $session,
                $vendor,
                FulfillmentType::from($fulfillment ?: FulfillmentType::Pickup->value),
                $platform ?: null,
                $userId
            );

            $proposal->setRelation('lunchSession', $session);
            $proposal->setRelation('vendor', $vendor);
            $this->messenger->postProposalMessage($proposal);
        } catch (InvalidArgumentException $e) {
            $this->messenger->postEphemeral($session->provider_channel_id, $userId, $e->getMessage());
        }

        return response('', 200);
    }

    private function handleVendorCreate(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $name = $this->stateValue($state, 'name', 'name');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (! $name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        $this->createVendor->handle($name, $urlMenu ?: null, $notes ?: null, $userId);
        $this->postOptionalFeedback($payload, $userId, 'Enseigne ajoutee.');

        return response('', 200);
    }

    private function handleVendorUpdate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $vendor = Vendor::find($metadata['vendor_id'] ?? null);
        if (! $vendor) {
            return response('', 200);
        }

        if (! $this->canManageVendor($vendor, $userId)) {
            $this->postOptionalFeedback($payload, $userId, 'Vous ne pouvez pas modifier cette enseigne.');

            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $name = $this->stateValue($state, 'name', 'name');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $notes = $this->stateValue($state, 'notes', 'notes');
        $active = $this->stateValue($state, 'active', 'active');

        if (! $name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        $data = [
            'name' => $name,
            'url_menu' => $urlMenu ?: null,
            'notes' => $notes ?: null,
        ];

        if ($active !== null) {
            $data['active'] = $active === '1';
        }

        $this->updateVendor->handle($vendor, $data);
        $this->postOptionalFeedback($payload, $userId, 'Enseigne mise a jour.');

        return response('', 200);
    }

    private function handleOrderCreate(array $payload, string $userId): Response
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

        try {
            if ($existingOrder) {
                $this->updateOrder->handle($existingOrder, $data, $userId);
            } else {
                $this->createOrder->handle($proposal, $userId, $data);
            }
            $this->messenger->updateProposalMessage($proposal);
            $this->postOptionalFeedback($payload, $userId, 'Commande enregistree.');
        } catch (InvalidArgumentException $e) {
            $this->messenger->postEphemeral($proposal->lunchSession->provider_channel_id, $userId, $e->getMessage());
        }

        return response('', 200);
    }

    private function handleOrderEdit(array $payload, string $userId): Response
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

    private function handleRoleDelegate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = VendorProposal::with('lunchSession')->find($metadata['proposal_id'] ?? null);
        $role = $metadata['role'] ?? null;
        if (! $proposal || ! $role) {
            return response('', 200);
        }

        $newUserId = $this->stateValue($payload['view']['state']['values'] ?? [], 'delegate', 'user_id');
        if (! $newUserId) {
            return response('', 200);
        }

        $delegated = $this->delegateRole->handle($proposal, $role, $userId, $newUserId);
        if (! $delegated) {
            $this->postOptionalFeedback($payload, $userId, "Vous n'etes pas {$role}.");

            return response('', 200);
        }

        $this->messenger->updateProposalMessage($proposal);
        $this->messenger->postRoleDelegation($proposal, $role, $userId, $newUserId);

        return response('', 200);
    }

    private function handleAdjustPrice(array $payload, string $userId): Response
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

    private function ensureSessionOpen(?LunchSession $session, string $channelId, string $userId): bool
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

    private function orderStateData(array $state, bool $allowFinal): array|Response
    {
        $description = $this->stateValue($state, 'description', 'description');
        $priceEstimatedRaw = $this->stateValue($state, 'price_estimated', 'price_estimated');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (! $description) {
            return $this->viewErrorResponse(['description' => 'Description requise.']);
        }

        $priceEstimated = $this->parsePrice($priceEstimatedRaw);
        if ($priceEstimated === null) {
            return $this->viewErrorResponse(['price_estimated' => 'Prix estime invalide.']);
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

    private function canManageVendor(Vendor $vendor, string $userId): bool
    {
        $actor = $this->buildActor($userId);

        return Gate::forUser($actor)->allows('update', $vendor);
    }

    private function canCloseSession(LunchSession $session, string $userId): bool
    {
        $actor = $this->buildActor($userId);

        if ($actor->isAdmin) {
            return true;
        }

        return VendorProposal::query()
            ->where('lunch_session_id', $session->id)
            ->where(function ($query) use ($userId) {
                $query->where('runner_user_id', $userId)
                    ->orWhere('orderer_user_id', $userId);
            })
            ->exists();
    }

    private function isRunnerOrOrderer(VendorProposal $proposal, string $userId): bool
    {
        return $proposal->hasRole($userId);
    }

    private function canManageFinalPrices(VendorProposal $proposal, string $userId): bool
    {
        $actor = $this->buildActor($userId);

        if ($actor->isAdmin) {
            return true;
        }

        return $proposal->runner_user_id === $actor->providerUserId
            || $proposal->orderer_user_id === $actor->providerUserId;
    }

    private function roleForUser(VendorProposal $proposal, string $userId): ?string
    {
        return $proposal->getRoleFor($userId);
    }

    private function stateValue(array $state, string $blockId, string $actionId): ?string
    {
        return Arr::get($state, "{$blockId}.{$actionId}.value")
            ?? Arr::get($state, "{$blockId}.{$actionId}.selected_option.value")
            ?? Arr::get($state, "{$blockId}.{$actionId}.selected_user");
    }

    private function decodeMetadata(string $metadata): array
    {
        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function parsePrice(?string $value): ?float
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

    private function viewErrorResponse(array $errors): Response
    {
        $payload = [
            'response_action' => 'errors',
            'errors' => $errors,
        ];

        return response()->json($payload, 200);
    }

    private function postOptionalFeedback(array $payload, string $userId, string $message): void
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

    private function buildActor(string $userId): Actor
    {
        return new Actor($userId, $this->messenger->isAdmin($userId));
    }
}
