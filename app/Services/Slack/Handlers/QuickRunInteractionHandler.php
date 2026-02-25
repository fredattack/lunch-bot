<?php

namespace App\Services\Slack\Handlers;

use App\Actions\QuickRun\AddQuickRunRequest;
use App\Actions\QuickRun\CloseQuickRun;
use App\Actions\QuickRun\CreateQuickRun;
use App\Actions\QuickRun\DeleteQuickRunRequest;
use App\Actions\QuickRun\LockQuickRun;
use App\Actions\QuickRun\UpdateQuickRunRequest;
use App\Enums\SlackAction;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Symfony\Component\HttpFoundation\Response;

class QuickRunInteractionHandler extends BaseInteractionHandler
{
    public function __construct(
        SlackService $slack,
        SlackMessenger $messenger,
        SlackBlockBuilder $blocks,
        private readonly CreateQuickRun $createQuickRun,
        private readonly AddQuickRunRequest $addRequest,
        private readonly UpdateQuickRunRequest $updateRequest,
        private readonly DeleteQuickRunRequest $deleteRequest,
        private readonly LockQuickRun $lockQuickRun,
        private readonly CloseQuickRun $closeQuickRun
    ) {
        parent::__construct($slack, $messenger, $blocks);
    }

    public function handleBlockAction(string $actionId, string $value, string $userId, string $triggerId, string $channelId, array $payload = []): void
    {
        match ($actionId) {
            SlackAction::QuickRunOpen->value => $this->openCreateModal($channelId, $triggerId, $payload),
            SlackAction::QuickRunAddRequest->value => $this->openRequestModal($value, $userId, $channelId, $triggerId),
            SlackAction::QuickRunEditRequest->value => $this->openEditRequestModal($value, $userId, $channelId, $triggerId),
            SlackAction::QuickRunDeleteRequest->value => $this->handleDeleteRequest($value, $userId, $channelId),
            SlackAction::QuickRunLock->value => $this->handleLock($value, $userId, $channelId),
            SlackAction::QuickRunClose->value => $this->handleClose($value, $userId, $channelId),
            SlackAction::QuickRunRecap->value => $this->openRecap($value, $triggerId),
            SlackAction::QuickRunAdjustPrices->value => $this->openAdjustPrices($value, $userId, $channelId, $triggerId),
            default => null,
        };
    }

    public function handleQuickRunCreate(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');

        $destination = $this->stateValue($state, 'destination', 'destination');
        $delayMinutes = $this->stateValue($state, 'delay', 'delay_minutes');
        $note = $this->stateValue($state, 'note', 'note');
        $channelId = $metadata['channel_id'] ?? config('lunch.channel_id');

        if (! $destination) {
            return $this->viewErrorResponse(['destination' => 'Destination requise.']);
        }

        $delay = (int) $delayMinutes;
        if ($delay < 1 || $delay > 120) {
            return $this->viewErrorResponse(['delay' => 'Le delai doit etre entre 1 et 120 minutes.']);
        }

        $quickRun = $this->createQuickRun->handle($userId, $channelId, [
            'destination' => $destination,
            'delay_minutes' => $delay,
            'note' => $note ?: null,
        ]);

        $this->messenger->postQuickRun($quickRun);
        $this->messenger->postQuickRunRunnerActions($quickRun);

        return response('', 200);
    }

    public function handleRequestCreate(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');

        $quickRun = QuickRun::find($metadata['quick_run_id'] ?? null);
        if (! $quickRun) {
            return response('', 200);
        }

        $description = $this->stateValue($state, 'description', 'description');
        $priceEstimated = $this->parsePrice($this->stateValue($state, 'price_estimated', 'price_estimated'));

        if (! $description) {
            return $this->viewErrorResponse(['description' => 'Description requise.']);
        }

        $request = $this->addRequest->handle($quickRun, $userId, [
            'description' => $description,
            'price_estimated' => $priceEstimated,
        ]);

        $this->messenger->updateQuickRunMessage($quickRun);
        $this->messenger->notifyQuickRunRunner($quickRun, $request);
        $this->messenger->postQuickRunRunnerActions($quickRun);

        return response('', 200);
    }

    public function handleRequestEdit(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');

        $request = QuickRunRequest::find($metadata['request_id'] ?? null);
        if (! $request) {
            return response('', 200);
        }

        $description = $this->stateValue($state, 'description', 'description');
        $priceEstimated = $this->parsePrice($this->stateValue($state, 'price_estimated', 'price_estimated'));

        if (! $description) {
            return $this->viewErrorResponse(['description' => 'Description requise.']);
        }

        $this->updateRequest->handle($request, $userId, [
            'description' => $description,
            'price_estimated' => $priceEstimated,
        ]);

        $request->loadMissing('quickRun');
        $this->messenger->updateQuickRunMessage($request->quickRun);
        $this->messenger->postQuickRunRunnerActions($request->quickRun);

        return response('', 200);
    }

    public function handleQuickRunCloseSubmission(array $payload, string $userId): Response
    {
        $state = $payload['view']['state']['values'] ?? [];
        $metadata = $this->decodeMetadata($payload['view']['private_metadata'] ?? '{}');

        $quickRun = QuickRun::find($metadata['quick_run_id'] ?? null);
        if (! $quickRun) {
            return response('', 200);
        }

        $requestId = $this->stateValue($state, 'request', 'request_id');
        $priceFinal = $this->parsePrice($this->stateValue($state, 'price_final', 'price_final'));

        $priceAdjustments = [];
        if ($requestId && $priceFinal !== null) {
            $priceAdjustments[] = ['id' => (int) $requestId, 'price_final' => $priceFinal];
        }

        $this->closeQuickRun->handle($quickRun, $userId, $priceAdjustments);

        $this->messenger->updateQuickRunMessage($quickRun);
        $this->messenger->postQuickRunClosureSummary($quickRun);

        return response('', 200);
    }

    private function openCreateModal(string $channelId, string $triggerId, array $payload = []): void
    {
        $view = $this->blocks->quickRunCreateModal($channelId);

        if (isset($payload['view'])) {
            $this->messenger->pushModal($triggerId, $view);
        } else {
            $this->messenger->openModal($triggerId, $view);
        }
    }

    private function openRequestModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $quickRun = QuickRun::find($value);
        if (! $quickRun || ! $quickRun->isOpen()) {
            $this->messenger->postEphemeral($channelId, $userId, 'Ce Quick Run n\'accepte plus de demandes.');

            return;
        }

        $existingRequest = $quickRun->requests()
            ->where('provider_user_id', $userId)
            ->first();

        if ($existingRequest) {
            $view = $this->blocks->quickRunRequestModal($quickRun, $existingRequest);
        } else {
            $view = $this->blocks->quickRunRequestModal($quickRun);
        }

        $this->messenger->openModal($triggerId, $view);
    }

    private function openEditRequestModal(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $request = QuickRunRequest::with('quickRun')->find($value);
        if (! $request || ! $request->quickRun->isOpen()) {
            $this->messenger->postEphemeral($channelId, $userId, 'Cette demande ne peut plus etre modifiee.');

            return;
        }

        $view = $this->blocks->quickRunRequestModal($request->quickRun, $request);
        $this->messenger->openModal($triggerId, $view);
    }

    private function handleDeleteRequest(string $value, string $userId, string $channelId): void
    {
        $request = QuickRunRequest::with('quickRun')->find($value);
        if (! $request) {
            return;
        }

        try {
            $this->deleteRequest->handle($request, $userId);
            $this->messenger->updateQuickRunMessage($request->quickRun);
            $this->messenger->postQuickRunRunnerActions($request->quickRun);
            $this->messenger->postEphemeral($channelId, $userId, 'Demande supprimee.');
        } catch (\InvalidArgumentException $e) {
            $this->messenger->postEphemeral($channelId, $userId, $e->getMessage());
        }
    }

    private function handleLock(string $value, string $userId, string $channelId): void
    {
        $quickRun = QuickRun::find($value);
        if (! $quickRun) {
            return;
        }

        try {
            $this->lockQuickRun->handle($quickRun, $userId);
            $this->messenger->updateQuickRunMessage($quickRun);
            $this->messenger->postQuickRunRunnerActions($quickRun);
        } catch (\InvalidArgumentException $e) {
            $this->messenger->postEphemeral($channelId, $userId, $e->getMessage());
        }
    }

    private function handleClose(string $value, string $userId, string $channelId): void
    {
        $quickRun = QuickRun::with('requests')->find($value);
        if (! $quickRun) {
            return;
        }

        if (! $quickRun->isRunner($userId)) {
            $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner peut cloturer ce Quick Run.');

            return;
        }

        try {
            $this->closeQuickRun->handle($quickRun, $userId);
            $this->messenger->updateQuickRunMessage($quickRun);
            $this->messenger->postQuickRunClosureSummary($quickRun);
        } catch (\InvalidArgumentException $e) {
            $this->messenger->postEphemeral($channelId, $userId, $e->getMessage());
        }
    }

    private function openRecap(string $value, string $triggerId): void
    {
        $quickRun = QuickRun::with('requests')->find($value);
        if (! $quickRun) {
            return;
        }

        $requests = $quickRun->requests->all();
        $estimated = $quickRun->requests->sum('price_estimated');
        $final = $quickRun->requests->sum(function (QuickRunRequest $r) {
            return $r->price_final ?? $r->price_estimated ?? 0;
        });

        $view = $this->blocks->quickRunRecapModal($quickRun, $requests, [
            'estimated' => number_format((float) $estimated, 2),
            'final' => number_format((float) $final, 2),
        ]);
        $this->messenger->openModal($triggerId, $view);
    }

    private function openAdjustPrices(string $value, string $userId, string $channelId, string $triggerId): void
    {
        $quickRun = QuickRun::with('requests')->find($value);
        if (! $quickRun) {
            return;
        }

        if (! $quickRun->isRunner($userId)) {
            $this->messenger->postEphemeral($channelId, $userId, 'Seul le runner peut ajuster les prix.');

            return;
        }

        $requests = $quickRun->requests->all();
        if (empty($requests)) {
            $this->messenger->postEphemeral($channelId, $userId, 'Aucune demande a ajuster.');

            return;
        }

        $view = $this->blocks->quickRunAdjustPricesModal($quickRun, $requests);
        $this->messenger->openModal($triggerId, $view);
    }
}
