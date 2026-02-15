<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\LockExpiredQuickRuns;
use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockExpiredQuickRunsTest extends TestCase
{
    use RefreshDatabase;

    private LockExpiredQuickRuns $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new LockExpiredQuickRuns;
    }

    public function test_locks_expired_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        $expired = QuickRun::factory()->expired()->create();

        $result = $this->action->handle();

        $this->assertCount(1, $result);
        $this->assertEquals($expired->id, $result->first()->id);
        $this->assertEquals(QuickRunStatus::Locked, $expired->fresh()->status);
    }

    public function test_ignores_non_expired_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        QuickRun::factory()->open()->create([
            'deadline_at' => Carbon::parse('2026-02-15 12:30:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(0, $result);
        $this->assertEquals(1, QuickRun::where('status', QuickRunStatus::Open)->count());
    }

    public function test_locks_multiple_expired_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        $expired1 = QuickRun::factory()->expired()->create();
        $expired2 = QuickRun::factory()->expired()->create();
        $expired3 = QuickRun::factory()->expired()->create();

        $result = $this->action->handle();

        $this->assertCount(3, $result);
        $this->assertEquals(QuickRunStatus::Locked, $expired1->fresh()->status);
        $this->assertEquals(QuickRunStatus::Locked, $expired2->fresh()->status);
        $this->assertEquals(QuickRunStatus::Locked, $expired3->fresh()->status);
    }

    public function test_ignores_already_locked_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        QuickRun::factory()->locked()->create([
            'deadline_at' => Carbon::parse('2026-02-15 11:30:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(0, $result);
    }

    public function test_ignores_already_closed_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        QuickRun::factory()->closed()->create([
            'deadline_at' => Carbon::parse('2026-02-15 11:30:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(0, $result);
    }

    public function test_returns_collection(): void
    {
        $result = $this->action->handle();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_returns_empty_collection_when_no_expired_quick_runs(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        QuickRun::factory()->open()->create([
            'deadline_at' => Carbon::parse('2026-02-15 13:00:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_handles_deadline_at_exactly_now(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        $quickRun = QuickRun::factory()->open()->create([
            'deadline_at' => Carbon::parse('2026-02-15 12:00:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(1, $result);
        $this->assertEquals(QuickRunStatus::Locked, $quickRun->fresh()->status);
    }

    public function test_locks_only_open_expired_quick_runs_in_mixed_scenario(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        $expired1 = QuickRun::factory()->expired()->create();
        $expired2 = QuickRun::factory()->expired()->create();
        QuickRun::factory()->open()->create([
            'deadline_at' => Carbon::parse('2026-02-15 13:00:00'),
        ]);
        QuickRun::factory()->locked()->create([
            'deadline_at' => Carbon::parse('2026-02-15 11:00:00'),
        ]);
        QuickRun::factory()->closed()->create([
            'deadline_at' => Carbon::parse('2026-02-15 11:00:00'),
        ]);

        $result = $this->action->handle();

        $this->assertCount(2, $result);
        $this->assertTrue($result->contains($expired1));
        $this->assertTrue($result->contains($expired2));
    }

    public function test_persists_locked_status_to_database(): void
    {
        Carbon::setTestNow('2026-02-15 12:00:00');

        $expired = QuickRun::factory()->expired()->create();

        $this->action->handle();

        $this->assertDatabaseHas('quick_runs', [
            'id' => $expired->id,
            'status' => QuickRunStatus::Locked->value,
        ]);
    }
}
