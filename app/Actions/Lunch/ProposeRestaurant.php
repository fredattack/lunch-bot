<?php

namespace App\Actions\Lunch;

use App\Enums\FulfillmentType;
use App\Enums\LunchDayStatus;
use App\Enums\ProposalStatus;
use App\Models\Enseigne;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use InvalidArgumentException;

class ProposeRestaurant
{
    public function handle(
        LunchDay $day,
        Enseigne $enseigne,
        FulfillmentType $fulfillment,
        ?string $platform,
        string $createdByUserId
    ): LunchDayProposal {
        if ($day->status !== LunchDayStatus::Open) {
            throw new InvalidArgumentException('Lunch day is not open.');
        }

        $existing = LunchDayProposal::query()
            ->where('lunch_day_id', $day->id)
            ->where('enseigne_id', $enseigne->id)
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('This restaurant has already been proposed for this day.');
        }

        return LunchDayProposal::create([
            'lunch_day_id' => $day->id,
            'enseigne_id' => $enseigne->id,
            'fulfillment_type' => $fulfillment,
            'platform' => $platform,
            'status' => ProposalStatus::Open,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }
}
