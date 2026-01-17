<?php

namespace Tests\Unit\Actions\VendorProposal;

use App\Actions\VendorProposal\DelegateRole;
use App\Models\LunchSession;
use App\Models\VendorProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegateRoleTest extends TestCase
{
    use RefreshDatabase;

    private DelegateRole $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DelegateRole;
    }

    public function test_delegates_runner_role(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withRunner('U_CURRENT_RUNNER')
            ->create();

        $result = $this->action->handle($proposal, 'runner', 'U_CURRENT_RUNNER', 'U_NEW_RUNNER');

        $this->assertTrue($result);
        $this->assertEquals('U_NEW_RUNNER', $proposal->fresh()->runner_user_id);
    }

    public function test_delegates_orderer_role(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withOrderer('U_CURRENT_ORDERER')
            ->create();

        $result = $this->action->handle($proposal, 'orderer', 'U_CURRENT_ORDERER', 'U_NEW_ORDERER');

        $this->assertTrue($result);
        $this->assertEquals('U_NEW_ORDERER', $proposal->fresh()->orderer_user_id);
    }

    public function test_fails_when_from_user_is_not_current_runner(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withRunner('U_ACTUAL_RUNNER')
            ->create();

        $result = $this->action->handle($proposal, 'runner', 'U_WRONG_USER', 'U_NEW_RUNNER');

        $this->assertFalse($result);
        $this->assertEquals('U_ACTUAL_RUNNER', $proposal->fresh()->runner_user_id);
    }

    public function test_fails_when_from_user_is_not_current_orderer(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()
            ->for($session)
            ->withOrderer('U_ACTUAL_ORDERER')
            ->create();

        $result = $this->action->handle($proposal, 'orderer', 'U_WRONG_USER', 'U_NEW_ORDERER');

        $this->assertFalse($result);
        $this->assertEquals('U_ACTUAL_ORDERER', $proposal->fresh()->orderer_user_id);
    }

    public function test_fails_when_role_is_not_assigned(): void
    {
        $session = LunchSession::factory()->open()->create();
        $proposal = VendorProposal::factory()->for($session)->create([
            'runner_user_id' => null,
        ]);

        $result = $this->action->handle($proposal, 'runner', 'U_SOME_USER', 'U_NEW_USER');

        $this->assertFalse($result);
        $this->assertNull($proposal->fresh()->runner_user_id);
    }
}
