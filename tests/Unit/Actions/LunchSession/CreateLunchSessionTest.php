<?php

namespace Tests\Unit\Actions\LunchSession;

use App\Actions\LunchSession\CreateLunchSession;
use App\Enums\LunchSessionStatus;
use App\Models\LunchSession;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateLunchSessionTest extends TestCase
{
    use RefreshDatabase;

    private CreateLunchSession $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateLunchSession;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_creates_new_session_for_date_and_channel(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(11, 30);

        $session = $this->action->handle($date, $channelId, $deadline);

        $this->assertInstanceOf(LunchSession::class, $session);
        $this->assertEquals($date, $session->date->toDateString());
        $this->assertEquals($channelId, $session->provider_channel_id);
        $this->assertEquals('slack', $session->provider);
        $this->assertTrue($deadline->eq($session->deadline_at));
        $this->assertEquals(LunchSessionStatus::Open, $session->status);
        $this->assertEquals($this->organization->id, $session->organization_id);
        $this->assertDatabaseCount('lunch_sessions', 1);
    }

    public function test_returns_existing_session_for_same_date_and_channel(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(11, 30);

        $firstSession = $this->action->handle($date, $channelId, $deadline);
        $secondSession = $this->action->handle($date, $channelId, $deadline);

        $this->assertEquals($firstSession->id, $secondSession->id);
        $this->assertDatabaseCount('lunch_sessions', 1);
    }

    public function test_updates_deadline_if_changed(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $originalDeadline = Carbon::today()->setTime(11, 30);
        $newDeadline = Carbon::today()->setTime(12, 0);

        $firstSession = $this->action->handle($date, $channelId, $originalDeadline);
        $secondSession = $this->action->handle($date, $channelId, $newDeadline);

        $this->assertEquals($firstSession->id, $secondSession->id);
        $this->assertTrue($newDeadline->eq($secondSession->deadline_at));
        $this->assertDatabaseCount('lunch_sessions', 1);
    }

    public function test_does_not_update_deadline_if_unchanged(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(11, 30);

        $firstSession = $this->action->handle($date, $channelId, $deadline);
        $originalUpdatedAt = $firstSession->updated_at;

        Carbon::setTestNow(Carbon::now()->addSecond());
        $secondSession = $this->action->handle($date, $channelId, $deadline);

        $secondSession->refresh();
        $this->assertEquals($originalUpdatedAt->toDateTimeString(), $secondSession->updated_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_creates_session_with_custom_provider(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(11, 30);

        $session = $this->action->handle($date, $channelId, $deadline, 'teams');

        $this->assertEquals('teams', $session->provider);
    }

    public function test_creates_separate_sessions_for_different_channels(): void
    {
        $date = Carbon::today()->toDateString();
        $deadline = Carbon::today()->setTime(11, 30);

        $session1 = $this->action->handle($date, 'C11111111', $deadline);
        $session2 = $this->action->handle($date, 'C22222222', $deadline);

        $this->assertNotEquals($session1->id, $session2->id);
        $this->assertDatabaseCount('lunch_sessions', 2);
    }

    public function test_creates_separate_sessions_for_different_dates(): void
    {
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(11, 30);

        $session1 = $this->action->handle(Carbon::today()->toDateString(), $channelId, $deadline);
        $session2 = $this->action->handle(Carbon::tomorrow()->toDateString(), $channelId, $deadline);

        $this->assertNotEquals($session1->id, $session2->id);
        $this->assertDatabaseCount('lunch_sessions', 2);
    }

    public function test_handles_deadline_in_the_past(): void
    {
        $date = Carbon::yesterday()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::yesterday()->setTime(11, 30);

        $session = $this->action->handle($date, $channelId, $deadline);

        $this->assertInstanceOf(LunchSession::class, $session);
        $this->assertEquals($date, $session->date->toDateString());
        $this->assertEquals(LunchSessionStatus::Open, $session->status);
    }

    public function test_handles_deadline_at_midnight_boundary(): void
    {
        $date = Carbon::today()->toDateString();
        $channelId = 'C12345678';
        $deadline = Carbon::today()->setTime(0, 0);

        $session = $this->action->handle($date, $channelId, $deadline);

        $this->assertInstanceOf(LunchSession::class, $session);
        $this->assertTrue($deadline->eq($session->deadline_at));
    }
}
