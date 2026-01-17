<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\VerifySlackSignature;
use App\Services\Slack\SlackInteractionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SlackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_returns_challenge_for_url_verification(): void
    {
        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->postJson('/api/slack/events', [
                'type' => 'url_verification',
                'challenge' => 'test_challenge_token',
            ]);

        $response->assertOk();
        $response->assertJson(['challenge' => 'test_challenge_token']);
    }

    public function test_events_ignores_retry_requests(): void
    {
        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('handleEvent');
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->withHeaders(['X-Slack-Retry-Num' => '1'])
            ->postJson('/api/slack/events', [
                'type' => 'event_callback',
                'event' => ['type' => 'app_mention'],
            ]);

        $response->assertOk();
        $response->assertSee('');
    }

    public function test_events_returns_200_on_valid_event(): void
    {
        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) {
            $mock->shouldReceive('handleEvent')
                ->once()
                ->with(['type' => 'event_callback', 'event' => ['type' => 'app_mention']]);
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->postJson('/api/slack/events', [
                'type' => 'event_callback',
                'event' => ['type' => 'app_mention'],
            ]);

        $response->assertOk();
    }

    public function test_events_delegates_to_handler(): void
    {
        $payload = [
            'type' => 'event_callback',
            'team_id' => 'T123',
            'event' => [
                'type' => 'message',
                'user' => 'U123',
                'text' => 'Hello',
            ],
        ];

        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('handleEvent')
                ->once()
                ->with($payload);
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->postJson('/api/slack/events', $payload);

        $response->assertOk();
    }

    public function test_interactivity_returns_400_on_invalid_json_payload(): void
    {
        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->post('/api/slack/interactivity', [
                'payload' => 'not valid json {{{',
            ]);

        $response->assertStatus(400);
        $response->assertSee('Invalid payload');
    }

    public function test_interactivity_returns_400_on_non_array_payload(): void
    {
        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->post('/api/slack/interactivity', [
                'payload' => json_encode('just a string'),
            ]);

        $response->assertStatus(400);
    }

    public function test_interactivity_ignores_retry_requests(): void
    {
        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('handleInteractivity');
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->withHeaders(['X-Slack-Retry-Num' => '1'])
            ->post('/api/slack/interactivity', [
                'payload' => json_encode(['type' => 'block_actions']),
            ]);

        $response->assertOk();
    }

    public function test_interactivity_delegates_to_handler(): void
    {
        $payload = [
            'type' => 'block_actions',
            'user' => ['id' => 'U123'],
            'actions' => [
                ['action_id' => 'test_action', 'value' => 'test'],
            ],
        ];

        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('handleInteractivity')
                ->once()
                ->with($payload)
                ->andReturn(response('', 200));
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->post('/api/slack/interactivity', [
                'payload' => json_encode($payload),
            ]);

        $response->assertOk();
    }

    public function test_interactivity_returns_handler_response(): void
    {
        $payload = [
            'type' => 'view_submission',
            'view' => ['callback_id' => 'test_callback'],
        ];

        $handlerResponse = response()->json(['response_action' => 'errors', 'errors' => ['field' => 'Error']], 200);

        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) use ($payload, $handlerResponse) {
            $mock->shouldReceive('handleInteractivity')
                ->once()
                ->with($payload)
                ->andReturn($handlerResponse);
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->post('/api/slack/interactivity', [
                'payload' => json_encode($payload),
            ]);

        $response->assertOk();
        $response->assertJson(['response_action' => 'errors', 'errors' => ['field' => 'Error']]);
    }

    public function test_events_handles_empty_challenge(): void
    {
        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->postJson('/api/slack/events', [
                'type' => 'url_verification',
            ]);

        $response->assertOk();
        $response->assertJson(['challenge' => '']);
    }

    public function test_interactivity_handles_missing_payload_as_empty_object(): void
    {
        $this->mock(SlackInteractionHandler::class, function (MockInterface $mock) {
            $mock->shouldReceive('handleInteractivity')
                ->once()
                ->with([])
                ->andReturn(response('', 200));
        });

        $response = $this->withoutMiddleware([VerifySlackSignature::class, ResolveOrganization::class])
            ->post('/api/slack/interactivity', []);

        $response->assertOk();
    }
}
