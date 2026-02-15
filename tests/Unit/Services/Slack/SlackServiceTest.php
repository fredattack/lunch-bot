<?php

namespace Tests\Unit\Services\Slack;

use App\Models\Organization;
use App\Services\Slack\SlackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SlackServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlackService $service;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->withInstallation()->create();
        Organization::setCurrent($this->organization);

        $this->service = new SlackService;
    }

    protected function tearDown(): void
    {
        Organization::setCurrent(null);
        parent::tearDown();
    }

    public function test_post_message_sends_correct_payload(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'ts' => '1234.5678']),
        ]);

        $result = $this->service->postMessage('C123', 'Hello', [['type' => 'section']]);

        $this->assertTrue($result['ok']);
        $this->assertEquals('1234.5678', $result['ts']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['channel'] === 'C123'
                && $body['text'] === 'Hello'
                && $body['blocks'] === [['type' => 'section']]
                && ! isset($body['thread_ts']);
        });
    }

    public function test_post_message_includes_thread_ts_when_provided(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $this->service->postMessage('C123', 'Reply', [], '9999.0000');

        Http::assertSent(function ($request) {
            return $request->data()['thread_ts'] === '9999.0000';
        });
    }

    public function test_post_message_omits_thread_ts_when_null(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $this->service->postMessage('C123', 'No thread', []);

        Http::assertSent(function ($request) {
            return ! isset($request->data()['thread_ts']);
        });
    }

    public function test_update_message_sends_correct_payload(): void
    {
        Http::fake([
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $result = $this->service->updateMessage('C123', '1234.5678', 'Updated', [['type' => 'section']]);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['channel'] === 'C123'
                && $body['ts'] === '1234.5678'
                && $body['text'] === 'Updated'
                && $body['blocks'] === [['type' => 'section']];
        });
    }

    public function test_post_ephemeral_sends_correct_payload(): void
    {
        Http::fake([
            'slack.com/api/chat.postEphemeral' => Http::response(['ok' => true]),
        ]);

        $result = $this->service->postEphemeral('C123', 'U456', 'Only you see this');

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['channel'] === 'C123'
                && $body['user'] === 'U456'
                && $body['text'] === 'Only you see this'
                && ! isset($body['thread_ts']);
        });
    }

    public function test_post_ephemeral_includes_thread_ts(): void
    {
        Http::fake([
            'slack.com/api/chat.postEphemeral' => Http::response(['ok' => true]),
        ]);

        $this->service->postEphemeral('C123', 'U456', 'Thread reply', [], '8888.0000');

        Http::assertSent(function ($request) {
            return $request->data()['thread_ts'] === '8888.0000';
        });
    }

    public function test_open_modal_sends_trigger_and_view(): void
    {
        Http::fake([
            'slack.com/api/views.open' => Http::response(['ok' => true]),
        ]);

        $view = ['type' => 'modal', 'title' => ['type' => 'plain_text', 'text' => 'Test']];
        $result = $this->service->openModal('trigger_123', $view);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['trigger_id'] === 'trigger_123'
                && $body['view']['type'] === 'modal';
        });
    }

    public function test_push_modal_sends_trigger_and_view(): void
    {
        Http::fake([
            'slack.com/api/views.push' => Http::response(['ok' => true]),
        ]);

        $view = ['type' => 'modal'];
        $result = $this->service->pushModal('trigger_456', $view);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['trigger_id'] === 'trigger_456'
                && $body['view']['type'] === 'modal';
        });
    }

    public function test_update_modal_sends_view_id_and_view(): void
    {
        Http::fake([
            'slack.com/api/views.update' => Http::response(['ok' => true]),
        ]);

        $view = ['type' => 'modal'];
        $result = $this->service->updateModal('V_VIEW_123', $view);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['view_id'] === 'V_VIEW_123'
                && $body['view']['type'] === 'modal';
        });
    }

    public function test_users_info_returns_user_data(): void
    {
        Http::fake([
            'slack.com/api/users.info' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U123', 'name' => 'testuser', 'is_admin' => false],
            ]),
        ]);

        $result = $this->service->usersInfo('U123');

        $this->assertIsArray($result);
        $this->assertEquals('U123', $result['id']);
        $this->assertEquals('testuser', $result['name']);
    }

    public function test_users_info_returns_null_on_api_error(): void
    {
        Http::fake([
            'slack.com/api/users.info' => Http::response(['ok' => false, 'error' => 'user_not_found']),
        ]);

        $result = $this->service->usersInfo('U_INVALID');

        $this->assertNull($result);
    }

    public function test_team_info_returns_team_data(): void
    {
        Http::fake([
            'slack.com/api/team.info' => Http::response([
                'ok' => true,
                'team' => ['id' => 'T123', 'name' => 'TestTeam'],
            ]),
        ]);

        $result = $this->service->teamInfo();

        $this->assertIsArray($result);
        $this->assertEquals('T123', $result['id']);
    }

    public function test_team_info_returns_null_on_api_error(): void
    {
        Http::fake([
            'slack.com/api/team.info' => Http::response(['ok' => false, 'error' => 'missing_scope']),
        ]);

        $result = $this->service->teamInfo();

        $this->assertNull($result);
    }

    public function test_is_admin_returns_true_for_configured_admin(): void
    {
        config(['slack.admin_user_ids' => ['U_ADMIN_CONFIG']]);

        $result = $this->service->isAdmin('U_ADMIN_CONFIG');

        $this->assertTrue($result);
    }

    public function test_is_admin_returns_true_for_slack_admin(): void
    {
        config(['slack.admin_user_ids' => []]);

        Http::fake([
            'slack.com/api/users.info' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U_SLACK_ADMIN', 'is_admin' => true, 'is_owner' => false],
            ]),
        ]);

        $result = $this->service->isAdmin('U_SLACK_ADMIN');

        $this->assertTrue($result);
    }

    public function test_is_admin_returns_true_for_slack_owner(): void
    {
        config(['slack.admin_user_ids' => []]);

        Http::fake([
            'slack.com/api/users.info' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U_OWNER', 'is_admin' => false, 'is_owner' => true],
            ]),
        ]);

        $result = $this->service->isAdmin('U_OWNER');

        $this->assertTrue($result);
    }

    public function test_is_admin_returns_false_for_regular_user(): void
    {
        config(['slack.admin_user_ids' => []]);

        Http::fake([
            'slack.com/api/users.info' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U_REGULAR', 'is_admin' => false, 'is_owner' => false],
            ]),
        ]);

        $result = $this->service->isAdmin('U_REGULAR');

        $this->assertFalse($result);
    }

    public function test_get_file_info_returns_file_data(): void
    {
        Http::fake([
            'slack.com/api/files.info' => Http::response([
                'ok' => true,
                'file' => ['id' => 'F123', 'name' => 'logo.png'],
            ]),
        ]);

        $result = $this->service->getFileInfo('F123');

        $this->assertIsArray($result);
        $this->assertEquals('F123', $result['id']);
    }

    public function test_get_file_info_returns_null_on_error(): void
    {
        Http::fake([
            'slack.com/api/files.info' => Http::response(['ok' => false, 'error' => 'file_not_found']),
        ]);

        $result = $this->service->getFileInfo('F_INVALID');

        $this->assertNull($result);
    }

    public function test_download_file_saves_to_temp_path(): void
    {
        Http::fake([
            'https://files.slack.com/*' => Http::response('file-content-here', 200),
        ]);

        $tempPath = $this->service->downloadFile('https://files.slack.com/files-pri/T123/logo.png');

        $this->assertNotNull($tempPath);
        $this->assertFileExists($tempPath);
        $this->assertEquals('file-content-here', file_get_contents($tempPath));

        @unlink($tempPath);
    }

    public function test_download_file_returns_null_on_http_error(): void
    {
        Http::fake([
            'https://files.slack.com/*' => Http::response('Not Found', 404),
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->service->downloadFile('https://files.slack.com/files-pri/T123/missing.png');

        $this->assertNull($result);
    }

    public function test_api_returns_error_when_token_missing(): void
    {
        Organization::setCurrent(null);
        config(['slack.bot_token' => null]);

        Log::shouldReceive('error')->once()->with('Slack bot token missing.');

        $result = $this->service->postMessage('C123', 'Hello');

        $this->assertFalse($result['ok']);
        $this->assertEquals('missing_token', $result['error']);
    }

    public function test_resolve_token_prefers_organization_installation_token(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        config(['slack.bot_token' => 'xoxb-config-fallback']);

        $this->service->postMessage('C123', 'Hello');

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';

            return str_contains($authHeader, 'Bearer ')
                && ! str_contains($authHeader, 'xoxb-config-fallback');
        });
    }

    public function test_api_logs_warning_for_unexpected_errors(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'too_many_attachments']),
        ]);

        Log::shouldReceive('warning')->once()->with('Slack API error.', \Mockery::type('array'));

        $this->service->postMessage('C123', 'Hello');
    }

    public function test_api_skips_log_for_expected_errors(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => false, 'error' => 'channel_not_found']),
        ]);

        Log::shouldReceive('warning')->never();

        $this->service->postMessage('C123', 'Hello');
    }

    public function test_download_file_returns_null_when_token_missing(): void
    {
        Organization::setCurrent(null);
        config(['slack.bot_token' => null]);

        $result = $this->service->downloadFile('https://files.slack.com/test');

        $this->assertNull($result);
    }
}
