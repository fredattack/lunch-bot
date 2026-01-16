<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\LunchDayStatus;
use App\Enums\ProposalStatus;
use App\Models\Enseigne;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SlackInteractionService
{
    public function __construct(
        private readonly SlackService $slack,
        private readonly SlackBlockBuilder $blocks,
        private readonly LunchManager $lunchManager,
    ) {
    }

    public function handleEvent(array $payload): void
    {
        // MVP: no event processing needed beyond verification.
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
                if (!$day || !$this->ensureDayOpen($day, $channelId, $userId)) {
                    return;
                }
                $enseignes = Enseigne::query()->where('active', true)->orderBy('name')->get()->all();
                if (empty($enseignes)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Aucune enseigne active pour le moment.');
                    return;
                }
                $view = $this->blocks->proposalModal($day, $enseignes);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::OPEN_ADD_ENSEIGNE_MODAL:
                $day = LunchDay::find($value);
                $metadata = $day ? ['lunch_day_id' => $day->id] : [];
                $view = $this->blocks->addEnseigneModal($metadata);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::CLOSE_DAY:
                $day = LunchDay::find($value);
                if (!$day) {
                    return;
                }
                if (!$this->canCloseDay($day, $userId)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Seul le runner/orderer ou un admin peut cloturer.');
                    return;
                }
                $this->lunchManager->closeDay($day);
                $this->lunchManager->postClosureSummary($day);
                return;
            case SlackActions::CLAIM_RUNNER:
            case SlackActions::CLAIM_ORDERER:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal || !$this->ensureDayOpen($proposal->lunchDay, $channelId, $userId)) {
                    return;
                }
                $roleField = $actionId === SlackActions::CLAIM_RUNNER ? 'runner_user_id' : 'orderer_user_id';
                $assigned = $this->assignRole($proposal, $roleField, $userId);
                if (!$assigned) {
                    $this->slack->postEphemeral($channelId, $userId, 'Role deja attribue.');
                }
                return;
            case SlackActions::OPEN_ORDER_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal || !$this->ensureDayOpen($proposal->lunchDay, $channelId, $userId)) {
                    return;
                }
                $view = $this->blocks->orderModal($proposal, null, false, false);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::OPEN_EDIT_ORDER_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal) {
                    return;
                }
                $order = Order::query()
                    ->where('lunch_day_proposal_id', $proposal->id)
                    ->where('slack_user_id', $userId)
                    ->first();
                if (!$order) {
                    $this->slack->postEphemeral($channelId, $userId, 'Aucune commande a modifier.');
                    return;
                }
                $allowFinal = $this->isRunnerOrOrderer($proposal, $userId) || $this->slack->isAdmin($userId);
                $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::OPEN_SUMMARY:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal) {
                    return;
                }
                if (!$this->isRunnerOrOrderer($proposal, $userId) && !$this->slack->isAdmin($userId)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut voir le recapitulatif.');
                    return;
                }
                $this->lunchManager->postSummary($proposal);
                return;
            case SlackActions::OPEN_DELEGATE_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal) {
                    return;
                }
                $role = $this->roleForUser($proposal, $userId);
                if (!$role) {
                    $this->slack->postEphemeral($channelId, $userId, 'Vous n\'avez pas de role a deleguer.');
                    return;
                }
                $view = $this->blocks->delegateModal($proposal, $role);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::OPEN_ADJUST_PRICE_MODAL:
                $proposal = LunchDayProposal::with('lunchDay')->find($value);
                if (!$proposal) {
                    return;
                }
                if (!$this->isRunnerOrOrderer($proposal, $userId) && !$this->slack->isAdmin($userId)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut ajuster les prix.');
                    return;
                }
                $orders = $proposal->orders()->orderBy('created_at')->get()->all();
                if (empty($orders)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Aucune commande a ajuster.');
                    return;
                }
                $view = $this->blocks->adjustPriceModal($proposal, $orders);
                $this->slack->openModal($triggerId, $view);
                return;
            case SlackActions::OPEN_MANAGE_ENSEIGNE_MODAL:
                $proposal = LunchDayProposal::with('enseigne')->find($value);
                if (!$proposal) {
                    return;
                }
                $enseigne = $proposal->enseigne;
                if (!$this->canManageEnseigne($enseigne, $userId)) {
                    $this->slack->postEphemeral($channelId, $userId, 'Vous ne pouvez pas modifier cette enseigne.');
                    return;
                }
                $metadata = $proposal->lunch_day_id ? ['lunch_day_id' => $proposal->lunch_day_id] : [];
                $view = $this->blocks->editEnseigneModal($enseigne, $metadata);
                $this->slack->openModal($triggerId, $view);
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
        if (!$day) {
            return response('', 200);
        }

        if ($day->status !== LunchDayStatus::Open) {
            $this->slack->postEphemeral($day->slack_channel_id, $userId, 'Les commandes sont verrouillees.');
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $enseigneId = $this->stateValue($state, 'enseigne', 'enseigne_id');
        $fulfillment = $this->stateValue($state, 'fulfillment', 'fulfillment_type');
        $platform = $this->stateValue($state, 'platform', 'platform');

        if ($fulfillment && !in_array($fulfillment, [FulfillmentType::Pickup->value, FulfillmentType::Delivery->value], true)) {
            return $this->viewErrorResponse(['fulfillment' => 'Type invalide.']);
        }

        $enseigne = Enseigne::query()->where('active', true)->find($enseigneId);
        if (!$enseigne) {
            return $this->viewErrorResponse(['enseigne' => 'Enseigne invalide.']);
        }

        $existing = LunchDayProposal::query()
            ->where('lunch_day_id', $day->id)
            ->where('enseigne_id', $enseigne->id)
            ->first();

        if ($existing) {
            $this->slack->postEphemeral($day->slack_channel_id, $userId, 'Cette enseigne est deja proposee.');
            return response('', 200);
        }

        $proposal = LunchDayProposal::create([
            'lunch_day_id' => $day->id,
            'enseigne_id' => $enseigne->id,
            'fulfillment_type' => $fulfillment ?: FulfillmentType::Pickup->value,
            'platform' => $platform ?: null,
            'status' => ProposalStatus::Open,
            'created_by_slack_user_id' => $userId,
        ]);

        $proposal->setRelation('lunchDay', $day);
        $proposal->setRelation('enseigne', $enseigne);
        $this->lunchManager->postProposalMessage($proposal);

        return response('', 200);
    }

    private function handleEnseigneCreate(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $name = $this->stateValue($state, 'name', 'name');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (!$name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        Enseigne::create([
            'name' => $name,
            'url_menu' => $urlMenu ?: null,
            'notes' => $notes ?: null,
            'active' => true,
            'created_by_slack_user_id' => $userId,
        ]);

        $this->postOptionalFeedback($payload, $userId, 'Enseigne ajoutee.');

        return response('', 200);
    }

    private function handleEnseigneUpdate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $enseigne = Enseigne::find($metadata['enseigne_id'] ?? null);
        if (!$enseigne) {
            return response('', 200);
        }

        if (!$this->canManageEnseigne($enseigne, $userId)) {
            $this->postOptionalFeedback($payload, $userId, 'Vous ne pouvez pas modifier cette enseigne.');
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $name = $this->stateValue($state, 'name', 'name');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $notes = $this->stateValue($state, 'notes', 'notes');
        $active = $this->stateValue($state, 'active', 'active');

        if (!$name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        $enseigne->fill([
            'name' => $name,
            'url_menu' => $urlMenu ?: null,
            'notes' => $notes ?: null,
        ]);

        if ($active !== null) {
            $enseigne->active = $active === '1';
        }

        $enseigne->save();

        $this->postOptionalFeedback($payload, $userId, 'Enseigne mise a jour.');

        return response('', 200);
    }

    private function handleOrderCreate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (!$proposal) {
            return response('', 200);
        }

        if (!$this->ensureDayOpen($proposal->lunchDay, $proposal->lunchDay->slack_channel_id, $userId)) {
            return response('', 200);
        }

        $state = $payload['view']['state']['values'] ?? [];
        $data = $this->orderStateData($state, false);
        if ($data instanceof Response) {
            return $data;
        }

        $order = Order::query()
            ->where('lunch_day_proposal_id', $proposal->id)
            ->where('slack_user_id', $userId)
            ->first();

        if ($order) {
            $this->applyOrderUpdate($order, $data, $userId);
        } else {
            $order = Order::create([
                'lunch_day_proposal_id' => $proposal->id,
                'slack_user_id' => $userId,
                'description' => $data['description'],
                'price_estimated' => $data['price_estimated'],
                'notes' => $data['notes'],
                'audit_log' => [[
                    'at' => now()->toIso8601String(),
                    'by' => $userId,
                    'changes' => ['created' => true],
                ]],
            ]);
        }

        $proposal->load('lunchDay');
        $this->lunchManager->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Commande enregistree.');

        return response('', 200);
    }

    private function handleOrderEdit(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (!$proposal) {
            return response('', 200);
        }

        if ($proposal->lunchDay->status === LunchDayStatus::Closed) {
            $this->slack->postEphemeral($proposal->lunchDay->slack_channel_id, $userId, 'La journee est cloturee.');
            return response('', 200);
        }

        $order = Order::query()
            ->where('lunch_day_proposal_id', $proposal->id)
            ->where('slack_user_id', $userId)
            ->first();
        if (!$order) {
            return response('', 200);
        }

        $allowFinal = $this->isRunnerOrOrderer($proposal, $userId) || $this->slack->isAdmin($userId);
        if ($proposal->lunchDay->status !== LunchDayStatus::Open && !$allowFinal) {
            $this->slack->postEphemeral($proposal->lunchDay->slack_channel_id, $userId, 'Les commandes sont verrouillees.');
            return response('', 200);
        }
        $state = $payload['view']['state']['values'] ?? [];
        $data = $this->orderStateData($state, $allowFinal);
        if ($data instanceof Response) {
            return $data;
        }

        $this->applyOrderUpdate($order, $data, $userId);
        $this->lunchManager->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Commande mise a jour.');

        return response('', 200);
    }

    private function handleRoleDelegate(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        $role = $metadata['role'] ?? null;
        if (!$proposal || !$role) {
            return response('', 200);
        }

        $newUserId = $this->stateValue($payload['view']['state']['values'] ?? [], 'delegate', 'user_id');
        if (!$newUserId) {
            return response('', 200);
        }

        if ($role === 'runner' && $proposal->runner_user_id !== $userId) {
            $this->postOptionalFeedback($payload, $userId, 'Vous n\'etes pas runner.');
            return response('', 200);
        }

        if ($role === 'orderer' && $proposal->orderer_user_id !== $userId) {
            $this->postOptionalFeedback($payload, $userId, 'Vous n\'etes pas orderer.');
            return response('', 200);
        }

        $field = $role === 'runner' ? 'runner_user_id' : 'orderer_user_id';
        $proposal->{$field} = $newUserId;
        $proposal->save();
        $this->lunchManager->updateProposalMessage($proposal);

        if ($proposal->lunchDay?->slack_message_ts) {
            $this->slack->postMessage(
                $proposal->lunchDay->slack_channel_id,
                'Role delegue',
                [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => sprintf('Role %s transfere de <@%s> a <@%s>.', $role, $userId, $newUserId),
                        ],
                    ],
                ],
                $proposal->lunchDay->slack_message_ts
            );
        }

        return response('', 200);
    }

    private function handleAdjustPrice(array $payload, string $userId): Response
    {
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $proposal = LunchDayProposal::with('lunchDay')->find($metadata['proposal_id'] ?? null);
        if (!$proposal) {
            return response('', 200);
        }

        if ($proposal->lunchDay->status === LunchDayStatus::Closed) {
            return response('', 200);
        }

        if (!$this->isRunnerOrOrderer($proposal, $userId) && !$this->slack->isAdmin($userId)) {
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
        if (!$order || $order->lunch_day_proposal_id !== $proposal->id) {
            return response('', 200);
        }

        $this->applyOrderUpdate($order, ['price_final' => $priceFinal], $userId);
        $this->lunchManager->updateProposalMessage($proposal);
        $this->postOptionalFeedback($payload, $userId, 'Prix final mis a jour.');

        return response('', 200);
    }

    private function assignRole(LunchDayProposal $proposal, string $field, string $userId): bool
    {
        return (bool) DB::transaction(function () use ($proposal, $field, $userId) {
            $locked = LunchDayProposal::query()->whereKey($proposal->id)->lockForUpdate()->first();
            if (!$locked || $locked->{$field}) {
                return false;
            }

            $locked->{$field} = $userId;
            $locked->status = ProposalStatus::Ordering;
            $locked->save();

            $proposal->refresh();
            $this->lunchManager->updateProposalMessage($proposal);

            return true;
        });
    }

    private function ensureDayOpen(?LunchDay $day, string $channelId, string $userId): bool
    {
        if (!$day) {
            return false;
        }

        if ($day->status !== LunchDayStatus::Open) {
            $this->slack->postEphemeral($channelId, $userId, 'Les commandes sont verrouillees.');
            return false;
        }

        return true;
    }

    private function orderStateData(array $state, bool $allowFinal): array|Response
    {
        $description = $this->stateValue($state, 'description', 'description');
        $priceEstimatedRaw = $this->stateValue($state, 'price_estimated', 'price_estimated');
        $notes = $this->stateValue($state, 'notes', 'notes');

        if (!$description) {
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

    private function applyOrderUpdate(Order $order, array $data, string $actorId): void
    {
        $changes = [];
        foreach ($data as $key => $value) {
            $current = $order->{$key};
            if (is_float($value) && $current !== null) {
                $current = (float) $current;
            }
            if ($current !== $value) {
                $changes[$key] = ['from' => $order->{$key}, 'to' => $value];
            }
        }

        $order->fill($data);
        $this->appendAuditLog($order, $actorId, $changes);
        $order->save();
    }

    private function appendAuditLog(Order $order, string $actorId, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $log = $order->audit_log ?? [];
        $log[] = [
            'at' => now()->toIso8601String(),
            'by' => $actorId,
            'changes' => $changes,
        ];
        $order->audit_log = $log;
    }

    private function canManageEnseigne(Enseigne $enseigne, string $userId): bool
    {
        if ($enseigne->created_by_slack_user_id === $userId) {
            return true;
        }

        return $this->slack->isAdmin($userId);
    }

    private function canCloseDay(LunchDay $day, string $userId): bool
    {
        if ($this->slack->isAdmin($userId)) {
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
        if (!is_numeric($normalized)) {
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
                $channelId = $day->slack_channel_id;
                $threadTs = $day->slack_message_ts;
            }
        }

        if ($channelId) {
            $this->slack->postEphemeral($channelId, $userId, $message, [], $threadTs);
        }
    }
}
