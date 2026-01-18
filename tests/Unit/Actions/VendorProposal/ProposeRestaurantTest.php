<?php

namespace Tests\Unit\Actions\VendorProposal;

use App\Actions\VendorProposal\ProposeRestaurant;
use App\Enums\FulfillmentType;
use App\Enums\OrderingMode;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProposeRestaurantTest extends TestCase
{
    use RefreshDatabase;

    private ProposeRestaurant $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProposeRestaurant;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_creates_vendor_and_proposal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Sushi Wasabi', 'cuisine_type' => 'Japonais'],
            FulfillmentType::Pickup,
            $userId
        );

        $this->assertEquals($session->id, $proposal->lunch_session_id);
        $this->assertEquals('Sushi Wasabi', $proposal->vendor->name);
        $this->assertEquals('Japonais', $proposal->vendor->cuisine_type);
        $this->assertEquals(FulfillmentType::Pickup, $proposal->fulfillment_type);
        $this->assertEquals(ProposalStatus::Open, $proposal->status);
        $this->assertEquals($userId, $proposal->created_by_provider_user_id);
    }

    public function test_reuses_existing_vendor_with_same_name(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $existingVendor = Vendor::factory()->for($this->organization)->create(['name' => 'Quick']);

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Quick'],
            FulfillmentType::Pickup,
            'U_CREATOR'
        );

        $this->assertEquals($existingVendor->id, $proposal->vendor_id);
    }

    public function test_throws_exception_for_closed_session(): void
    {
        $session = LunchSession::factory()->for($this->organization)->closed()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La session de lunch est fermee.');

        $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            'U_CREATOR'
        );
    }

    public function test_auto_assigns_runner_for_pickup(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            $userId
        );

        $this->assertEquals($userId, $proposal->runner_user_id);
        $this->assertNull($proposal->orderer_user_id);
    }

    public function test_auto_assigns_orderer_for_delivery(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Delivery,
            $userId
        );

        $this->assertNull($proposal->runner_user_id);
        $this->assertEquals($userId, $proposal->orderer_user_id);
    }

    public function test_sets_ordering_mode_shared_always(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            'U_CREATOR'
        );

        $this->assertEquals(OrderingMode::Shared, $proposal->ordering_mode);
    }

    public function test_sets_deadline_time(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            'U_CREATOR',
            '12:00'
        );

        $this->assertEquals('12:00', $proposal->deadline_time);
    }

    public function test_sets_note(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            'U_CREATOR',
            '11:30',
            'Special instructions'
        );

        $this->assertEquals('Special instructions', $proposal->note);
    }

    public function test_sets_help_requested(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
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
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            ['name' => 'Test Restaurant'],
            FulfillmentType::Pickup,
            'U_CREATOR'
        );

        $this->assertFalse($proposal->help_requested);
    }
}
