<?php

namespace App\Slack;

use App\Actions\Lunch\AdjustOrderPrice;
use App\Actions\Lunch\AssignRole;
use App\Actions\Lunch\CloseLunchDay;
use App\Actions\Lunch\CreateEnseigne;
use App\Actions\Lunch\CreateOrder;
use App\Actions\Lunch\DelegateRole;
use App\Actions\Lunch\ProposeRestaurant;
use App\Actions\Lunch\UpdateEnseigne;
use App\Actions\Lunch\UpdateOrder;
use App\Enums\FulfillmentType;
use App\Enums\LunchDayStatus;
use App\Models\Enseigne;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SlackInteractionHandler
{
    public function __construct(
        private readonly SlackMessenger $messenger,
        private readonly SlackBlockBuilder $blocks,
        private readonly CloseLunchDay $closeLunchDay,
        private readonly ProposeRestaurant $proposeRestaurant,
        private readonly AssignRole $assignRole,
        private readonly DelegateRole $delegateRole,
        private readonly CreateOrder $createOrder,
        private readonly UpdateOrder $updateOrder,
        private readonly AdjustOrderPrice $adjustOrderPrice,
        private readonly CreateEnseigne $createEnseigne,
        private readonly UpdateEnseigne $updateEnseigne
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
                $day = LunchDay::find($value);
                if (! $day || ! $this->ensureDayOpen($day, $channelId, $userId)) {
                    return;
                }
                $enseignes = Enseigne::query()->where('active', true)->orderBy('name')->get()->all();
                if (empty($enseignes)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Aucune enseigne active pour le moment.');

                    return;
                }
                $view = $this->blocks->proposalModal($day, $enseignes);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_ADD_ENSEIGNE_MODAL:
                $day = LunchDay::find($value);
                $metadata = $day ? ['lunch_day_id' => $day->id] : [];
                $view = $this->blocks->addEnseigneModal($metadata);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::CLOSE_DAY:
                $day = LunchDay::find($value);
                if (! $day) {
                    return;
                }
                if (! $this->canCloseDay($day, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer ou un admin peut cloturer.');

                    return;
                }
                $this->closeLunchDay->handle($day);
                $this->messenger->postClosureSummary($day);

                return;
            case SlackActions::CLAIM_RUNNER:
            case SlackActions::CLAIM_ORDERER:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (! $proposal || ! $this->ensureDayOpen($proposal->lunchDay, $channelId, $userId)) {
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
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (! $proposal || ! $this->ensureDayOpen($proposal->lunchDay, $channelId, $userId)) {
                    return;
                }
                $view = $this->blocks->orderModal($proposal, null, false, false);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_EDIT_ORDER_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (! $proposal) {
                    return;
                }
                $order = Order::query()
                    ->where('lunch_day_proposal_id', $proposal->id)
                    ->where('provider_user_id', $userId)
                    ->first();
                if (! $order) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Aucune commande a modifier.');

                    return;
                }
                $allowFinal = $this->isRunnerOrOrderer($proposal, $userId) || $this->messenger->isAdmin($userId);
                $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackActions::OPEN_SUMMARY:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->isRunnerOrOrderer($proposal, $userId) && ! $this->messenger->isAdmin($userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut voir le recapitulatif.');

                    return;
                }
                $this->messenger->postSummary($proposal);

                return;
            case SlackActions::OPEN_DELEGATE_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
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
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->isRunnerOrOrderer($proposal, $userId) && ! $this->messenger->isAdmin($userId)) {
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
                $proposal = LunchDayProposal::with('enseigne')->find($value);
                if (! $proposal) {
                    return;
                }
                $enseigne = $proposal->enseigne;
                if (! $this->canManageEnseigne($enseigne, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Vous ne pouvez pas modifier cette enseigne.');

                    return;
                }
                $metadata = $proposal->lunch_day_id ? ['lunch_day_id' => $proposal->lunch_day_id] : [];
                $view = $this->blocks->editEnseigneModal($enseigne, $metadata);
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
                return $this->handleEnseigneCreate($payload, $userId);
            case SlackActions::CALLBACK_ENSEIGNE_UPDATE:
                return $this->handleEnseigneUpdate($payload, $userId);
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
        $day = LunchDay::find($metadata['lunch_day_id'] ?? null);
        if (! $day) {
            return response('', 200);
        }

        if ($day->status !== LunchDayStatus::Open) {
            $this->messenger->postEphemeral($day->provider_channel_id, $userId, 'Les commandes sont verrouillees.');

            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $enseigneId = $this->stateValue($state, 'enseigne', 'enseigne_id');
        $fulfillment = $this->stateValue($state, 'fulfillment', 'fulfillment_type');
        $platform = $this->stateValue($state, 'platform', 'platform');

        if ($fulfillment && ! in_array($fulfillment, [FulfillmentType::Pickup->value, FulfillmentType::Delivery->value], true)) {
            return $this->viewErrorResponse(['fulfillment' => 'Type invalide.']);
        }

        $enseigne = Enseigne::query()->where('active', true)->find($enseigneId);
        if (! $enseigne) {
            return $this->viewErrorResponse(['enseigne' => 'Enseigne invalide.']);
        }

        try {
            $proposal = $this->proposeRestaurant->handle(
                $day,
                $enseigne,
                FulfillmentType::from($fulfillment ?: FulfillmentType::Pickup->value),
                $platform ?: null,
                $userId
            );

            $proposal->setRelation('lunchDay', $day);
            $proposal->setRelation('enseigne', $enseigne);
            $this->messenger->postProposalMessage($proposal);
        } catch (InvalidArgumentException $e) {
            $this->messenger->postEphemeral($day->provider_channel_id, $userId, $e->getMessage());
        }

        return response('', 200);
    }

    private function handleEnseigneCreate(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $name = $this->stateValue($state, 'name', 'name');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (! $name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        $this->createEnseigne->handle($name, $urlMenu ?: null, $notes ?: null, $userId);
        $this->postOptionalFeedback($payload, $userId, 'Enseigne ajoutee.');

        return response('', 200);
    }

    private function handleEnseigneUpdate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $enseigne = Enseigne::find($metadata['enseigne_id'] ?? null);
        if (! $enseigne) {
            return response('', 200);
        }

        if (! $this->canManageEnseigne($enseigne, $userId)) {
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

        $this->updateEnseigne->handle($enseigne, $data);
        $this->postOptionalFeedback($payload, $userId, 'Enseigne mise a jour.');

        return response('', 200);
    }

    private function handleOrderCreate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if (! $this->ensureDayOpen($proposal->lunchDay, $proposal->lunchDay->provider_channel_id, $userId)) {
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $data = $this->orderStateData($state, false);
        if ($data instanceof Response) {
            return $data;
        }

        try {
            $this->createOrder->handle($proposal, $userId, $data);
            $this->messenger->updateProposalMessage($proposal);
            $this->postOptionalFeedback($payload, $userId, 'Commande enregistree.');
        } catch (InvalidArgumentException $e) {
            $this->messenger->postEphemeral($proposal->lunchDay->provider_channel_id, $userId, $e->getMessage());
        }

        return response('', 200);
    }

    private function handleOrderEdit(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if ($proposal->lunchDay->status === LunchDayStatus::Closed) {
            $this->messenger->postEphemeral($proposal->lunchDay->provider_channel_id, $userId, 'La journee est cloturee.');

            return response('', 200);
        }

        $order = Order::query()
            ->where('lunch_day_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();
        if (! $order) {
            return response('', 200);
        }

        $allowFinal = $this->isRunnerOrOrderer($proposal, $userId) || $this->messenger->isAdmin($userId);
        if ($proposal->lunchDay->status !== LunchDayStatus::Open && ! $allowFinal) {
            $this->messenger->postEphemeral($proposal->lunchDay->provider_channel_id, $userId, 'Les commandes sont verrouillees.');

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
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
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
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (! $proposal) {
            return response('', 200);
        }

        if ($proposal->lunchDay->status === LunchDayStatus::Closed) {
            return response('', 200);
        }

        if (! $this->isRunnerOrOrderer($proposal, $userId) && ! $this->messenger->isAdmin($userId)) {
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
        if (! $order || $order->lunch_day_proposal_id !== $proposal->id) {
            return response('', 200);
        }

        $this->adjustOrderPrice->handle($order, $priceFinal, $userId);
        $this->messenger->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Prix final mis a jour.');

        return response('', 200);
    }

    private function ensureDayOpen(?LunchDay $day, string $channelId, string $userId): bool
    {
        if (! $day) {
            return false;
        }

        if ($day->status !== LunchDayStatus::Open) {
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

    private function canManageEnseigne(Enseigne $enseigne, string $userId): bool
    {
        if ($enseigne->created_by_provider_user_id === $userId) {
            return true;
        }

        return $this->messenger->isAdmin($userId);
    }

    private function canCloseDay(LunchDay $day, string $userId): bool
    {
        if ($this->messenger->isAdmin($userId)) {
            return true;
        }

        return LunchDayProposal::query()
            ->where('lunch_day_id', $day->id)
            ->where(function ($query) use ($userId) {
                $query->where('runner_user_id', $userId)
                    ->orWhere('orderer_user_id', $userId);
            })
            ->exists();
    }

    private function isRunnerOrOrderer(LunchDayProposal $proposal, string $userId): bool
    {
        return $proposal->runner_user_id === $userId || $proposal->orderer_user_id === $userId;
    }

    private function roleForUser(LunchDayProposal $proposal, string $userId): ?string
    {
        if ($proposal->runner_user_id === $userId) {
            return 'runner';
        }
        if ($proposal->orderer_user_id === $userId) {
            return 'orderer';
        }

        return null;
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
        $dayId = $metadata['lunch_day_id'] ?? null;
        $channelId = config('lunch.channel_id');
        $threadTs = null;

        if ($dayId) {
            $day = LunchDay::find($dayId);
            if ($day) {
                $channelId = $day->provider_channel_id;
                $threadTs = $day->provider_message_ts;
            }
        }

        if ($channelId) {
            $this->messenger->postEphemeral($channelId, $userId, $message, $threadTs);
        }
    }
}
