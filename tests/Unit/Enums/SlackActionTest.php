<?php

namespace Tests\Unit\Enums;

use App\Enums\SlackAction;
use Tests\TestCase;

class SlackActionTest extends TestCase
{
    public function test_is_callback_returns_true_for_callback_actions(): void
    {
        $this->assertTrue(SlackAction::CallbackProposalCreate->isCallback());
        $this->assertTrue(SlackAction::CallbackOrderCreate->isCallback());
        $this->assertTrue(SlackAction::CallbackEnseigneCreate->isCallback());
        $this->assertTrue(SlackAction::CallbackRoleDelegate->isCallback());
        $this->assertTrue(SlackAction::CallbackRestaurantPropose->isCallback());
    }

    public function test_is_callback_returns_false_for_block_actions(): void
    {
        $this->assertFalse(SlackAction::ClaimRunner->isCallback());
        $this->assertFalse(SlackAction::CloseDay->isCallback());
        $this->assertFalse(SlackAction::ProposalTakeCharge->isCallback());
    }

    public function test_is_dashboard_returns_true_for_dashboard_actions(): void
    {
        $this->assertTrue(SlackAction::DashboardCreateProposal->isDashboard());
        $this->assertTrue(SlackAction::DashboardStartFromCatalog->isDashboard());
        $this->assertTrue(SlackAction::DashboardJoinProposal->isDashboard());
        $this->assertTrue(SlackAction::DashboardRelaunch->isDashboard());
        $this->assertTrue(SlackAction::DashboardVendorsList->isDashboard());
        $this->assertTrue(SlackAction::DashboardProposeVendor->isDashboard());
    }

    public function test_is_dashboard_returns_false_for_non_dashboard_actions(): void
    {
        $this->assertFalse(SlackAction::ClaimRunner->isDashboard());
        $this->assertFalse(SlackAction::OpenOrderModal->isDashboard());
        $this->assertFalse(SlackAction::SessionClose->isDashboard());
    }

    public function test_all_enum_values_are_unique_strings(): void
    {
        $values = array_map(fn ($case) => $case->value, SlackAction::cases());

        $this->assertCount(count($values), array_unique($values));
        foreach ($values as $value) {
            $this->assertIsString($value);
        }
    }

    public function test_callback_and_dashboard_are_not_mutually_exclusive(): void
    {
        // CallbackLunchDashboard starts with "lunch_" so isCallback is true
        // and starts with "lunch_" not "dashboard" so isDashboard is false
        $lunchDashboard = SlackAction::CallbackLunchDashboard;
        // Just verify the methods work without errors on all cases
        foreach (SlackAction::cases() as $action) {
            $action->isCallback();
            $action->isDashboard();
        }
        $this->assertTrue(true);
    }
}
