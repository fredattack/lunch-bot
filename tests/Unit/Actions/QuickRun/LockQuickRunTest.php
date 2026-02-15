<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\LockQuickRun;
use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LockQuickRunTest extends TestCase
{
    use RefreshDatabase;

    private LockQuickRun $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new LockQuickRun;
    }

    public function test_locks_open_quick_run(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $locked = $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertEquals(QuickRunStatus::Locked, $locked->status);
        $this->assertTrue($locked->isLocked());
        $this->assertFalse($locked->isOpen());
    }

    public function test_throws_exception_if_already_locked(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run est deja verrouille ou cloture.');

        $this->action->handle($quickRun, 'U_RUNNER');
    }

    public function test_throws_exception_if_already_closed(): void
    {
        $quickRun = QuickRun::factory()->closed()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run est deja verrouille ou cloture.');

        $this->action->handle($quickRun, 'U_RUNNER');
    }

    public function test_throws_exception_if_not_runner(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seul le runner peut verrouiller ce Quick Run.');

        $this->action->handle($quickRun, 'U_OTHER_USER');
    }

    public function test_allows_null_user_id_for_auto_lock(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $locked = $this->action->handle($quickRun, null);

        $this->assertEquals(QuickRunStatus::Locked, $locked->status);
        $this->assertTrue($locked->isLocked());
    }

    public function test_auto_lock_ignores_runner_check(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $locked = $this->action->handle($quickRun);

        $this->assertEquals(QuickRunStatus::Locked, $locked->status);
    }

    public function test_auto_lock_throws_exception_if_already_locked(): void
    {
        $quickRun = QuickRun::factory()->locked()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run est deja verrouille ou cloture.');

        $this->action->handle($quickRun, null);
    }

    public function test_persists_lock_status_to_database(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertDatabaseHas('quick_runs', [
            'id' => $quickRun->id,
            'status' => QuickRunStatus::Locked->value,
        ]);
    }
}
