<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\OrganizationInstallation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifySlackSignatureTest extends TestCase
{
    use RefreshDatabase;

    private string $signingSecret = 'test_signing_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();
        config(['slack.signing_secret' => $this->signingSecret]);
    }

    public function test_returns_401_when_timestamp_missing(): void
    {
        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->withHeaders([
                'X-Slack-Signature' => 'v0=abc123',
            ])
            ->postJson('/api/slack/events', ['type' => 'event_callback']);

        $response->assertStatus(401);
        $response->assertSee('Missing Slack signature headers');
    }

    public function test_returns_401_when_signature_missing(): void
    {
        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->withHeaders([
                'X-Slack-Request-Timestamp' => (string) time(),
            ])
            ->postJson('/api/slack/events', ['type' => 'event_callback']);

        $response->assertStatus(401);
        $response->assertSee('Missing Slack signature headers');
    }

    public function test_returns_401_when_timestamp_stale(): void
    {
        $staleTimestamp = time() - (6 * 60);
        $body = json_encode(['type' => 'event_callback']);
        $signature = $this->computeSignature($body, $staleTimestamp);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $staleTimestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertStatus(401);
        $response->assertSee('Stale Slack request');
    }

    public function test_returns_401_when_signature_invalid(): void
    {
        $timestamp = time();

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->withHeaders([
                'X-Slack-Request-Timestamp' => (string) $timestamp,
                'X-Slack-Signature' => 'v0=invalid_signature',
            ])
            ->postJson('/api/slack/events', ['type' => 'event_callback']);

        $response->assertStatus(401);
        $response->assertSee('Invalid Slack signature');
    }

    public function test_returns_500_when_signing_secret_missing(): void
    {
        config(['slack.signing_secret' => null]);
        $timestamp = time();

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->withHeaders([
                'X-Slack-Request-Timestamp' => (string) $timestamp,
                'X-Slack-Signature' => 'v0=something',
            ])
            ->postJson('/api/slack/events', ['type' => 'event_callback']);

        $response->assertStatus(500);
        $response->assertSee('Slack signing secret missing');
    }

    public function test_passes_with_valid_signature(): void
    {
        $timestamp = time();
        $body = json_encode(['type' => 'event_callback', 'event' => ['type' => 'message']]);
        $signature = $this->computeSignature($body, $timestamp);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertOk();
    }

    public function test_uses_org_specific_secret_when_available(): void
    {
        $orgSecret = 'org_specific_secret_abc';

        $organization = Organization::factory()->create([
            'provider' => 'slack',
            'provider_team_id' => 'T12345',
        ]);
        OrganizationInstallation::factory()->create([
            'organization_id' => $organization->id,
            'signing_secret' => $orgSecret,
        ]);

        $timestamp = time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T12345']);
        $signature = $this->computeSignature($body, $timestamp, $orgSecret);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertOk();
    }

    public function test_falls_back_to_config_secret_when_org_not_found(): void
    {
        $timestamp = time();
        $body = json_encode(['type' => 'event_callback', 'team_id' => 'T_UNKNOWN']);
        $signature = $this->computeSignature($body, $timestamp, $this->signingSecret);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertOk();
    }

    public function test_extracts_team_id_from_event_nested_structure(): void
    {
        $orgSecret = 'org_interactivity_secret';

        $organization = Organization::factory()->create([
            'provider' => 'slack',
            'provider_team_id' => 'T_INTERACT',
        ]);
        OrganizationInstallation::factory()->create([
            'organization_id' => $organization->id,
            'signing_secret' => $orgSecret,
        ]);

        $timestamp = time();
        $body = json_encode([
            'type' => 'event_callback',
            'team' => ['id' => 'T_INTERACT'],
            'event' => ['type' => 'message'],
        ]);
        $signature = $this->computeSignature($body, $timestamp, $orgSecret);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertOk();
    }

    public function test_accepts_timestamp_within_5_minute_window(): void
    {
        $timestamp = time() - (4 * 60);
        $body = json_encode(['type' => 'event_callback']);
        $signature = $this->computeSignature($body, $timestamp);

        $response = $this->withoutMiddleware(ResolveOrganization::class)
            ->call('POST', '/api/slack/events', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
                'HTTP_X_SLACK_SIGNATURE' => $signature,
            ], $body);

        $response->assertOk();
    }

    private function computeSignature(string $body, int $timestamp, ?string $secret = null): string
    {
        $secret = $secret ?? $this->signingSecret;
        $baseString = sprintf('v0:%s:%s', $timestamp, $body);

        return 'v0='.hash_hmac('sha256', $baseString, $secret);
    }
}
