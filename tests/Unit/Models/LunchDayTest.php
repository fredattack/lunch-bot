<?php

namespace Tests\Unit\Models;

use App\Enums\LunchDayStatus;
use App\Models\LunchDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LunchDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_returns_true_for_open_status(): void
    {
        $day = LunchDay::factory()->open()->create();

        $this->assertTrue($day->isOpen());
        $this->assertFalse($day->isLocked());
        $this->assertFalse($day->isClosed());
    }

    public function test_is_locked_returns_true_for_locked_status(): void
    {
        $day = LunchDay::factory()->locked()->create();

        $this->assertFalse($day->isOpen());
        $this->assertTrue($day->isLocked());
        $this->assertFalse($day->isClosed());
    }

    public function test_is_closed_returns_true_for_closed_status(): void
    {
        $day = LunchDay::factory()->closed()->create();

        $this->assertFalse($day->isOpen());
        $this->assertFalse($day->isLocked());
        $this->assertTrue($day->isClosed());
    }

    public function test_status_helpers_work_with_manually_created_model(): void
    {
        $day = new LunchDay;
        $day->status = LunchDayStatus::Open;

        $this->assertTrue($day->isOpen());

        $day->status = LunchDayStatus::Locked;
        $this->assertTrue($day->isLocked());

        $day->status = LunchDayStatus::Closed;
        $this->assertTrue($day->isClosed());
    }
}
