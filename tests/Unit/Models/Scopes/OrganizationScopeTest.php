<?php

namespace Tests\Unit\Models\Scopes;

use App\Models\LunchSession;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_scope_filters_by_current_organization(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        Organization::setCurrent($org1);
        LunchSession::factory()->for($org1)->create();

        Organization::setCurrent($org2);
        LunchSession::factory()->for($org2)->create();

        Organization::setCurrent($org1);
        $this->assertCount(1, LunchSession::all());

        Organization::setCurrent($org2);
        $this->assertCount(1, LunchSession::all());
    }

    public function test_scope_does_not_filter_when_no_current_organization(): void
    {
        $org1 = Organization::factory()->withInstallation()->create();
        $org2 = Organization::factory()->withInstallation()->create();

        Organization::setCurrent($org1);
        LunchSession::factory()->for($org1)->create();

        Organization::setCurrent($org2);
        LunchSession::factory()->for($org2)->create();

        Organization::setCurrent(null);
        $this->assertCount(2, LunchSession::all());
    }

    public function test_auto_assigns_organization_id_on_create(): void
    {
        $org = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($org);

        $session = LunchSession::factory()->create(['organization_id' => null]);

        $this->assertEquals($org->id, $session->organization_id);
    }
}
