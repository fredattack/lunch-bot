<?php

namespace Tests\Unit\Actions\LunchSession;

use App\Actions\LunchSession\LockExpiredSessions;
use App\Enums\LunchSessionStatus;
use App\Models\LunchSession;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LockExpiredSessionsTest extends TestCase
{
    use RefreshDatabase;

    private LockExpiredSessions $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new LockExpiredSessions;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_locks_sessions_past_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            ]);

        $result = $this->action->handle('UTC');

        $this->assertCount(1, $result);
        $this->assertEquals($session->id, $result->first()->id);
        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Locked->value,
        ]);
    }

    public function test_ignores_sessions_before_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 11:00:00', 'UTC'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            ]);

        $result = $this->action->handle('UTC');

        $this->assertCount(0, $result);
        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Open->value,
        ]);
    }

    public function test_ignores_locked_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->locked()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            ]);

        $result = $this->action->handle('UTC');

        $this->assertCount(0, $result);
    }

    public function test_ignores_closed_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->closed()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            ]);

        $result = $this->action->handle('UTC');

        $this->assertCount(0, $result);
    }

    public function test_respects_timezone_parameter(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'Europe/Paris'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'Europe/Paris'),
            ]);

        $result = $this->action->handle('Europe/Paris');

        $this->assertCount(1, $result);
        $this->assertEquals($session->id, $result->first()->id);
    }

    public function test_uses_config_timezone_when_null(): void
    {
        config(['lunch.timezone' => 'America/New_York']);
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'America/New_York'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'America/New_York'),
            ]);

        $result = $this->action->handle(null);

        $this->assertCount(1, $result);
    }

    public function test_returns_collection_of_locked_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));

        LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['deadline_at' => Carbon::parse('2025-01-15 11:00:00', 'UTC')]);

        LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create(['deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC')]);

        $result = $this->action->handle('UTC');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(LunchSession::class, $result);
    }

    public function test_locks_session_at_exact_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 11:30:00', 'UTC'));

        $session = LunchSession::factory()
            ->for($this->organization)
            ->open()
            ->create([
                'deadline_at' => Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            ]);

        $result = $this->action->handle('UTC');

        $this->assertCount(1, $result);
        $this->assertDatabaseHas('lunch_sessions', [
            'id' => $session->id,
            'status' => LunchSessionStatus::Locked->value,
        ]);
    }

    public function test_returns_empty_collection_when_no_sessions_to_lock(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15 12:00:00', 'UTC'));

        $result = $this->action->handle('UTC');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }
}
