<?php

namespace App\Services\Slack\Handlers;

use App\Actions\Vendor\CreateVendor;
use App\Actions\Vendor\UpdateVendor;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class VendorInteractionHandler extends BaseInteractionHandler
{
    public function __construct(
        SlackService $slack,
        SlackMessenger $messenger,
        SlackBlockBuilder $blocks,
        private readonly CreateVendor $createVendor,
        private readonly UpdateVendor $updateVendor
    ) {
        parent::__construct($slack, $messenger, $blocks);
    }

    public function handleBlockAction(string $actionId, string $value, string $userId, string $triggerId, string $channelId, array $payload = []): void
    {
        match ($actionId) {
            SlackAction::OpenAddEnseigneModal->value => $this->openAddModal($value, $triggerId),
            SlackAction::OpenManageEnseigneModal->value => $this->openManageModal($value, $userId, $channelId, $triggerId),
            SlackAction::DashboardVendorsList->value => $this->vendorsList($value, $triggerId),
            SlackAction::VendorsListSearch->value => $this->vendorsListSearch($payload, $triggerId),
            SlackAction::VendorsListEdit->value => $this->vendorsListEdit($value, $triggerId, $payload),
            SlackAction::DevResetDatabase->value => $this->devResetDatabase($userId, $channelId),
            SlackAction::DevExportVendors->value => $this->devExportVendors($userId, $triggerId),
            default => null,
        };
    }

    public function handleVendorCreate(array $payload, string $userId): Response
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

    public function handleVendorUpdate(array $payload, string $userId): Response
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
        $cuisineType = $this->stateValue($state, 'cuisine_type', 'cuisine_type');
        $urlWebsite = $this->stateValue($state, 'url_website', 'url_website');
        $urlMenu = $this->stateValue($state, 'url_menu', 'url_menu');
        $fulfillmentTypes = $this->stateCheckboxValues($state, 'fulfillment_types', 'fulfillment_types');
        $allowIndividualOrder = $this->stateCheckboxHasValue($state, 'allow_individual', 'allow_individual_order', 'allow_individual');
        $notes = $this->stateValue($state, 'notes', 'notes');
        $active = $this->stateValue($state, 'active', 'active');
        $files = $this->stateFiles($state, 'file', 'file_upload');

        if (! $name) {
            return $this->viewErrorResponse(['name' => 'Nom requis.']);
        }

        if (empty($fulfillmentTypes)) {
            return $this->viewErrorResponse(['fulfillment_types' => 'Au moins un type requis.']);
        }

        $data = [
            'name' => $name,
            'cuisine_type' => $cuisineType ?: null,
            'url_website' => $urlWebsite ?: null,
            'url_menu' => $urlMenu ?: null,
            'fulfillment_types' => $fulfillmentTypes,
            'allow_individual_order' => $allowIndividualOrder,
            'notes' => $notes ?: null,
        ];

        if ($active !== null) {
            $data['active'] = $active === '1';
        }

        $this->updateVendor->handle($vendor, $data);

        if (! empty($files)) {
            $this->processFileUpload($vendor, $files[0]);
        }

        $this->postOptionalFeedback($payload, $userId, 'Enseigne mise a jour.');

        return response('', 200);
    }

    private function canManageVendor(Vendor $vendor, string $userId): bool
    {
        $actor = $this->buildActor($userId);

        return Gate::forUser($actor)->allows('update', $vendor);
    }

    private function isDevUser(string $userId): bool
    {
        $devUserId = config('slack.dev_user_id');

        if ($devUserId && $userId === $devUserId) {
            return true;
        }

        return $this->messenger->isAdmin($userId);
    }

    private function openAddModal(string $value, string $triggerId): void
    {
        $session = LunchSession::find($value);
        $metadata = $session ? ['lunch_session_id' => $session->id] : [];
        $view = $this->blocks->addVendorModal($metadata);
        $this->messenger->openModal($triggerId, $view);
    }

    private function openManageModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
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
    }

    private function vendorsList(string $value, string $triggerId): void
    {
        $session = LunchSession::find($value);
        if (! $session) {
            return;
        }

        $vendors = Vendor::query()
            ->where('organization_id', $session->organization_id)
            ->where('active', true)
            ->orderBy('name')
            ->with('media')
            ->get()
            ->all();

        $view = $this->blocks->vendorsListModal($session, $vendors);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function vendorsListSearch(array $payload, string $triggerId): void
    {
        $state = $payload['view']['state']['values'] ?? [];
        $searchQuery = $this->stateValue($state, 'search', SlackAction::VendorsListSearch->value) ?? '';
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $session = LunchSession::find($metadata['lunch_session_id'] ?? null);
        if (! $session) {
            return;
        }

        $query = Vendor::query()
            ->where('organization_id', $session->organization_id)
            ->where('active', true)
            ->with('media');

        if ($searchQuery !== '') {
            $query->where('name', 'like', "%{$searchQuery}%");
        }

        $vendors = $query->orderBy('name')->get()->all();
        $view = $this->blocks->vendorsListModal($session, $vendors, $searchQuery);
        $this->messenger->updateModal($payload['view']['id'], $view);
    }

    private function vendorsListEdit(string $value, string $triggerId, array $payload): void
    {
        $vendor = Vendor::find($value);
        if (! $vendor) {
            return;
        }

        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');
        $view = $this->blocks->editVendorModal($vendor, $metadata);
        $this->messenger->pushModal($triggerId, $view);
    }

    private function devResetDatabase(string $userId, string $channelId): void
    {
        if (! app()->environment('local', 'dev', 'testing')) {
            return;
        }

        if (! $this->isDevUser($userId)) {
            return;
        }

        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->messenger->postEphemeral($channelId, $userId, 'Base de donnees reinitialisee avec succes.');
    }

    private function devExportVendors(string $userId, string $triggerId): void
    {
        if (! app()->environment('local', 'dev', 'testing')) {
            return;
        }

        if (! $this->isDevUser($userId)) {
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
    }
}
