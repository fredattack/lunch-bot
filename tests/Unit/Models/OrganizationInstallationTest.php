<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use App\Models\OrganizationInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_belongs_to_organization(): void
    {
        $org = Organization::factory()->withInstallation()->create();
        $installation = $org->installation;

        $this->assertInstanceOf(Organization::class, $installation->organization);
        $this->assertEquals($org->id, $installation->organization->id);
    }

    public function test_bot_token_is_encrypted(): void
    {
        $org = Organization::factory()->create();
        $installation = OrganizationInstallation::factory()->for($org)->create([
            'bot_token' => 'xoxb-test-token-value',
        ]);

        $this->assertEquals('xoxb-test-token-value', $installation->bot_token);

        // Verify it's stored encrypted in DB (raw value differs)
        $raw = \DB::table('organization_installations')
            ->where('id', $installation->id)
            ->value('bot_token');

        $this->assertNotEquals('xoxb-test-token-value', $raw);
    }

    public function test_signing_secret_is_encrypted(): void
    {
        $org = Organization::factory()->create();
        $installation = OrganizationInstallation::factory()->for($org)->create([
            'signing_secret' => 'my-secret-value',
        ]);

        $this->assertEquals('my-secret-value', $installation->signing_secret);

        $raw = \DB::table('organization_installations')
            ->where('id', $installation->id)
            ->value('signing_secret');

        $this->assertNotEquals('my-secret-value', $raw);
    }

    public function test_scopes_casts_to_array(): void
    {
        $org = Organization::factory()->create();
        $installation = OrganizationInstallation::factory()->for($org)->create([
            'scopes' => ['chat:write', 'commands'],
        ]);

        $this->assertIsArray($installation->fresh()->scopes);
        $this->assertContains('chat:write', $installation->fresh()->scopes);
    }

    public function test_installed_at_casts_to_datetime(): void
    {
        $org = Organization::factory()->create();
        $installation = OrganizationInstallation::factory()->for($org)->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $installation->installed_at);
    }

    public function test_factory_creates_valid_installation(): void
    {
        $org = Organization::factory()->create();
        $installation = OrganizationInstallation::factory()->for($org)->create();

        $this->assertDatabaseHas('organization_installations', ['id' => $installation->id]);
        $this->assertNotNull($installation->bot_token);
        $this->assertNotNull($installation->signing_secret);
    }
}
