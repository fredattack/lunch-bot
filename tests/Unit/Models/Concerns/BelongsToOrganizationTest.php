<?php

namespace Tests\Unit\Models\Concerns;

use App\Models\LunchSession;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_model_has_organization_relation(): void
    {
        $session = LunchSession::factory()->create();

        $this->assertTrue(method_exists($session, 'organization'));
        $this->assertInstanceOf(Organization::class, $session->organization);
    }

    public function test_auto_assigns_organization_id_when_context_is_set(): void
    {
        $org = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($org);

        $vendor = Vendor::factory()->make(['organization_id' => null]);
        $vendor->save();

        $this->assertEquals($org->id, $vendor->organization_id);
    }

    public function test_does_not_override_explicit_organization_id(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        Organization::setCurrent($org1);

        $vendor = Vendor::factory()->create(['organization_id' => $org2->id]);

        $this->assertEquals($org2->id, $vendor->organization_id);
    }

    public function test_global_scope_filters_by_current_organization(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        $session1 = LunchSession::factory()->create(['organization_id' => $org1->id]);
        $session2 = LunchSession::factory()->create(['organization_id' => $org2->id]);

        Organization::setCurrent($org1);

        $sessions = LunchSession::all();

        $this->assertCount(1, $sessions);
        $this->assertEquals($session1->id, $sessions->first()->id);
    }

    public function test_no_scope_applied_when_no_current_organization(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        LunchSession::factory()->create(['organization_id' => $org1->id]);
        LunchSession::factory()->create(['organization_id' => $org2->id]);

        Organization::setCurrent(null);

        $sessions = LunchSession::all();

        $this->assertCount(2, $sessions);
    }

    public function test_can_bypass_scope_with_without_global_scopes(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        LunchSession::factory()->create(['organization_id' => $org1->id]);
        LunchSession::factory()->create(['organization_id' => $org2->id]);

        Organization::setCurrent($org1);

        $allSessions = LunchSession::withoutGlobalScopes()->get();

        $this->assertCount(2, $allSessions);
    }
}
