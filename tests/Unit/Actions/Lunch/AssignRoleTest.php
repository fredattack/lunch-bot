<?php

namespace Tests\Unit\Actions\Lunch;

use App\Actions\Lunch\AssignRole;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use RefreshDatabase;

    private AssignRole $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AssignRole;
    }

    public function test_assigns_runner_role_to_proposal(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $userId = 'U123ABC';

        $result = $this->action->handle($proposal, 'runner', $userId);

        $this->assertTrue($result);
        $this->assertEquals($userId, $proposal->runner_user_id);
        $this->assertEquals(ProposalStatus::Ordering, $proposal->status);
    }

    public function test_assigns_orderer_role_to_proposal(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $userId = 'U456DEF';

        $result = $this->action->handle($proposal, 'orderer', $userId);

        $this->assertTrue($result);
        $this->assertEquals($userId, $proposal->orderer_user_id);
        $this->assertEquals(ProposalStatus::Ordering, $proposal->status);
    }

    public function test_fails_when_runner_already_assigned(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withRunner('U_EXISTING')
            ->create();

        $result = $this->action->handle($proposal, 'runner', 'U_NEW_USER');

        $this->assertFalse($result);
        $this->assertEquals('U_EXISTING', $proposal->fresh()->runner_user_id);
    }

    public function test_fails_when_orderer_already_assigned(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withOrderer('U_EXISTING')
            ->create();

        $result = $this->action->handle($proposal, 'orderer', 'U_NEW_USER');

        $this->assertFalse($result);
        $this->assertEquals('U_EXISTING', $proposal->fresh()->orderer_user_id);
    }

    public function test_refreshes_proposal_on_successful_assignment(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create();
        $userId = 'U789GHI';

        $this->action->handle($proposal, 'runner', $userId);

        $this->assertEquals($userId, $proposal->runner_user_id);
        $this->assertEquals(ProposalStatus::Ordering, $proposal->status);
    }

    public function test_does_not_refresh_proposal_on_failed_assignment(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withRunner('U_EXISTING')
            ->create();
        $originalRunnerUserId = $proposal->runner_user_id;

        $result = $this->action->handle($proposal, 'runner', 'U_NEW_USER');

        $this->assertFalse($result);
        $this->assertEquals($originalRunnerUserId, $proposal->runner_user_id);
    }
}
