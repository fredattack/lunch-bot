<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\UpdateQuickRunRequest;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class UpdateQuickRunRequestTest extends TestCase
{
    use RefreshDatabase;

    private UpdateQuickRunRequest $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateQuickRunRequest;
    }

    public function test_updates_request_description(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'description' => 'Old description',
        ]);
        $data = [
            'description' => 'New description',
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertEquals('New description', $updated->description);
    }

    public function test_updates_request_price_estimated(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'price_estimated' => 5.00,
        ]);
        $data = [
            'price_estimated' => 7.50,
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertEquals(7.50, (float) $updated->price_estimated);
    }

    public function test_updates_request_notes(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'notes' => 'Old notes',
        ]);
        $data = [
            'notes' => 'New notes',
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertEquals('New notes', $updated->notes);
    }

    public function test_updates_multiple_fields_at_once(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'description' => 'Old',
            'price_estimated' => 5.00,
            'notes' => 'Old notes',
        ]);
        $data = [
            'description' => 'New description',
            'price_estimated' => 8.00,
            'notes' => 'New notes',
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertEquals('New description', $updated->description);
        $this->assertEquals(8.00, (float) $updated->price_estimated);
        $this->assertEquals('New notes', $updated->notes);
    }

    public function test_throws_exception_if_quick_run_is_locked(): void
    {
        $quickRun = QuickRun::factory()->locked()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);
        $data = [
            'description' => 'New description',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de modifications.');

        $this->action->handle($request, 'U_OWNER', $data);
    }

    public function test_throws_exception_if_quick_run_is_closed(): void
    {
        $quickRun = QuickRun::factory()->closed()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);
        $data = [
            'description' => 'New description',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de modifications.');

        $this->action->handle($request, 'U_OWNER', $data);
    }

    public function test_throws_exception_if_not_request_owner(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);
        $data = [
            'description' => 'New description',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous ne pouvez modifier que vos propres demandes.');

        $this->action->handle($request, 'U_OTHER_USER', $data);
    }

    public function test_runner_cannot_update_other_users_requests(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_CUSTOMER',
        ]);
        $data = [
            'description' => 'Modified by runner',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous ne pouvez modifier que vos propres demandes.');

        $this->action->handle($request, 'U_RUNNER', $data);
    }

    public function test_can_set_price_estimated_to_null(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'price_estimated' => 5.00,
        ]);
        $data = [
            'price_estimated' => null,
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertNull($updated->price_estimated);
    }

    public function test_can_set_notes_to_null(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
            'notes' => 'Some notes',
        ]);
        $data = [
            'notes' => null,
        ];

        $updated = $this->action->handle($request, 'U_OWNER', $data);

        $this->assertNull($updated->notes);
    }
}
