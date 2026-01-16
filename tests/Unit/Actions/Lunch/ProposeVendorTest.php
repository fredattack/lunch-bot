<?php

namespace Tests\Unit\Actions\Lunch;

use App\Actions\Lunch\ProposeVendor;
use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProposeVendorTest extends TestCase
{
    use RefreshDatabase;

    private ProposeVendor $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProposeVendor;
    }

    public function test_creates_proposal_for_open_session(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            $vendor,
            FulfillmentType::Pickup,
            'uber-eats',
            $userId
        );

        $this->assertInstanceOf(VendorProposal::class, $proposal);
        $this->assertEquals($session->id, $proposal->lunch_session_id);
        $this->assertEquals($vendor->id, $proposal->vendor_id);
        $this->assertEquals(FulfillmentType::Pickup, $proposal->fulfillment_type);
        $this->assertEquals('uber-eats', $proposal->platform);
        $this->assertEquals(ProposalStatus::Open, $proposal->status);
        $this->assertEquals($userId, $proposal->created_by_provider_user_id);
    }

    public function test_throws_exception_for_locked_session(): void
    {
        $session = LunchSession::factory()->locked()->create();
        $vendor = Vendor::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch session is not open.');

        $this->action->handle($session, $vendor, FulfillmentType::Pickup, null, 'U_CREATOR');
    }

    public function test_throws_exception_for_closed_session(): void
    {
        $session = LunchSession::factory()->closed()->create();
        $vendor = Vendor::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch session is not open.');

        $this->action->handle($session, $vendor, FulfillmentType::Pickup, null, 'U_CREATOR');
    }

    public function test_throws_exception_for_duplicate_vendor_on_same_session(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        VendorProposal::factory()->for($session)->create([
            'vendor_id' => $vendor->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This vendor has already been proposed for this session.');

        $this->action->handle($session, $vendor, FulfillmentType::Delivery, null, 'U_OTHER');
    }

    public function test_allows_same_vendor_on_different_sessions(): void
    {
        $session1 = LunchSession::factory()->open()->create();
        $session2 = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal1 = $this->action->handle($session1, $vendor, FulfillmentType::Pickup, null, 'U_USER1');
        $proposal2 = $this->action->handle($session2, $vendor, FulfillmentType::Delivery, null, 'U_USER2');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
        $this->assertEquals($vendor->id, $proposal1->vendor_id);
        $this->assertEquals($vendor->id, $proposal2->vendor_id);
    }

    public function test_allows_different_vendors_on_same_session(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        $proposal1 = $this->action->handle($session, $vendor1, FulfillmentType::Pickup, null, 'U_USER');
        $proposal2 = $this->action->handle($session, $vendor2, FulfillmentType::Pickup, null, 'U_USER');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
    }

    public function test_creates_proposal_with_delivery_fulfillment(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Delivery, null, 'U_CREATOR');

        $this->assertEquals(FulfillmentType::Delivery, $proposal->fulfillment_type);
    }

    public function test_creates_proposal_with_null_platform(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Pickup, null, 'U_CREATOR');

        $this->assertNull($proposal->platform);
    }
}
