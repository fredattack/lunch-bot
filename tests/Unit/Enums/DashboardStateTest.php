<?php

namespace Tests\Unit\Enums;

use App\Enums\DashboardState;
use Tests\TestCase;

class DashboardStateTest extends TestCase
{
    public function test_label_returns_correct_text_for_each_case(): void
    {
        $this->assertEquals('Aucune commande', DashboardState::NoProposal->label());
        $this->assertEquals('Commandes ouvertes', DashboardState::OpenProposalsNoOrder->label());
        $this->assertEquals('Ma commande', DashboardState::HasOrder->label());
        $this->assertEquals('En charge', DashboardState::InCharge->label());
        $this->assertEquals('Tout cloture', DashboardState::AllClosed->label());
        $this->assertEquals('Historique', DashboardState::History->label());
    }

    public function test_is_today_returns_true_for_all_non_history_states(): void
    {
        $this->assertTrue(DashboardState::NoProposal->isToday());
        $this->assertTrue(DashboardState::OpenProposalsNoOrder->isToday());
        $this->assertTrue(DashboardState::HasOrder->isToday());
        $this->assertTrue(DashboardState::InCharge->isToday());
        $this->assertTrue(DashboardState::AllClosed->isToday());
    }

    public function test_is_today_returns_false_for_history(): void
    {
        $this->assertFalse(DashboardState::History->isToday());
    }

    public function test_allows_actions_returns_true_for_non_history(): void
    {
        $this->assertTrue(DashboardState::NoProposal->allowsActions());
        $this->assertTrue(DashboardState::InCharge->allowsActions());
    }

    public function test_allows_actions_returns_false_for_history(): void
    {
        $this->assertFalse(DashboardState::History->allowsActions());
    }

    public function test_all_cases_have_unique_values(): void
    {
        $values = array_map(fn ($case) => $case->value, DashboardState::cases());

        $this->assertCount(count($values), array_unique($values));
    }
}
