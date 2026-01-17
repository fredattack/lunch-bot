<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\OrganizationInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization_with_factory(): void
    {
        $org = Organization::factory()->create();

        $this->assertDatabaseHas('organizations', [
            'id' => $org->id,
            'provider' => 'slack',
        ]);
    }

    public function test_organization_can_have_installation(): void
    {
        $org = Organization::factory()->withInstallation()->create();

        $this->assertNotNull($org->installation);
        $this->assertInstanceOf(OrganizationInstallation::class, $org->installation);
    }

    public function test_installation_has_encrypted_bot_token(): void
    {
        $org = Organization::factory()->withInstallation()->create();

        $this->assertStringStartsWith('xoxb-', $org->installation->bot_token);
    }

    public function test_current_returns_null_when_not_set(): void
    {
        $this->assertNull(Organization::current());
    }

    public function test_set_current_sets_organization_in_context(): void
    {
        $org = Organization::factory()->create();

        Organization::setCurrent($org);

        $this->assertNotNull(Organization::current());
        $this->assertEquals($org->id, Organization::current()->id);
    }

    public function test_set_current_with_null_clears_context(): void
    {
        $org = Organization::factory()->create();
        Organization::setCurrent($org);

        $this->assertNotNull(Organization::current());

        Organization::setCurrent(null);

        $this->assertNull(Organization::current());
    }

    public function test_organization_has_lunch_sessions_relation(): void
    {
        $org = Organization::factory()->create();

        $this->assertTrue(method_exists($org, 'lunchSessions'));
        $this->assertCount(0, $org->lunchSessions);
    }

    public function test_organization_has_vendors_relation(): void
    {
        $org = Organization::factory()->create();

        $this->assertTrue(method_exists($org, 'vendors'));
        $this->assertCount(0, $org->vendors);
    }

    public function test_settings_is_cast_as_array(): void
    {
        $org = Organization::factory()->create([
            'settings' => ['timezone' => 'America/New_York', 'locale' => 'en'],
        ]);

        $org->refresh();

        $this->assertIsArray($org->settings);
        $this->assertEquals('America/New_York', $org->settings['timezone']);
    }
}
