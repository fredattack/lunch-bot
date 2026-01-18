<?php

namespace Tests\Unit\Actions\VendorProposal;

use App\Actions\VendorProposal\ProposeVendor;
use App\Enums\FulfillmentType;
use App\Enums\OrderingMode;
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
            $userId
        );

        $this->assertInstanceOf(VendorProposal::class, $proposal);
        $this->assertEquals($session->id, $proposal->lunch_session_id);
        $this->assertEquals($vendor->id, $proposal->vendor_id);
        $this->assertEquals(FulfillmentType::Pickup, $proposal->fulfillment_type);
        $this->assertEquals(ProposalStatus::Open, $proposal->status);
        $this->assertEquals($userId, $proposal->created_by_provider_user_id);
    }

    public function test_throws_exception_for_locked_session(): void
    {
        $session = LunchSession::factory()->locked()->create();
        $vendor = Vendor::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch session is not open.');

        $this->action->handle($session, $vendor, FulfillmentType::Pickup, 'U_CREATOR');
    }

    public function test_throws_exception_for_closed_session(): void
    {
        $session = LunchSession::factory()->closed()->create();
        $vendor = Vendor::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lunch session is not open.');

        $this->action->handle($session, $vendor, FulfillmentType::Pickup, 'U_CREATOR');
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

        $this->action->handle($session, $vendor, FulfillmentType::Delivery, 'U_OTHER');
    }

    public function test_allows_same_vendor_on_different_sessions(): void
    {
        $session1 = LunchSession::factory()->open()->create();
        $session2 = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal1 = $this->action->handle($session1, $vendor, FulfillmentType::Pickup, 'U_USER1');
        $proposal2 = $this->action->handle($session2, $vendor, FulfillmentType::Delivery, 'U_USER2');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
        $this->assertEquals($vendor->id, $proposal1->vendor_id);
        $this->assertEquals($vendor->id, $proposal2->vendor_id);
    }

    public function test_allows_different_vendors_on_same_session(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        $proposal1 = $this->action->handle($session, $vendor1, FulfillmentType::Pickup, 'U_USER');
        $proposal2 = $this->action->handle($session, $vendor2, FulfillmentType::Pickup, 'U_USER');

        $this->assertNotEquals($proposal1->id, $proposal2->id);
    }

    public function test_creates_proposal_with_delivery_fulfillment(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Delivery, 'U_CREATOR');

        $this->assertEquals(FulfillmentType::Delivery, $proposal->fulfillment_type);
    }

    public function test_auto_assigns_runner_for_pickup(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Pickup, $userId);

        $this->assertEquals($userId, $proposal->runner_user_id);
        $this->assertNull($proposal->orderer_user_id);
    }

    public function test_auto_assigns_orderer_for_delivery(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Delivery, $userId);

        $this->assertNull($proposal->runner_user_id);
        $this->assertEquals($userId, $proposal->orderer_user_id);
    }

    public function test_sets_ordering_mode_shared_always(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Pickup, 'U_CREATOR');

        $this->assertEquals(OrderingMode::Shared, $proposal->ordering_mode);
    }

    public function test_sets_deadline_time(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle(
            $session,
            $vendor,
            FulfillmentType::Pickup,
            'U_CREATOR',
            '12:00'
        );

        $this->assertEquals('12:00', $proposal->deadline_time);
    }

    public function test_sets_note(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle(
            $session,
            $vendor,
            FulfillmentType::Pickup,
            'U_CREATOR',
            '11:30',
            'Special instructions'
        );

        $this->assertEquals('Special instructions', $proposal->note);
    }

    public function test_sets_help_requested(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle(
            $session,
            $vendor,
            FulfillmentType::Pickup,
            'U_CREATOR',
            '11:30',
            null,
            true
        );

        $this->assertTrue($proposal->help_requested);
    }

    public function test_help_requested_defaults_to_false(): void
    {
        $session = LunchSession::factory()->open()->create();
        $vendor = Vendor::factory()->create();

        $proposal = $this->action->handle($session, $vendor, FulfillmentType::Pickup, 'U_CREATOR');

        $this->assertFalse($proposal->help_requested);
    }
}
