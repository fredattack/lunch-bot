<?php

namespace Tests\Unit\Models;

use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LunchDayProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_role_returns_true_for_runner(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withRunner('U_RUNNER')
            ->create();

        $this->assertTrue($proposal->hasRole('U_RUNNER'));
        $this->assertFalse($proposal->hasRole('U_OTHER'));
    }

    public function test_has_role_returns_true_for_orderer(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withOrderer('U_ORDERER')
            ->create();

        $this->assertTrue($proposal->hasRole('U_ORDERER'));
        $this->assertFalse($proposal->hasRole('U_OTHER'));
    }

    public function test_has_role_returns_true_when_user_has_both_roles(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->create([
                'runner_user_id' => 'U_BOTH',
                'orderer_user_id' => 'U_BOTH',
            ]);

        $this->assertTrue($proposal->hasRole('U_BOTH'));
    }

    public function test_get_role_for_returns_runner(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withRunner('U_RUNNER')
            ->create();

        $this->assertEquals('runner', $proposal->getRoleFor('U_RUNNER'));
    }

    public function test_get_role_for_returns_orderer(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withOrderer('U_ORDERER')
            ->create();

        $this->assertEquals('orderer', $proposal->getRoleFor('U_ORDERER'));
    }

    public function test_get_role_for_returns_null_for_non_role_user(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()->for($day)->create();

        $this->assertNull($proposal->getRoleFor('U_RANDOM'));
    }

    public function test_get_role_for_prioritizes_runner_over_orderer(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->create([
                'runner_user_id' => 'U_BOTH',
                'orderer_user_id' => 'U_BOTH',
            ]);

        $this->assertEquals('runner', $proposal->getRoleFor('U_BOTH'));
    }

    public function test_is_runner_returns_true_for_runner(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withRunner('U_RUNNER')
            ->create();

        $this->assertTrue($proposal->isRunner('U_RUNNER'));
        $this->assertFalse($proposal->isRunner('U_OTHER'));
    }

    public function test_is_orderer_returns_true_for_orderer(): void
    {
        $day = LunchDay::factory()->open()->create();
        $proposal = LunchDayProposal::factory()
            ->for($day)
            ->withOrderer('U_ORDERER')
            ->create();

        $this->assertTrue($proposal->isOrderer('U_ORDERER'));
        $this->assertFalse($proposal->isOrderer('U_OTHER'));
    }
}
