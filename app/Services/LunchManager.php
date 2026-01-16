<?php

namespace App\Services;

use App\Enums\LunchDayStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LunchManager
{
    public function __construct(
        private readonly SlackService $slack,
        private readonly SlackBlockBuilder $blocks,
    ) {
    }

    public function ensureTodayLunchDay(): ?LunchDay
    {
        $channelId = config('lunch.channel_id');
        if (!$channelId) {
            Log::warning('Lunch channel id missing.');
            return null;
        }

        $timezone = config('lunch.timezone');
        $today = Carbon::now($timezone)->toDateString();
        $deadlineTime = config('lunch.deadline_time');
        $deadlineAt = Carbon::createFromFormat('Y-m-d H:i', $today . ' ' . $deadlineTime, $timezone);

        $day = LunchDay::firstOrCreate(
            ['date' => $today, 'slack_channel_id' => $channelId],
            ['deadline_at' => $deadlineAt, 'status' => LunchDayStatus::Open]
        );

        if ($day->deadline_at->ne($deadlineAt)) {
            $day->deadline_at = $deadlineAt;
            $day->save();
        }

        return $day;
    }

    public function postDailyKickoff(LunchDay $day): void
    {
        if ($day->slack_message_ts) {
            return;
        }

        $blocks = $this->blocks->dailyKickoffBlocks($day);
        $response = $this->slack->postMessage(
            $day->slack_channel_id,
            'Dejeuner du ' . $day->date->format('Y-m-d'),
            $blocks
        );

        if ($response['ok'] ?? false) {
            $day->slack_message_ts = $response['ts'] ?? null;
            $day->save();
        }
    }

    public function lockExpiredDays(): int
    {
        $timezone = config('lunch.timezone');
        $now = Carbon::now($timezone);

        $days = LunchDay::query()
            ->where('status', LunchDayStatus::Open)
            ->where('deadline_at', '<=', $now)
            ->get();

        foreach ($days as $day) {
            $day->status = LunchDayStatus::Locked;
            $day->save();

            if ($day->slack_message_ts) {
                $this->slack->postMessage(
                    $day->slack_channel_id,
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
                    $day->slack_message_ts
                );
            }
        }

        return $days->count();
    }

    public function closeDay(LunchDay $day): void
    {
        $day->status = LunchDayStatus::Closed;
        $day->save();

        $day->proposals()->update(['status' => ProposalStatus::Closed]);
    }

    public function postClosureSummary(LunchDay $day): void
    {
        if (!$day->slack_message_ts) {
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
            $totals[$order->slack_user_id] = ($totals[$order->slack_user_id] ?? 0) + $amount;
        }

        $lines = [];
        foreach ($totals as $userId => $amount) {
            $lines[] = sprintf('- <@%s>: %.2f', $userId, $amount);
        }

        if (empty($lines)) {
            $lines[] = '- Aucun total a afficher.';
        }

        $text = "*Journee cloturee.*\nRemboursements:\n" . implode("\n", $lines);

        $this->slack->postMessage(
            $day->slack_channel_id,
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
            $day->slack_message_ts
        );
    }

    public function postProposalMessage(LunchDayProposal $proposal): void
    {
        $proposal->loadMissing(['enseigne', 'orders']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $response = $this->slack->postMessage(
            $proposal->lunchDay->slack_channel_id,
            'Proposition: ' . $proposal->enseigne->name,
            $blocks,
            $proposal->lunchDay->slack_message_ts
        );

        if ($response['ok'] ?? false) {
            $proposal->slack_message_ts = $response['ts'] ?? null;
            $proposal->save();
        }
    }

    public function updateProposalMessage(LunchDayProposal $proposal): void
    {
        if (!$proposal->slack_message_ts) {
            return;
        }

        $proposal->loadMissing(['enseigne', 'orders', 'lunchDay']);
        $blocks = $this->blocks->proposalBlocks($proposal, $proposal->orders->count());

        $this->slack->updateMessage(
            $proposal->lunchDay->slack_channel_id,
            $proposal->slack_message_ts,
            'Proposition: ' . $proposal->enseigne->name,
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
            $proposal->lunchDay->slack_channel_id,
            'Recapitulatif',
            $blocks,
            $proposal->lunchDay->slack_message_ts
        );
    }
}
