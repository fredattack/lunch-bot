<?php

namespace Tests\Unit\Services\Slack;

use App\Enums\DashboardState;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use App\Services\Slack\DashboardStateResolver;
use App\Services\Slack\SlackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DashboardStateResolverTest extends TestCase
{
    use RefreshDatabase;

    private DashboardStateResolver $resolver;

    private string $userId = 'U_TEST_USER';

    protected function setUp(): void
    {
        parent::setUp();

        $slackService = Mockery::mock(SlackService::class);
        $slackService->shouldReceive('teamInfo')->andReturn(['locale' => 'en']);
        $this->resolver = new DashboardStateResolver($slackService);
    }

    public function test_s1_no_proposal_when_session_has_no_proposals(): void
    {
        $session = $this->createTodaySession();

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::NoProposal, $context->state);
        $this->assertTrue($context->isToday);
        $this->assertFalse($context->hasProposals());
        $this->assertFalse($context->hasOpenProposals());
        $this->assertFalse($context->hasOrder());
        $this->assertFalse($context->isInCharge());
    }

    public function test_s2_open_proposals_no_order_when_proposals_exist_but_user_has_no_order(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Open]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::OpenProposalsNoOrder, $context->state);
        $this->assertTrue($context->hasProposals());
        $this->assertTrue($context->hasOpenProposals());
        $this->assertFalse($context->hasOrder());
        $this->assertFalse($context->isInCharge());
    }

    public function test_s3_has_order_when_user_has_order_but_not_in_charge(): void
    {
        $session = $this->createTodaySession();
        $proposal = VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Open,
            'runner_user_id' => 'U_OTHER_RUNNER',
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::HasOrder, $context->state);
        $this->assertTrue($context->hasOrder());
        $this->assertFalse($context->isInCharge());
        $this->assertEquals($this->userId, $context->myOrder->provider_user_id);
    }

    public function test_s4_in_charge_when_user_is_runner(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'runner_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::InCharge, $context->state);
        $this->assertTrue($context->isInCharge());
        $this->assertCount(1, $context->myProposalsInCharge);
    }

    public function test_s4_in_charge_when_user_is_orderer(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'orderer_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::InCharge, $context->state);
        $this->assertTrue($context->isInCharge());
    }

    public function test_s4_in_charge_takes_priority_over_has_order(): void
    {
        $session = $this->createTodaySession();
        $proposal = VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'runner_user_id' => $this->userId,
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::InCharge, $context->state);
        $this->assertTrue($context->hasOrder());
        $this->assertTrue($context->isInCharge());
    }

    public function test_s5_all_closed_when_proposals_exist_but_none_open(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Closed]);
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Closed]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::AllClosed, $context->state);
        $this->assertTrue($context->hasProposals());
        $this->assertFalse($context->hasOpenProposals());
    }

    public function test_s6_history_when_session_date_is_past(): void
    {
        $session = LunchSession::factory()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->subDay(),
        ]);
        VendorProposal::factory()->for($session)->create();

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::History, $context->state);
        $this->assertFalse($context->isToday);
        $this->assertFalse($context->state->allowsActions());
    }

    public function test_s6_history_takes_priority_over_other_states(): void
    {
        $session = LunchSession::factory()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->subDays(2),
        ]);
        $proposal = VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Open,
            'runner_user_id' => $this->userId,
        ]);
        Order::factory()->for($proposal)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertEquals(DashboardState::History, $context->state);
    }

    public function test_context_loads_proposals_with_vendor_and_orders(): void
    {
        $session = $this->createTodaySession();
        $proposal = VendorProposal::factory()->for($session)->create();
        Order::factory()->count(3)->for($proposal)->create([
            'organization_id' => $session->organization_id,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertCount(1, $context->proposals);
        $loadedProposal = $context->proposals->first();
        $this->assertTrue($loadedProposal->relationLoaded('vendor'));
        $this->assertTrue($loadedProposal->relationLoaded('orders'));
        $this->assertEquals(3, $loadedProposal->orders_count);
    }

    public function test_context_finds_my_order_across_multiple_proposals(): void
    {
        $session = $this->createTodaySession();
        $proposal1 = VendorProposal::factory()->for($session)->create();
        $proposal2 = VendorProposal::factory()->for($session)->create();

        Order::factory()->for($proposal1)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => 'U_OTHER',
        ]);
        Order::factory()->for($proposal2)->create([
            'organization_id' => $session->organization_id,
            'provider_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertNotNull($context->myOrder);
        $this->assertEquals($this->userId, $context->myOrder->provider_user_id);
        $this->assertNotNull($context->myOrderProposal);
        $this->assertEquals($proposal2->id, $context->myOrderProposal->id);
    }

    public function test_context_can_create_proposal_only_when_session_is_open_and_today(): void
    {
        $openTodaySession = $this->createTodaySession();
        $lockedTodaySession = LunchSession::factory()->locked()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->toDateString(),
        ]);
        $pastSession = LunchSession::factory()->open()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->subDay(),
        ]);

        $this->assertTrue($this->resolver->resolve($openTodaySession, $this->userId)->canCreateProposal());
        $this->assertFalse($this->resolver->resolve($lockedTodaySession, $this->userId)->canCreateProposal());
        $this->assertFalse($this->resolver->resolve($pastSession, $this->userId)->canCreateProposal());
    }

    public function test_context_can_close_session_for_admin(): void
    {
        $session = $this->createTodaySession();

        $contextNonAdmin = $this->resolver->resolve($session, $this->userId, false);
        $contextAdmin = $this->resolver->resolve($session, $this->userId, true);

        $this->assertFalse($contextNonAdmin->canCloseSession());
        $this->assertTrue($contextAdmin->canCloseSession());
    }

    public function test_context_can_close_session_for_user_in_charge(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'runner_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId, false);

        $this->assertTrue($context->canCloseSession());
    }

    public function test_private_metadata_contains_required_fields(): void
    {
        $session = $this->createTodaySession();

        $context = $this->resolver->resolve($session, $this->userId);
        $metadata = $context->toPrivateMetadata();

        $this->assertArrayHasKey('tenant_id', $metadata);
        $this->assertArrayHasKey('date', $metadata);
        $this->assertArrayHasKey('lunch_session_id', $metadata);
        $this->assertArrayHasKey('origin', $metadata);
        $this->assertArrayHasKey('user_id', $metadata);
        $this->assertArrayHasKey('state', $metadata);

        $this->assertEquals($session->organization_id, $metadata['tenant_id']);
        $this->assertEquals($session->id, $metadata['lunch_session_id']);
        $this->assertEquals($this->userId, $metadata['user_id']);
        $this->assertEquals('slash_lunch', $metadata['origin']);
        $this->assertEquals(DashboardState::NoProposal->value, $metadata['state']);
    }

    public function test_open_proposals_includes_ordering_status(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Open]);
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Ordering]);
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Placed]);
        VendorProposal::factory()->for($session)->create(['status' => ProposalStatus::Closed]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertCount(4, $context->proposals);
        $this->assertCount(2, $context->openProposals);
    }

    public function test_my_proposals_in_charge_excludes_closed_proposals(): void
    {
        $session = $this->createTodaySession();
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Ordering,
            'runner_user_id' => $this->userId,
        ]);
        VendorProposal::factory()->for($session)->create([
            'status' => ProposalStatus::Closed,
            'runner_user_id' => $this->userId,
        ]);

        $context = $this->resolver->resolve($session, $this->userId);

        $this->assertCount(1, $context->myProposalsInCharge);
    }

    private function createTodaySession(): LunchSession
    {
        return LunchSession::factory()->open()->create([
            'date' => Carbon::now(config('lunch.timezone', 'Europe/Paris'))->toDateString(),
        ]);
    }
}
