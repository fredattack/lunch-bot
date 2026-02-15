<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderingMode;
use Tests\TestCase;

class OrderingModeTest extends TestCase
{
    public function test_individual_label_returns_correct_text(): void
    {
        $this->assertEquals('Commandes individuelles', OrderingMode::Individual->label());
    }

    public function test_shared_label_returns_correct_text(): void
    {
        $this->assertStringContainsString('Commande group', OrderingMode::Shared->label());
    }

    public function test_all_cases_have_labels(): void
    {
        foreach (OrderingMode::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }
}
