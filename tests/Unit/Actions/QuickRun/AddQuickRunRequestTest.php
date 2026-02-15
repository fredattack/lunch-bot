<?php

namespace Tests\Unit\Actions\QuickRun;

use App\Actions\QuickRun\AddQuickRunRequest;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AddQuickRunRequestTest extends TestCase
{
    use RefreshDatabase;

    private AddQuickRunRequest $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AddQuickRunRequest;
    }

    public function test_adds_request_to_open_quick_run(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $userId = 'U_CUSTOMER';
        $data = [
            'description' => 'Large latte with oat milk',
            'price_estimated' => 5.50,
            'notes' => 'Extra hot',
        ];

        $request = $this->action->handle($quickRun, $userId, $data);

        $this->assertInstanceOf(QuickRunRequest::class, $request);
        $this->assertEquals($quickRun->id, $request->quick_run_id);
        $this->assertEquals($userId, $request->provider_user_id);
        $this->assertEquals('Large latte with oat milk', $request->description);
        $this->assertEquals(5.50, (float) $request->price_estimated);
        $this->assertEquals('Extra hot', $request->notes);
    }

    public function test_throws_exception_if_quick_run_is_locked(): void
    {
        $quickRun = QuickRun::factory()->locked()->create();
        $data = [
            'description' => 'Some item',
            'price_estimated' => 5.00,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de demandes.');

        $this->action->handle($quickRun, 'U_USER', $data);
    }

    public function test_throws_exception_if_quick_run_is_closed(): void
    {
        $quickRun = QuickRun::factory()->closed()->create();
        $data = [
            'description' => 'Some item',
            'price_estimated' => 5.00,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ce Quick Run n\'accepte plus de demandes.');

        $this->action->handle($quickRun, 'U_USER', $data);
    }

    public function test_throws_exception_if_runner_tries_to_add_request(): void
    {
        $quickRun = QuickRun::factory()->open()->create([
            'provider_user_id' => 'U_RUNNER',
        ]);
        $data = [
            'description' => 'My own item',
            'price_estimated' => 5.00,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le runner ne peut pas ajouter de demande a son propre Quick Run.');

        $this->action->handle($quickRun, 'U_RUNNER', $data);
    }

    public function test_price_estimated_is_optional(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $data = [
            'description' => 'Surprise me',
        ];

        $request = $this->action->handle($quickRun, 'U_USER', $data);

        $this->assertNull($request->price_estimated);
    }

    public function test_notes_are_optional(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $data = [
            'description' => 'Coffee',
            'price_estimated' => 4.00,
        ];

        $request = $this->action->handle($quickRun, 'U_USER', $data);

        $this->assertNull($request->notes);
    }

    public function test_allows_multiple_requests_from_different_users(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $data = [
            'description' => 'Same item',
            'price_estimated' => 5.00,
        ];

        $request1 = $this->action->handle($quickRun, 'U_USER1', $data);
        $request2 = $this->action->handle($quickRun, 'U_USER2', $data);

        $this->assertNotEquals($request1->id, $request2->id);
        $this->assertEquals(2, QuickRunRequest::count());
    }

    public function test_user_can_only_have_one_request_per_quick_run(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $data1 = [
            'description' => 'First item',
            'price_estimated' => 5.00,
        ];
        $data2 = [
            'description' => 'Second item',
            'price_estimated' => 7.00,
        ];

        $request1 = $this->action->handle($quickRun, 'U_USER', $data1);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->action->handle($quickRun, 'U_USER', $data2);
    }

    public function test_request_inherits_organization_from_quick_run(): void
    {
        $quickRun = QuickRun::factory()->open()->create();
        $data = [
            'description' => 'Coffee',
            'price_estimated' => 4.50,
        ];

        $request = $this->action->handle($quickRun, 'U_USER', $data);

        $this->assertEquals($quickRun->organization_id, $request->organization_id);
    }
}
