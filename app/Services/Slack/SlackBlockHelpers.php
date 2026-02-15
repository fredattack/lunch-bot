<?php

namespace App\Services\Slack;

use App\Enums\FulfillmentType;
use Carbon\CarbonInterface;

trait SlackBlockHelpers
{
    private function button(string $text, string $actionId, string $value, ?string $style = null): array
    {
        $button = [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
            ],
            'action_id' => $actionId,
            'value' => $value,
        ];

        if ($style) {
            $button['style'] = $style;
        }

        return $button;
    }

    private function formatTime(?CarbonInterface $dateTime): string
    {
        return $dateTime ? $dateTime->format('H:i') : '-';
    }

    private function formatPrice(?float $amount): string
    {
        if ($amount === null) {
            return '-';
        }

        return number_format($amount, 2).' EUR';
    }

    private function fulfillmentLabel(FulfillmentType $type): string
    {
        return match ($type) {
            FulfillmentType::Pickup => 'A emporter',
            FulfillmentType::Delivery => 'Livraison',
            FulfillmentType::OnSite => 'Sur place',
        };
    }
}
