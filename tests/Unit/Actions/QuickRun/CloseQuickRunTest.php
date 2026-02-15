<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\CloseQuickRun;
use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CloseQuickRunTest extends TestCase
{
    use RefreshDatabase;

    private CloseQuickRun $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CloseQuickRun;
    }

    public function test_closes_open_quick_run(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $closed = $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertEquals(QuickRunStatus::Closed, $closed->status);
        $this->assertTrue($closed->isClosed());
        $this->assertFalse($closed->isOpen());
    }

    public function test_closes_locked_quick_run(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $closed = $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertEquals(QuickRunStatus::Closed, $closed->status);
        $this->assertTrue($closed->isClosed());
    }

    public function test_throws_exception_if_already_closed(): void
    {
        $quickRun = QuickRun::factory()->closed()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run est deja cloture.');

        $this->action->handle($quickRun, 'U_RUNNER');
    }

    public function test_throws_exception_if_not_runner(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seul le runner peut cloturer ce Quick Run.');

        $this->action->handle($quickRun, 'U_OTHER_USER');
    }

    public function test_adjusts_final_prices(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $request1 = QuickRunRequest::factory()->for($quickRun)->create([
            'price_estimated' => 5.00,
            'price_final' => null,
        ]);
        $request2 = QuickRunRequest::factory()->for($quickRun)->create([
            'price_estimated' => 7.00,
            'price_final' => null,
        ]);

        $priceAdjustments = [
            ['id' => $request1->id, 'price_final' => 5.50],
            ['id' => $request2->id, 'price_final' => 6.80],
        ];

        $this->action->handle($quickRun, 'U_RUNNER', $priceAdjustments);

        $request1->refresh();
        $request2->refresh();

        $this->assertEquals(5.50, (float) $request1->price_final);
        $this->assertEquals(6.80, (float) $request2->price_final);
    }

    public function test_ignores_price_adjustments_with_null_final_price(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'price_estimated' => 5.00,
            'price_final' => null,
        ]);

        $priceAdjustments = [
            ['id' => $request->id, 'price_final' => null],
        ];

        $this->action->handle($quickRun, 'U_RUNNER', $priceAdjustments);

        $request->refresh();

        $this->assertNull($request->price_final);
    }

    public function test_ignores_price_adjustments_for_non_existent_requests(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $priceAdjustments = [
            ['id' => 99999, 'price_final' => 10.00],
        ];

        $this->action->handle($quickRun, 'U_RUNNER', $priceAdjustments);

        $this->assertEquals(QuickRunStatus::Closed, $quickRun->fresh()->status);
    }

    public function test_closes_without_price_adjustments(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        QuickRunRequest::factory()->for($quickRun)->create();

        $closed = $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertEquals(QuickRunStatus::Closed, $closed->status);
    }

    public function test_closes_with_empty_price_adjustments_array(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $closed = $this->action->handle($quickRun, 'U_RUNNER', []);

        $this->assertEquals(QuickRunStatus::Closed, $closed->status);
    }

    public function test_persists_close_status_to_database(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);

        $this->action->handle($quickRun, 'U_RUNNER');

        $this->assertDatabaseHas('quick_runs', [
            'id' => $quickRun->id,
            'status' => QuickRunStatus::Closed->value,
        ]);
    }

    public function test_adjusts_some_prices_while_leaving_others_null(): void
    {
        $quickRun = QuickRun::factory()->locked()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $request1 = QuickRunRequest::factory()->for($quickRun)->create();
        $request2 = QuickRunRequest::factory()->for($quickRun)->create();

        $priceAdjustments = [
            ['id' => $request1->id, 'price_final' => 5.50],
        ];

        $this->action->handle($quickRun, 'U_RUNNER', $priceAdjustments);

        $request1->refresh();
        $request2->refresh();

        $this->assertEquals(5.50, (float) $request1->price_final);
        $this->assertNull($request2->price_final);
    }
}
