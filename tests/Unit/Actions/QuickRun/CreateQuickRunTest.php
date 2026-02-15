<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\CreateQuickRun;
use App\Enums\QuickRunStatus;
use App\Models\Organization;
use App\Models\QuickRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateQuickRunTest extends TestCase
{
    use RefreshDatabase;

    private CreateQuickRun $action;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateQuickRun;
        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_creates_quick_run(): void
    {
        $userId = 'U_RUNNER';
        $channelId = 'C_CHANNEL';
        $data = [
            'destination' => 'Starbucks',
            'delay_minutes' => 15,
            'note' => 'Getting coffee',
        ];

        $quickRun = $this->action->handle($userId, $channelId, $data);

        $this->assertInstanceOf(QuickRun::class, $quickRun);
        $this->assertEquals($userId, $quickRun->provider_user_id);
        $this->assertEquals($channelId, $quickRun->provider_channel_id);
        $this->assertEquals('Starbucks', $quickRun->destination);
        $this->assertEquals('Getting coffee', $quickRun->note);
    }

    public function test_status_is_open(): void
    {
        $data = [
            'destination' => 'Bakery',
            'delay_minutes' => 20,
        ];

        $quickRun = $this->action->handle('U_RUNNER', 'C_CHANNEL', $data);

        $this->assertEquals(QuickRunStatus::Open, $quickRun->status);
        $this->assertTrue($quickRun->isOpen());
    }

    public function test_deadline_calculated_correctly(): void
    {
        $timezone = config('lunch.timezone', 'Europe/Paris');
        Carbon::setTestNow(Carbon::parse('2026-02-15 10:00:00', $timezone));

        $data = [
            'destination' => 'McDonald\'s',
            'delay_minutes' => 30,
        ];

        $quickRun = $this->action->handle('U_RUNNER', 'C_CHANNEL', $data);

        $expectedDeadline = Carbon::parse('2026-02-15 10:30:00', $timezone);
        $this->assertEquals($expectedDeadline->toDateTimeString(), $quickRun->deadline_at->toDateTimeString());
    }

    public function test_deadline_uses_configured_timezone(): void
    {
        config(['lunch.timezone' => 'America/New_York']);
        Carbon::setTestNow(Carbon::parse('2026-02-15 10:00:00', 'America/New_York'));

        $data = [
            'destination' => 'Pizza Place',
            'delay_minutes' => 45,
        ];

        $quickRun = $this->action->handle('U_RUNNER', 'C_CHANNEL', $data);

        $expectedDeadline = Carbon::parse('2026-02-15 10:45:00', 'America/New_York');
        $this->assertEquals($expectedDeadline->toDateTimeString(), $quickRun->deadline_at->toDateTimeString());
    }

    public function test_note_is_optional(): void
    {
        $data = [
            'destination' => 'Grocery Store',
            'delay_minutes' => 10,
        ];

        $quickRun = $this->action->handle('U_RUNNER', 'C_CHANNEL', $data);

        $this->assertNull($quickRun->note);
    }

    public function test_note_can_be_explicitly_null(): void
    {
        $data = [
            'destination' => 'Pharmacy',
            'delay_minutes' => 15,
            'note' => null,
        ];

        $quickRun = $this->action->handle('U_RUNNER', 'C_CHANNEL', $data);

        $this->assertNull($quickRun->note);
    }

    public function test_creates_multiple_quick_runs_for_same_user(): void
    {
        $userId = 'U_RUNNER';
        $data1 = [
            'destination' => 'Place A',
            'delay_minutes' => 15,
        ];
        $data2 = [
            'destination' => 'Place B',
            'delay_minutes' => 20,
        ];

        $quickRun1 = $this->action->handle($userId, 'C_CHANNEL', $data1);
        $quickRun2 = $this->action->handle($userId, 'C_CHANNEL', $data2);

        $this->assertNotEquals($quickRun1->id, $quickRun2->id);
        $this->assertEquals(2, QuickRun::count());
        $this->assertEquals('Place A', $quickRun1->destination);
        $this->assertEquals('Place B', $quickRun2->destination);
    }
}
