<?php

namespace Tests\Unit\Actions\Lunch;

use App\Actions\Lunch\ProposeRestaurant;
use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Models\Enseigne;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProposeRestaurantTest extends TestCase
{
    use RefreshDatabase;

    private ProposeRestaurant $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProposeRestaurant;
    }

    public function test_creates_proposal_for_open_day(): void
    {
        $day = LunchDay::factory()->open()->create();
        $enseigne = Enseigne::factory()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $day,
            $enseigne,
            FulfillmentType::Pickup,
            'uber-eats',
            $userId
        );

        $this->assertInstanceOf(LunchDayProposal::class, $proposal);
        $this->assertEquals($day->id, $proposal->lunch_day_id);
        $this->assertEquals($enseigne->id, $proposal->enseigne_id);
        $this->assertEquals(FulfillmentType::Pickup, $proposal->fulfillment_type);
        $this->assertEquals('uber-eats', $proposal->platform);
        $this->assertEquals(ProposalStatus::Open, $proposal->status);
        $this->assertEquals($userId, $proposal->created_by_provider_user_id);
    }

    public function test_throws_exception_for_locked_day(): void
    {
        $day = LunchDay::factory()->locked()->create();
        $enseigne = Enseigne::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch day is not open.');

        $this->action->handle($day, $enseigne, FulfillmentType::Pickup, null, 'U_CREATOR');
    }

    public function test_throws_exception_for_closed_day(): void
    {
        $day = LunchDay::factory()->closed()->create();
        $enseigne = Enseigne::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch day is not open.');

        $this->action->handle($day, $enseigne, FulfillmentType::Pickup, null, 'U_CREATOR');
    }

    public function test_throws_exception_for_duplicate_enseigne_on_same_day(): void
    {
        $day = LunchDay::factory()->open()->create();
        $enseigne = Enseigne::factory()->create();

        LunchDayProposal::factory()->for($day)->create([
            'enseigne_id' => $enseigne->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This restaurant has already been proposed for this day.');

        $this->action->handle($day, $enseigne, FulfillmentType::Delivery, null, 'U_OTHER');
    }

    public function test_allows_same_enseigne_on_different_days(): void
    {
        $day1 = LunchDay::factory()->open()->create();
        $day2 = LunchDay::factory()->open()->create();
        $enseigne = Enseigne::factory()->create();

        $proposal1 = $this->action->handle($day1, $enseigne, FulfillmentType::Pickup, null, 'U_USER1');
        $proposal2 = $this->action->handle($day2, $enseigne, FulfillmentType::Delivery, null, 'U_USER2');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
        $this->assertEquals($enseigne->id, $proposal1->enseigne_id);
        $this->assertEquals($enseigne->id, $proposal2->enseigne_id);
    }

    public function test_allows_different_enseignes_on_same_day(): void
    {
        $day = LunchDay::factory()->open()->create();
        $enseigne1 = Enseigne::factory()->create();
        $enseigne2 = Enseigne::factory()->create();

        $proposal1 = $this->action->handle($day, $enseigne1, FulfillmentType::Pickup, null, 'U_USER');
        $proposal2 = $this->action->handle($day, $enseigne2, FulfillmentType::Pickup, null, 'U_USER');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
    }

    public function test_creates_proposal_with_delivery_fulfillment(): void
    {
        $day = LunchDay::factory()->open()->create();
        $enseigne = Enseigne::factory()->create();

        $proposal = $this->action->handle($day, $enseigne, FulfillmentType::Delivery, null, 'U_CREATOR');

        $this->assertEquals(FulfillmentType::Delivery, $proposal->fulfillment_type);
    }

    public function test_creates_proposal_with_null_platform(): void
    {
        $day = LunchDay::factory()->open()->create();
        $enseigne = Enseigne::factory()->create();

        $proposal = $this->action->handle($day, $enseigne, FulfillmentType::Pickup, null, 'U_CREATOR');

        $this->assertNull($proposal->platform);
    }
}
