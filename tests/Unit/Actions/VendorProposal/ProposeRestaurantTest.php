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
            [
                'name' => 'Sushi Wasabi',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
            $userId
        );

        $this->assertEquals($session->id, $proposal->lunch_session_id);
        $this->assertEquals('Sushi Wasabi', $proposal->vendor->name);
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
            [
                'name' => 'Quick',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
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
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
            'U_CREATOR'
        );
    }

    public function test_throws_exception_for_empty_fulfillment_types(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Au moins un type de commande doit etre selectionne.');

        $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [],
            ],
            'U_CREATOR'
        );
    }

    public function test_auto_assigns_runner_for_pickup(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
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
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Delivery->value],
            ],
            $userId
        );

        $this->assertNull($proposal->runner_user_id);
        $this->assertEquals($userId, $proposal->orderer_user_id);
    }

    public function test_no_role_assigned_for_on_site(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();
        $userId = 'U_CREATOR';

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::OnSite->value],
            ],
            $userId
        );

        $this->assertNull($proposal->runner_user_id);
        $this->assertNull($proposal->orderer_user_id);
    }

    public function test_uses_first_fulfillment_type_for_proposal(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [
                    FulfillmentType::Delivery->value,
                    FulfillmentType::Pickup->value,
                ],
            ],
            'U_CREATOR'
        );

        $this->assertEquals(FulfillmentType::Delivery, $proposal->fulfillment_type);
    }

    public function test_stores_fulfillment_types_on_vendor(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [
                    FulfillmentType::Pickup->value,
                    FulfillmentType::Delivery->value,
                    FulfillmentType::OnSite->value,
                ],
            ],
            'U_CREATOR'
        );

        $this->assertEquals(
            [FulfillmentType::Pickup->value, FulfillmentType::Delivery->value, FulfillmentType::OnSite->value],
            $proposal->vendor->fulfillment_types
        );
    }

    public function test_stores_allow_individual_order_on_vendor(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
                'allow_individual_order' => true,
            ],
            'U_CREATOR'
        );

        $this->assertTrue($proposal->vendor->allow_individual_order);
    }

    public function test_sets_ordering_mode_shared_always(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
            'U_CREATOR'
        );

        $this->assertEquals(OrderingMode::Shared, $proposal->ordering_mode);
    }

    public function test_sets_deadline_time(): void
    {
        $session = LunchSession::factory()->for($this->organization)->open()->create();

        $proposal = $this->action->handle(
            $session,
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
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
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
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
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
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
            [
                'name' => 'Test Restaurant',
                'fulfillment_types' => [FulfillmentType::Pickup->value],
            ],
            'U_CREATOR'
        );

        $this->assertFalse($proposal->help_requested);
    }
}
