<?php

namespace App\Services\Slack\Handlers;

use App\Actions\LunchSession\CloseLunchSession;
use App\Actions\LunchSession\CreateLunchSession;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\VendorProposal;
use App\Services\Slack\DashboardBlockBuilder;
use App\Services\Slack\DashboardStateResolver;
use App\Services\Slack\SlackBlockBuilder;
use App\Services\Slack\SlackMessenger;
use App\Services\Slack\SlackService;
use Carbon\Carbon;

class SessionInteractionHandler extends BaseInteractionHandler
{
    public function __construct(
        SlackService $slack,
        SlackMessenger $messenger,
        SlackBlockBuilder $blocks,
        private readonly DashboardBlockBuilder $dashboardBlocks,
        private readonly DashboardStateResolver $stateResolver,
        private readonly CreateLunchSession $createLunchSession,
        private readonly CloseLunchSession $closeLunchSession
    ) {
        parent::__construct($slack, $messenger, $blocks);
    }

    public function handleBlockAction(string $actionId, string $value, string $userId, string $triggerId, string $channelId): void
    {
        match ($actionId) {
            SlackAction::OpenLunchDashboard->value => $this->openDashboard($userId, $channelId, $triggerId, $value ?: null),
            SlackAction::SessionClose->value,
            SlackAction::CloseDay->value,
            SlackAction::DashboardCloseSession->value => $this->closeSession($value, $userId, $channelId),
            default => null,
        };
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

    private function openDashboard(string $userId, string $channelId, string $triggerId, ?string $dateOverride): void
    {
        $this->handleLunchDashboard($userId, $channelId, $triggerId, $dateOverride);
    }

    private function closeSession(string $value, string $userId, string $channelId): void
    {
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
}
