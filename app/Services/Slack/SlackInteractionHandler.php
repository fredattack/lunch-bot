<?php

namespace App\Services\Slack;

use App\Actions\LunchSession\CloseLunchSession;
use App\Actions\LunchSession\CreateLunchSession;
use App\Actions\Order\CreateOrder;
use App\Actions\Order\DeleteOrder;
use App\Actions\Order\UpdateOrder;
use App\Actions\Vendor\CreateVendor;
use App\Actions\Vendor\UpdateVendor;
use App\Actions\VendorProposal\AssignRole;
use App\Actions\VendorProposal\DelegateRole;
use App\Actions\VendorProposal\ProposeRestaurant;
use App\Actions\VendorProposal\ProposeVendor;
use App\Authorization\Actor;
use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class SlackInteractionHandler
{
    public function __construct(
        private readonly SlackService $slack,
        private readonly SlackMessenger $messenger,
        private readonly SlackBlockBuilder $blocks,
        private readonly DashboardBlockBuilder $dashboardBlocks,
        private readonly DashboardStateResolver $stateResolver,
        private readonly CloseLunchSession $closeLunchSession,
        private readonly CreateLunchSession $createLunchSession,
        private readonly ProposeVendor $proposeVendor,
        private readonly ProposeRestaurant $proposeRestaurant,
        private readonly AssignRole $assignRole,
        private readonly DelegateRole $delegateRole,
        private readonly CreateOrder $createOrder,
        private readonly UpdateOrder $updateOrder,
        private readonly DeleteOrder $deleteOrder,
        private readonly CreateVendor $createVendor,
        private readonly UpdateVendor $updateVendor
    ) {}

    public function handleEvent(array $payload): void
    {
        Log::info('Slack event received.', ['type' => $payload['type'] ?? null]);
    }

    public function handleLunchDashboard(string $userId, string $channelId, string $triggerId, ?string $dateOverride = null): void
    {
        $timezone = config('lunch.timezone', 'Europe/Paris');
        $date = $dateOverride ?? Carbon::now($timezone)->toDateString();
        $deadlineTime = config('lunch.deadline_time', '11:30');
        $deadlineAt = Carbon::parse("{$date} {$deadlineTime}", $timezone);

        $session = $this->createLunchSession->handle($date, $channelId, $deadlineAt);
        $isAdmin = $this->messenger->isAdmin($userId);

        $context = $this->stateResolver->resolve($session, $userId, $isAdmin);
        $view = $this->dashboardBlocks->buildModal($context);

        $this->messenger->openModal($triggerId, $view);
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
            // New dashboard actions (manifeste)
            case SlackAction::DashboardStartFromCatalog->value:
            case SlackAction::DashboardRelaunch->value:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                $sessionChannel = $session->provider_channel_id;
                if (! $this->ensureSessionOpen($session, $sessionChannel, $userId)) {
                    return;
                }
                $vendors = Vendor::query()
                    ->where('organization_id', $session->organization_id)
                    ->where('active', true)
                    ->orderBy('name')
                    ->get()
                    ->all();
                if (empty($vendors)) {
                    $view = $this->blocks->proposeRestaurantModal($session);
                    $this->messenger->pushModal($triggerId, $view);

                    return;
                }
                $view = $this->blocks->proposalModal($session, $vendors);
                $this->messenger->pushModal($triggerId, $view);

                return;

            case SlackAction::DashboardCreateProposal->value:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                $sessionChannel = $session->provider_channel_id;
                if (! $this->ensureSessionOpen($session, $sessionChannel, $userId)) {
                    return;
                }
                $view = $this->blocks->proposeRestaurantModal($session);
                $this->messenger->pushModal($triggerId, $view);

                return;

            case SlackAction::DashboardJoinProposal->value:
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

                return;

            case SlackAction::OpenOrderForProposal->value:
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

                return;

            case SlackAction::OpenLunchDashboard->value:
                $this->handleLunchDashboard($userId, $channelId, $triggerId, $value ?: null);

                return;

            case SlackAction::OrderOpenEdit->value:
                $order = Order::with('proposal.lunchSession')->find($value);
                if (! $order) {
                    return;
                }
                $proposal = $order->proposal;
                $allowFinal = $this->canManageFinalPrices($proposal, $userId);
                $view = $this->blocks->orderModal($proposal, $order, $allowFinal, true);
                $this->messenger->pushModal($triggerId, $view);

                return;

            case SlackAction::OrderDelete->value:
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

                return;

            case SlackAction::ProposalOpenManage->value:
                $proposal = VendorProposal::with(['lunchSession', 'vendor', 'orders'])->find($value);
                if (! $proposal) {
                    return;
                }
                $sessionChannel = $proposal->lunchSession->provider_channel_id;
                if (! $this->ensureSessionOpen($proposal->lunchSession, $sessionChannel, $userId)) {
                    return;
                }
                $view = $this->blocks->proposalManageModal($proposal, $userId);
                $this->messenger->pushModal($triggerId, $view);

                return;

            case SlackAction::ProposalTakeCharge->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                $sessionChannel = $proposal->lunchSession->provider_channel_id;
                if (! $this->ensureSessionOpen($proposal->lunchSession, $sessionChannel, $userId)) {
                    return;
                }
                $isPickup = $proposal->fulfillment_type === FulfillmentType::Pickup;
                $role = $isPickup ? 'runner' : 'orderer';
                $assigned = $this->assignRole->handle($proposal, $role, $userId);
                if ($assigned) {
                    $this->messenger->updateProposalMessage($proposal);
                    $roleLabel = $isPickup ? 'runner' : 'orderer';
                    $this->messenger->postEphemeral($sessionChannel, $userId, "Vous etes maintenant {$roleLabel} pour cette commande.");
                } else {
                    $this->messenger->postEphemeral($sessionChannel, $userId, 'Un responsable est deja assigne.');
                }

                return;

            case SlackAction::ProposalOpenRecap->value:
                $proposal = VendorProposal::with(['lunchSession', 'orders', 'vendor'])->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->canManageFinalPrices($proposal, $userId)) {
                    $sessionChannel = $proposal->lunchSession->provider_channel_id;
                    $this->messenger->postEphemeral($sessionChannel, $userId, 'Seul le responsable peut voir le recapitulatif.');

                    return;
                }
                $orders = $proposal->orders;
                $estimated = $orders->sum('price_estimated');
                $final = $orders->sum(fn (Order $o) => $o->price_final ?? $o->price_estimated);
                $view = $this->blocks->recapModal($proposal, $orders->all(), [
                    'estimated' => number_format((float) $estimated, 2),
                    'final' => number_format((float) $final, 2),
                ]);
                $this->messenger->pushModal($triggerId, $view);

                return;

            case SlackAction::ProposalClose->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                if (! $this->canManageFinalPrices($proposal, $userId)) {
                    $sessionChannel = $proposal->lunchSession->provider_channel_id;
                    $this->messenger->postEphemeral($sessionChannel, $userId, 'Seul le responsable peut cloturer.');

                    return;
                }
                $proposal->update(['status' => ProposalStatus::Closed]);
                $this->messenger->updateProposalMessage($proposal);

                return;

            case SlackAction::SessionClose->value:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                if (! $this->canCloseSession($session, $userId)) {
                    $this->messenger->postEphemeral($channelId, $userId, 'Seul le responsable ou un admin peut cloturer.');

                    return;
                }
                $this->closeLunchSession->handle($session);
                $this->messenger->postClosureSummary($session);

                return;

                // Legacy block actions
            case SlackAction::OpenProposalModal->value:
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
            case SlackAction::OpenAddEnseigneModal->value:
                $session = LunchSession::find($value);
                $metadata = $session ? ['lunch_session_id' => $session->id] : [];
                $view = $this->blocks->addVendorModal($metadata);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackAction::CloseDay->value:
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
            case SlackAction::ClaimRunner->value:
            case SlackAction::ClaimOrderer->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
                    return;
                }
                $role = $actionId === SlackAction::ClaimRunner->value ? 'runner' : 'orderer';
                $assigned = $this->assignRole->handle($proposal, $role, $userId);
                if ($assigned) {
                    $this->messenger->updateProposalMessage($proposal);
                } else {
                    $this->messenger->postEphemeral($channelId, $userId, 'Role deja attribue.');
                }

                return;
            case SlackAction::OpenOrderModal->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
                    return;
                }
                $view = $this->blocks->orderModal($proposal, null, false, false);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackAction::OpenEditOrderModal->value:
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
            case SlackAction::OpenSummary->value:
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
            case SlackAction::OpenDelegateModal->value:
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
            case SlackAction::OpenAdjustPriceModal->value:
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
            case SlackAction::OpenManageEnseigneModal->value:
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
            case SlackAction::DashboardProposeVendor->value:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                $sessionChannel = $session->provider_channel_id;
                if (! $this->ensureSessionOpen($session, $sessionChannel, $userId)) {
                    return;
                }
                $view = $this->blocks->proposeRestaurantModal($session);
                $this->messenger->openModal($triggerId, $view);

                return;
            case SlackAction::DashboardChooseFavorite->value:
                $session = LunchSession::find($value);
                if (! $session) {
                    return;
                }
                $sessionChannel = $session->provider_channel_id;
                if (! $this->ensureSessionOpen($session, $sessionChannel, $userId)) {
                    return;
                }
                $vendors = Vendor::query()
                    ->where('organization_id', $session->organization_id)
                    ->where('active', true)
                    ->orderBy('name')
                    ->get()
                    ->all();
                if (empty($vendors)) {
                    $this->messenger->postEphemeral($sessionChannel, $userId, 'Aucun favori enregistre pour le moment.');

                    return;
                }
                $view = $this->blocks->proposalModal($session, $vendors);
                $this->messenger->pushModal($triggerId, $view);

                return;
            case SlackAction::DashboardOrderHere->value:
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

                return;
            case SlackAction::DashboardClaimResponsible->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal || ! $this->ensureSessionOpen($proposal->lunchSession, $channelId, $userId)) {
                    return;
                }
                $assigned = $this->assignRole->handle($proposal, 'runner', $userId);
                if ($assigned) {
                    $this->messenger->updateProposalMessage($proposal);
                } else {
                    $this->messenger->postEphemeral($channelId, $userId, 'Role deja attribue.');
                }

                return;
            case SlackAction::DashboardViewOrders->value:
                $proposal = VendorProposal::with('lunchSession')->find($value);
                if (! $proposal) {
                    return;
                }
                $this->messenger->postSummary($proposal);

                return;
            case SlackAction::DashboardMyOrder->value:
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

                return;
            case SlackAction::DashboardCloseSession->value:
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

            case SlackAction::DevResetDatabase->value:
                if ($userId !== 'U08E9Q2KJGY') {
                    return;
                }
                Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
                $this->messenger->postEphemeral($channelId, $userId, 'Base de donnees reinitialisee avec succes.');

                return;

            case SlackAction::DevExportVendors->value:
                if ($userId !== 'U08E9Q2KJGY') {
                    return;
                }
                $vendors = Vendor::with('media')->get()->map(function (Vendor $vendor) {
                    return [
                        'id' => $vendor->id,
                        'name' => $vendor->name,
                        'cuisine_type' => $vendor->cuisine_type,
                        'fulfillment_types' => $vendor->fulfillment_types,
                        'allow_individual_order' => $vendor->allow_individual_order,
                        'url_website' => $vendor->url_website,
                        'url_menu' => $vendor->url_menu,
                        'notes' => $vendor->notes,
                        'active' => $vendor->active,
                        'media' => $vendor->media->map(fn ($m) => [
                            'collection' => $m->collection_name,
                            'file_name' => $m->file_name,
                            'mime_type' => $m->mime_type,
                            'url' => $m->getUrl(),
                        ])->toArray(),
                    ];
                })->toArray();

                $json = json_encode($vendors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $view = $this->blocks->vendorExportModal($json);
                $this->messenger->pushModal($triggerId, $view);

                return;

            default:
                return;
        }
    }

    private function handleViewSubmission(array $payload): Response
    {
        $callbackId = $payload['view']['callback_id'] ?? '';
        $userId = $payload['user']['id'] ?? '';

        try {
            return match ($callbackId) {
                SlackAction::CallbackProposalCreate->value, 'proposal.create' => $this->handleProposalSubmission($payload, $userId),
                SlackAction::CallbackRestaurantPropose->value, 'restaurant.propose' => $this->handleRestaurantPropose($payload, $userId),
                SlackAction::CallbackEnseigneCreate->value, 'enseigne.create' => $this->handleVendorCreate($payload, $userId),
                SlackAction::CallbackEnseigneUpdate->value, 'enseigne.update' => $this->handleVendorUpdate($payload, $userId),
                SlackAction::CallbackOrderCreate->value, 'order.create' => $this->handleOrderCreate($payload, $userId),
                SlackAction::CallbackOrderEdit->value, 'order.edit' => $this->handleOrderEdit($payload, $userId),
                SlackAction::CallbackRoleDelegate->value, 'role.delegate' => $this->handleRoleDelegate($payload, $userId),
                SlackAction::CallbackOrderAdjustPrice->value, 'order.adjust_price' => $this->handleAdjustPrice($payload, $userId),
                default => response('', 200),
            };
        } catch (InvalidArgumentException $e) {
            Log::warning('Slack view submission business error', ['message' => $e->getMessage()]);

            return $this->viewUpdateResponse(
                $this->blocks->errorModal('Erreur', $e->getMessage())
            );
        } catch (\Throwable $e) {
            Log::error('Slack view submission error', ['exception' => $e->getMessage()]);

            return $this->viewUpdateResponse(
                $this->blocks->errorModal('Erreur', 'Une erreur est survenue. Veuillez reessayer.')
            );
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
        $deadlineTime = $this->stateValue($state, 'deadline', 'deadline_time') ?: '11:30';
        $note = $this->stateValue($state, 'note', 'note');
        $helpRequested = $this->stateCheckboxHasValue($state, 'help', 'help_requested', 'help_requested');

        if ($fulfillment && ! in_array($fulfillment, [FulfillmentType::Pickup->value, FulfillmentType::Delivery->value], true)) {
            return $this->viewErrorResponse(['fulfillment' => 'Type invalide.']);
        }

        $vendor = Vendor::query()->where('active', true)->find($vendorId);
        if (! $vendor) {
            return $this->viewErrorResponse(['enseigne' => 'Enseigne invalide.']);
        }

        $proposal = $this->proposeVendor->handle(
            $session,
            $vendor,
            FulfillmentType::from($fulfillment ?: FulfillmentType::Pickup->value),
            $userId,
            $deadlineTime,
            $note ?: null,
            $helpRequested
        );

        $proposal->setRelation('lunchSession', $session);
        $proposal->setRelation('vendor', $vendor);

        $orderModal = $this->blocks->orderModal($proposal, null, false, false);

        return $this->viewUpdateResponse($orderModal);
    }

    private function handleRestaurantPropose(array $payload, string $userId): Response
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
        $name = $this->stateValue($state, 'name', 'name');
        $urlWebsite = $this->stateValue($state, 'url_website', 'url_website');
        $fulfillmentTypes = $this->stateCheckboxValues($state, 'fulfillment_types', 'fulfillment_types');
        $allowIndividualOrder = $this->stateCheckboxHasValue($state, 'allow_individual', 'allow_individual_order', 'allow_individual');
        $deadlineTime = $this->stateValue($state, 'deadline', 'deadline_time') ?: '11:30';
        $note = $this->stateValue($state, 'note', 'note');
        $helpRequested = $this->stateCheckboxHasValue($state, 'help', 'help_requested', 'help_requested');
        $fileIds = $this->stateFileIds($state, 'file', 'file_upload');

        if (! $name) {
            return $this->viewErrorResponse(['name' => 'Nom du restaurant requis.']);
        }

        if (empty($fulfillmentTypes)) {
            return $this->viewErrorResponse(['fulfillment_types' => 'Au moins un type doit etre selectionne.']);
        }

        $proposal = $this->proposeRestaurant->handle(
            $session,
            [
                'name' => $name,
                'url_website' => $urlWebsite ?: null,
                'fulfillment_types' => $fulfillmentTypes,
                'allow_individual_order' => $allowIndividualOrder,
            ],
            $userId,
            $deadlineTime,
            $note ?: null,
            $helpRequested
        );

        if (! empty($fileIds)) {
            $this->processFileUpload($proposal->vendor, $fileIds[0]);
        }

        $proposal->load(['lunchSession', 'vendor']);

        $orderModal = $this->blocks->orderModal($proposal, null, false, false);

        return $this->viewUpdateResponse($orderModal);
    }

    private function processFileUpload(Vendor $vendor, string $fileId): void
    {
        $fileInfo = $this->slack->getFileInfo($fileId);
        if (! $fileInfo) {
            return;
        }

        $urlPrivate = $fileInfo['url_private'] ?? null;
        $mimetype = $fileInfo['mimetype'] ?? '';
        $filename = $fileInfo['name'] ?? 'file';

        if (! $urlPrivate) {
            return;
        }

        $tempPath = $this->slack->downloadFile($urlPrivate);
        if (! $tempPath) {
            return;
        }

        $collection = str_starts_with($mimetype, 'image/') ? 'logo' : 'menu';

        $vendor->addMedia($tempPath)
            ->usingFileName($filename)
            ->toMediaCollection($collection);
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

        $isFirstOrderForProposal = ! $proposal->provider_message_ts
            && $proposal->orders()->count() === 0;

        if ($existingOrder) {
            $this->updateOrder->handle($existingOrder, $data, $userId);
        } else {
            $this->createOrder->handle($proposal, $userId, $data);
        }

        if ($isFirstOrderForProposal && ! $existingOrder) {
            $hasOtherOrderInSession = Order::query()
                ->whereHas('proposal', fn ($q) => $q->where('lunch_session_id', $proposal->lunch_session_id))
                ->where('provider_user_id', $userId)
                ->where('vendor_proposal_id', '!=', $proposal->id)
                ->exists();

            $this->messenger->postOrderCreatedMessage($proposal, $userId, $hasOtherOrderInSession);
        }

        $this->postOptionalFeedback($payload, $userId, 'Commande enregistree.');

        return $this->viewClearResponse();
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

    private function stateCheckboxHasValue(array $state, string $blockId, string $actionId, string $value): bool
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
    private function stateCheckboxValues(array $state, string $blockId, string $actionId): array
    {
        $selectedOptions = Arr::get($state, "{$blockId}.{$actionId}.selected_options", []);

        return array_map(fn ($option) => $option['value'] ?? '', $selectedOptions);
    }

    /**
     * @return array<string>
     */
    private function stateFileIds(array $state, string $blockId, string $actionId): array
    {
        $files = Arr::get($state, "{$blockId}.{$actionId}.files", []);

        return array_map(fn ($file) => $file['id'] ?? '', $files);
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

    private function viewUpdateResponse(array $view): Response
    {
        $payload = [
            'response_action' => 'update',
            'view' => $view,
        ];

        return response()->json($payload, 200);
    }

    private function viewClearResponse(): Response
    {
        return response()->json(['response_action' => 'clear'], 200);
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
