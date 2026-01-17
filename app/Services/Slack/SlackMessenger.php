<?php

namespace App\Services\Slack;

use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use Illuminate\Support\Collection;

class SlackMessenger
{
    public function __construct(
        private readonly SlackService $slack,
        private readonly SlackBlockBuilder $blocks
    ) {}

    public function postDailyKickoff(LunchSession $session): void
    {
        if ($session->provider_message_ts) {
            return;
        }

        $blocks = $this->blocks->dailyKickoffBlocks($session);
        $response = $this->slack->postMessage(
            $session->provider_channel_id,
            'Dejeuner du '.$session->date->format('Y-m-d'),
            $blocks
        );

        if ($response['ok'] ?? false) {
            $session->provider_message_ts = $response['ts'] ?? null;
            $session->save();
        }
    }

    public function postProposalMessage(VendorProposal $proposal): void
    {
        $proposal->loadMissing(['vendor', 'orders', 'lunchSession']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $response = $this->slack->postMessage(
            $proposal->lunchSession->provider_channel_id,
            'Proposition: '.$proposal->vendor->name,
            $blocks,
            $proposal->lunchSession->provider_message_ts
        );

        if ($response['ok'] ?? false) {
            $proposal->provider_message_ts = $response['ts'] ?? null;
            $proposal->save();
        }
    }

    public function updateProposalMessage(VendorProposal $proposal): void
    {
        if (! $proposal->provider_message_ts) {
            return;
        }

        $proposal->loadMissing(['vendor', 'orders', 'lunchSession']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $this->slack->updateMessage(
            $proposal->lunchSession->provider_channel_id,
            $proposal->provider_message_ts,
            'Proposition: '.$proposal->vendor->name,
            $blocks
        );
    }

    public function postSummary(VendorProposal $proposal): void
    {
        $proposal->loadMissing(['orders', 'lunchSession']);

        $orders = $proposal->orders;
        $estimated = $orders->sum('price_estimated');
        $final = $orders->sum(function (Order $order) {
            return $order->price_final ?? $order->price_estimated;
        });

        $blocks = $this->blocks->summaryBlocks($proposal, $orders->all(), [
            'estimated' => number_format((float) $estimated, 2),
            'final' => number_format((float) $final, 2),
        ]);

        $this->slack->postMessage(
            $proposal->lunchSession->provider_channel_id,
            'Recapitulatif',
            $blocks,
            $proposal->lunchSession->provider_message_ts
        );
    }

    public function postClosureSummary(LunchSession $session): void
    {
        if (! $session->provider_message_ts) {
            return;
        }

        $orders = Order::query()
            ->whereHas('proposal', function ($query) use ($session) {
                $query->where('lunch_session_id', $session->id);
            })
            ->get();

        $totals = [];
        foreach ($orders as $order) {
            $amount = $order->price_final !== null ? (float) $order->price_final : (float) $order->price_estimated;
            $totals[$order->provider_user_id] = ($totals[$order->provider_user_id] ?? 0) + $amount;
        }

        $lines = [];
        foreach ($totals as $userId => $amount) {
            $lines[] = sprintf('- <@%s>: %.2f', $userId, $amount);
        }

        if (empty($lines)) {
            $lines[] = '- Aucun total a afficher.';
        }

        $text = "*Journee cloturee.*\nRemboursements:\n".implode("\n", $lines);

        $this->slack->postMessage(
            $session->provider_channel_id,
            'Journee cloturee',
            [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $text,
                    ],
                ],
            ],
            $session->provider_message_ts
        );
    }

    /**
     * @param  Collection<int, LunchSession>  $sessions
     */
    public function notifySessionsLocked(Collection $sessions): void
    {
        foreach ($sessions as $session) {
            if (! $session->provider_message_ts) {
                continue;
            }

            $this->slack->postMessage(
                $session->provider_channel_id,
                'Commandes verrouillees.',
                [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => '*Commandes verrouillees.*',
                        ],
                    ],
                ],
                $session->provider_message_ts
            );
        }
    }

    public function postRoleDelegation(VendorProposal $proposal, string $role, string $fromUserId, string $toUserId): void
    {
        if (! $proposal->lunchSession?->provider_message_ts) {
            return;
        }

        $this->slack->postMessage(
            $proposal->lunchSession->provider_channel_id,
            'Role delegue',
            [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('Role %s transfere de <@%s> a <@%s>.', $role, $fromUserId, $toUserId),
                    ],
                ],
            ],
            $proposal->lunchSession->provider_message_ts
        );
    }

    public function postEphemeral(string $channelId, string $userId, string $message, ?string $threadTs = null): void
    {
        $this->slack->postEphemeral($channelId, $userId, $message, [], $threadTs);
    }

    public function openModal(string $triggerId, array $view): void
    {
        $this->slack->openModal($triggerId, $view);
    }

    public function isAdmin(string $userId): bool
    {
        return $this->slack->isAdmin($userId);
    }
}
