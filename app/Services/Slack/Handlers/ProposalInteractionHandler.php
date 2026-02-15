<?php

namespace App\Services\Slack\Handlers;

use App\Actions\VendorProposal\AssignRole;
use App\Actions\VendorProposal\DelegateRole;
use App\Actions\VendorProposal\ProposeRestaurant;
use App\Actions\VendorProposal\ProposeVendor;
use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Symfony\Component\HttpFoundation\Response;

class ProposalInteractionHandler extends BaseInteractionHandler
{
    public function __construct(
        SlackService $slack,
        SlackMessenger $messenger,
        SlackBlockBuilder $blocks,
        private readonly ProposeVendor $proposeVendor,
        private readonly ProposeRestaurant $proposeRestaurant,
        private readonly AssignRole $assignRole,
        private readonly DelegateRole $delegateRole
    ) {
        parent::__construct($slack, $messenger, $blocks);
    }

    public function handleBlockAction(string $actionId, string $value, string $userId, string $triggerId, string $channelId): void
    {
        match ($actionId) {
            SlackAction::DashboardStartFromCatalog->value,
            SlackAction::DashboardRelaunch->value => $this->startFromCatalog($value, $userId, $triggerId),
            SlackAction::DashboardCreateProposal->value => $this->createProposal($value, $userId, $triggerId),
            SlackAction::OpenProposalModal->value => $this->openProposalModal($value, $userId, $channelId, $triggerId),
            SlackAction::DashboardChooseFavorite->value => $this->chooseFavorite($value, $userId, $triggerId),
            SlackAction::DashboardProposeVendor->value => $this->proposeVendorModal($value, $userId, $triggerId),
            SlackAction::ProposalOpenManage->value => $this->openManage($value, $userId, $triggerId),
            SlackAction::ProposalTakeCharge->value => $this->takeCharge($value, $userId, $triggerId, $channelId),
            SlackAction::ProposalOpenRecap->value => $this->openRecap($value, $userId, $triggerId),
            SlackAction::ProposalClose->value => $this->closeProposal($value, $userId, $channelId),
            SlackAction::ClaimRunner->value,
            SlackAction::ClaimOrderer->value => $this->claimRole($actionId, $value, $userId, $channelId),
            SlackAction::OpenDelegateModal->value => $this->openDelegateModal($value, $userId, $channelId, $triggerId),
            SlackAction::OpenAdjustPriceModal->value => $this->openAdjustPriceModal($value, $userId, $channelId, $triggerId),
            SlackAction::OpenSummary->value => $this->openSummary($value, $userId, $channelId),
            SlackAction::DashboardClaimResponsible->value => $this->claimResponsible($value, $userId, $channelId),
            SlackAction::DashboardViewOrders->value => $this->viewOrders($value),
            default => null,
        };
    }

    public function handleProposalSubmission(array $payload, string $userId): Response
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

    public function handleRestaurantPropose(array $payload, string $userId): Response
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
        $files = $this->stateFiles($state, 'file', 'file_upload');

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

        if (! empty($files)) {
            $this->processFileUpload($proposal->vendor, $files[0]);
        }

        $proposal->load(['lunchSession', 'vendor']);

        $orderModal = $this->blocks->orderModal($proposal, null, false, false);

        return $this->viewUpdateResponse($orderModal);
    }

    public function handleRoleDelegate(array $payload, string $userId): Response
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

    private function startFromCatalog(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function createProposal(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function openProposalModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
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
    }

    private function chooseFavorite(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function proposeVendorModal(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function openManage(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function takeCharge(string $value, string $userId, string $triggerId, string $channelId): void
    {
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
    }

    private function openRecap(string $value, string $userId, string $triggerId): void
    {
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
    }

    private function closeProposal(string $value, string $userId, string $channelId): void
    {
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
    }

    private function claimRole(string $actionId, string $value, string $userId, string $channelId): void
    {
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
    }

    private function openDelegateModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $role = $proposal->getRoleFor($userId);
        if (! $role) {
            $this->messenger->postEphemeral($channelId, $userId, 'Vous n\'avez pas de role a deleguer.');

            return;
        }

        $view = $this->blocks->delegateModal($proposal, $role);
        $this->messenger->openModal($triggerId, $view);
    }

    private function openAdjustPriceModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
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
    }

    private function openSummary(string $value, string $userId, string $channelId): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        if (! $this->canManageFinalPrices($proposal, $userId)) {
            $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner/orderer peut voir le recapitulatif.');

            return;
        }

        $this->messenger->postSummary($proposal);
    }

    private function claimResponsible(string $value, string $userId, string $channelId): void
    {
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
    }

    private function viewOrders(string $value): void
    {
        $proposal = VendorProposal::with('lunchSession')->find($value);
        if (! $proposal) {
            return;
        }

        $this->messenger->postSummary($proposal);
    }
}
