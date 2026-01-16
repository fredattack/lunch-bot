<?php

namespace App\Slack;

use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Illuminate\Support\Collection;

class SlackMessenger
{
    public function __construct(
        private readonly SlackService $slack,
        private readonly SlackBlockBuilder $blocks
    ) {}

    public function postDailyKickoff(LunchDay $day): void
    {
        if ($day->provider_message_ts) {
            return;
        }

        $blocks = $this->blocks->dailyKickoffBlocks($day);
        $response = $this->slack->postMessage(
            $day->provider_channel_id,
            'Dejeuner du '.$day->date->format('Y-m-d'),
            $blocks
        );

        if ($response['ok'] ?? false) {
            $day->provider_message_ts = $response['ts'] ?? null;
            $day->save();
        }
    }

    public function postProposalMessage(LunchDayProposal $proposal): void
    {
        $proposal->loadMissing(['enseigne', 'orders', 'lunchDay']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $response = $this->slack->postMessage(
            $proposal->lunchDay->provider_channel_id,
            'Proposition: '.$proposal->enseigne->name,
            $blocks,
            $proposal->lunchDay->provider_message_ts
        );

        if ($response['ok'] ?? false) {
            $proposal->provider_message_ts = $response['ts'] ?? null;
            $proposal->save();
        }
    }

    public function updateProposalMessage(LunchDayProposal $proposal): void
    {
        if (! $proposal->provider_message_ts) {
            return;
        }

        $proposal->loadMissing(['enseigne', 'orders', 'lunchDay']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $this->slack->updateMessage(
            $proposal->lunchDay->provider_channel_id,
            $proposal->provider_message_ts,
            'Proposition: '.$proposal->enseigne->name,
            $blocks
        );
    }

    public function postSummary(LunchDayProposal $proposal): void
    {
        $proposal->loadMissing(['orders', 'lunchDay']);

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
            $proposal->lunchDay->provider_channel_id,
            'Recapitulatif',
            $blocks,
            $proposal->lunchDay->provider_message_ts
        );
    }

    public function postClosureSummary(LunchDay $day): void
    {
        if (! $day->provider_message_ts) {
            return;
        }

        $orders = Order::query()
            ->whereHas('proposal', function ($query) use ($day) {
                $query->where('lunch_day_id', $day->id);
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
            $day->provider_channel_id,
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
            $day->provider_message_ts
        );
    }

    /**
     * @param  Collection<int, LunchDay>  $days
     */
    public function notifyDaysLocked(Collection $days): void
    {
        foreach ($days as $day) {
            if (! $day->provider_message_ts) {
                continue;
            }

            $this->slack->postMessage(
                $day->provider_channel_id,
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
                $day->provider_message_ts
            );
        }
    }

    public function postRoleDelegation(LunchDayProposal $proposal, string $role, string $fromUserId, string $toUserId): void
    {
        if (! $proposal->lunchDay?->provider_message_ts) {
            return;
        }

        $this->slack->postMessage(
            $proposal->lunchDay->provider_channel_id,
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
            $proposal->lunchDay->provider_message_ts
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
