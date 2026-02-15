<?php

namespace App\Services\Slack;

use App\Enums\FulfillmentType;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
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

    public function postOrderCreatedMessage(VendorProposal $proposal, string $createdByUserId, bool $hideOtherVendorButton = false): void
    {
        $proposal->loadMissing(['vendor', 'lunchSession']);

        $vendorName = $proposal->vendor?->name ?? 'Restaurant';
        $fulfillmentLabel = $proposal->fulfillment_type === FulfillmentType::Pickup ? 'pickup' : 'delivery';
        $date = $proposal->lunchSession->date->format('Y-m-d');

        $messageText = "*Nouvelle commande lancee*\n{$vendorName} — {$fulfillmentLabel}\nPar <@{$createdByUserId}>";

        if ($proposal->help_requested) {
            $messageText .= "\n:warning: *Aide demandee : le createur est tres occupe.*";
        }

        if ($proposal->note) {
            $messageText .= "\n\n:memo: _{$proposal->note}_";
        }

        $actionElements = [
            [
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => 'Commander'],
                'action_id' => SlackAction::OpenOrderForProposal->value,
                'value' => (string) $proposal->id,
                'style' => 'primary',
            ],
        ];

        if (! $hideOtherVendorButton) {
            $actionElements[] = [
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => 'Autre enseigne'],
                'action_id' => SlackAction::OpenLunchDashboard->value,
                'value' => $date,
            ];
        }

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $messageText,
                ],
            ],
            [
                'type' => 'actions',
                'elements' => $actionElements,
            ],
        ];

        $response = $this->slack->postMessage(
            $proposal->lunchSession->provider_channel_id,
            "Nouvelle commande: {$vendorName}",
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

    public function postQuickRun(QuickRun $quickRun): void
    {
        $quickRun->loadMissing('requests');
        $requestCount = $quickRun->requests->count();
        $requesterNames = $quickRun->requests->map(fn (QuickRunRequest $r) => "<@{$r->provider_user_id}>")->all();

        $blocks = $this->blocks->quickRunBlocks($quickRun, $requestCount, $requesterNames);
        $response = $this->slack->postMessage(
            $quickRun->provider_channel_id,
            "Quick Run: {$quickRun->destination}",
            $blocks
        );

        if ($response['ok'] ?? false) {
            $quickRun->provider_message_ts = $response['ts'] ?? null;
            $quickRun->save();
        }
    }

    public function updateQuickRunMessage(QuickRun $quickRun): void
    {
        if (! $quickRun->provider_message_ts) {
            return;
        }

        $quickRun->loadMissing('requests');
        $requestCount = $quickRun->requests->count();
        $requesterNames = $quickRun->requests->map(fn (QuickRunRequest $r) => "<@{$r->provider_user_id}>")->all();

        $blocks = $this->blocks->quickRunBlocks($quickRun, $requestCount, $requesterNames);

        $this->slack->updateMessage(
            $quickRun->provider_channel_id,
            $quickRun->provider_message_ts,
            "Quick Run: {$quickRun->destination}",
            $blocks
        );
    }

    public function postQuickRunClosureSummary(QuickRun $quickRun): void
    {
        $quickRun->loadMissing('requests');

        $totals = [];
        foreach ($quickRun->requests as $request) {
            $amount = $request->price_final !== null ? (float) $request->price_final : (float) ($request->price_estimated ?? 0);
            $totals[$request->provider_user_id] = ($totals[$request->provider_user_id] ?? 0) + $amount;
        }

        $lines = [];
        foreach ($totals as $userId => $amount) {
            $lines[] = sprintf('- <@%s>: %.2f EUR', $userId, $amount);
        }

        $totalAmount = array_sum($totals);

        if (empty($lines)) {
            $lines[] = '- Aucun montant a afficher.';
        }

        $text = "*Quick Run cloture*\nDestination: {$quickRun->destination}\nRunner: <@{$quickRun->provider_user_id}>\n\nMontants dus au runner:\n".implode("\n", $lines);
        $text .= sprintf("\n\n*Total: %.2f EUR*", $totalAmount);

        $this->slack->postMessage(
            $quickRun->provider_channel_id,
            'Quick Run cloture',
            [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $text,
                    ],
                ],
            ]
        );
    }

    /**
     * @param  Collection<int, QuickRun>  $quickRuns
     */
    public function notifyQuickRunsLocked(Collection $quickRuns): void
    {
        foreach ($quickRuns as $quickRun) {
            if (! $quickRun->provider_message_ts) {
                continue;
            }

            $this->updateQuickRunMessage($quickRun);

            $this->slack->postMessage(
                $quickRun->provider_channel_id,
                'Quick Run verrouille.',
                [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "*Quick Run verrouille.*\n<@{$quickRun->provider_user_id}>, les demandes sont figees. Consultez le recapitulatif.",
                        ],
                    ],
                ]
            );
        }
    }

    public function postEphemeral(string $channelId, string $userId, string $message, ?string $threadTs = null): void
    {
        $this->slack->postEphemeral($channelId, $userId, $message, [], $threadTs);
    }

    public function openModal(string $triggerId, array $view): void
    {
        $this->slack->openModal($triggerId, $view);
    }

    public function pushModal(string $triggerId, array $view): void
    {
        $this->slack->pushModal($triggerId, $view);
    }

    public function updateModal(string $viewId, array $view): void
    {
        $this->slack->updateModal($viewId, $view);
    }

    public function isAdmin(string $userId): bool
    {
        return $this->slack->isAdmin($userId);
    }
}
