<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\DeleteQuickRunRequest;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeleteQuickRunRequestTest extends TestCase
{
    use RefreshDatabase;

    private DeleteQuickRunRequest $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteQuickRunRequest;
    }

    public function test_deletes_request(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->action->handle($request, 'U_OWNER');

        $this->assertDatabaseMissing('quick_run_requests', [
            'id' => $request->id,
        ]);
        $this->assertEquals(0, QuickRunRequest::count());
    }

    public function test_throws_exception_if_quick_run_is_locked(): void
    {
        $quickRun = QuickRun::factory()->locked()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de modifications.');

        $this->action->handle($request, 'U_OWNER');
    }

    public function test_throws_exception_if_quick_run_is_closed(): void
    {
        $quickRun = QuickRun::factory()->closed()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de modifications.');

        $this->action->handle($request, 'U_OWNER');
    }

    public function test_throws_exception_if_not_request_owner(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous ne pouvez supprimer que vos propres demandes.');

        $this->action->handle($request, 'U_OTHER_USER');
    }

    public function test_runner_cannot_delete_other_users_requests(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $request = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_CUSTOMER',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vous ne pouvez supprimer que vos propres demandes.');

        $this->action->handle($request, 'U_RUNNER');
    }

    public function test_deletes_only_specified_request(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request1 = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);
        $request2 = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->action->handle($request1, 'U_OWNER');

        $this->assertDatabaseMissing('quick_run_requests', [
            'id' => $request1->id,
        ]);
        $this->assertDatabaseHas('quick_run_requests', [
            'id' => $request2->id,
        ]);
        $this->assertEquals(1, QuickRunRequest::count());
    }

    public function test_user_can_delete_multiple_own_requests(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $request1 = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);
        $request2 = QuickRunRequest::factory()->for($quickRun)->create([
            'provider_user_id' => 'U_OWNER',
        ]);

        $this->action->handle($request1, 'U_OWNER');
        $this->action->handle($request2, 'U_OWNER');

        $this->assertEquals(0, QuickRunRequest::count());
    }
}
