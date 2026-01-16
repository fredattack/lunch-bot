<?php

namespace Tests\Unit\Models;

use App\Enums\LunchSessionStatus;
use App\Models\LunchSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LunchSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_open_returns_true_for_open_status(): void
    {
        $session = LunchSession::factory()->open()->create();

        $this->assertTrue($session->isOpen());
        $this->assertFalse($session->isLocked());
        $this->assertFalse($session->isClosed());
    }

    public function test_is_locked_returns_true_for_locked_status(): void
    {
        $session = LunchSession::factory()->locked()->create();

        $this->assertFalse($session->isOpen());
        $this->assertTrue($session->isLocked());
        $this->assertFalse($session->isClosed());
    }

    public function test_is_closed_returns_true_for_closed_status(): void
    {
        $session = LunchSession::factory()->closed()->create();

        $this->assertFalse($session->isOpen());
        $this->assertFalse($session->isLocked());
        $this->assertTrue($session->isClosed());
    }

    public function test_status_helpers_work_with_manually_created_model(): void
    {
        $session = new LunchSession;
        $session->status = LunchSessionStatus::Open;

        $this->assertTrue($session->isOpen());

        $session->status = LunchSessionStatus::Locked;
        $this->assertTrue($session->isLocked());

        $session->status = LunchSessionStatus::Closed;
        $this->assertTrue($session->isClosed());
    }
}
