<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ResolveOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_returns_400_when_team_id_missing(): void
    {
        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/events', 'POST', []);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing team_id', $response->getContent());
    }

    public function test_returns_403_when_organization_not_found(): void
    {
        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/events', 'POST', [
            'team_id' => 'T_UNKNOWN',
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Organization not found', $response->getContent());
    }

    public function test_returns_403_when_installation_not_found(): void
    {
        $org = Organization::factory()->create(['provider_team_id' => 'T123456']);

        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/events', 'POST', [
            'team_id' => 'T123456',
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('installation not found', $response->getContent());
    }

    public function test_sets_organization_in_context_on_success(): void
    {
        $org = Organization::factory()->withInstallation()->create(['provider_team_id' => 'T123456']);

        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/events', 'POST', [
            'team_id' => 'T123456',
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull(Organization::current());
        $this->assertEquals($org->id, Organization::current()->id);
    }

    public function test_extracts_team_id_from_event_payload(): void
    {
        $org = Organization::factory()->withInstallation()->create(['provider_team_id' => 'TEVENT123']);

        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/events', 'POST', [
            'event' => ['team' => 'TEVENT123', 'type' => 'message'],
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($org->id, Organization::current()->id);
    }

    public function test_extracts_team_id_from_interactivity_payload(): void
    {
        $org = Organization::factory()->withInstallation()->create(['provider_team_id' => 'TINTER123']);

        $payload = json_encode([
            'team' => ['id' => 'TINTER123', 'domain' => 'test'],
            'user' => ['id' => 'U123'],
        ]);

        $middleware = new ResolveOrganization;
        $request = Request::create('/api/slack/interactivity', 'POST', [
            'payload' => $payload,
        ]);

        $response = $middleware->handle($request, fn () => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($org->id, Organization::current()->id);
    }
}
