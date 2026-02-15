<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SlackDumpUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_command_creates_json_file_on_success(): void
    {
        Http::fake([
            'slack.com/api/users.list*' => Http::response([
                'ok' => true,
                'members' => [
                    [
                        'id' => 'U123',
                        'team_id' => 'T123',
                        'name' => 'testuser',
                        'real_name' => 'Test User',
                        'deleted' => false,
                        'is_bot' => false,
                        'profile' => [
                            'display_name' => 'tester',
                            'email' => 'test@example.com',
                            'image_48' => 'https://example.com/avatar.png',
                        ],
                    ],
                ],
                'response_metadata' => ['next_cursor' => ''],
            ]),
        ]);

        $this->artisan('slack:dump-users', ['--token' => 'xoxb-test-token'])
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('slack/users.json');
        $content = json_decode(Storage::disk('local')->get('slack/users.json'), true);
        $this->assertEquals(1, $content['count']);
        $this->assertEquals('U123', $content['users'][0]['id']);
    }

    public function test_command_excludes_bots_when_flag_set(): void
    {
        Http::fake([
            'slack.com/api/users.list*' => Http::response([
                'ok' => true,
                'members' => [
                    ['id' => 'U1', 'name' => 'human', 'is_bot' => false, 'deleted' => false, 'profile' => []],
                    ['id' => 'U2', 'name' => 'bot', 'is_bot' => true, 'deleted' => false, 'profile' => []],
                ],
                'response_metadata' => ['next_cursor' => ''],
            ]),
        ]);

        $this->artisan('slack:dump-users', ['--token' => 'xoxb-test', '--exclude-bots' => true])
            ->assertExitCode(0);

        $content = json_decode(Storage::disk('local')->get('slack/users.json'), true);
        $this->assertEquals(1, $content['count']);
        $this->assertEquals('U1', $content['users'][0]['id']);
    }

    public function test_command_excludes_deleted_when_flag_set(): void
    {
        Http::fake([
            'slack.com/api/users.list*' => Http::response([
                'ok' => true,
                'members' => [
                    ['id' => 'U1', 'name' => 'active', 'is_bot' => false, 'deleted' => false, 'profile' => []],
                    ['id' => 'U2', 'name' => 'deleted', 'is_bot' => false, 'deleted' => true, 'profile' => []],
                ],
                'response_metadata' => ['next_cursor' => ''],
            ]),
        ]);

        $this->artisan('slack:dump-users', ['--token' => 'xoxb-test', '--exclude-deleted' => true])
            ->assertExitCode(0);

        $content = json_decode(Storage::disk('local')->get('slack/users.json'), true);
        $this->assertEquals(1, $content['count']);
        $this->assertEquals('U1', $content['users'][0]['id']);
    }

    public function test_command_fails_without_token(): void
    {
        Http::fake();

        // Make sure there's no env token
        $originalToken = env('SLACK_BOT_TOKEN');
        putenv('SLACK_BOT_TOKEN=');

        try {
            $this->artisan('slack:dump-users')
                ->assertExitCode(1);
        } finally {
            // Restore original token
            if ($originalToken) {
                putenv("SLACK_BOT_TOKEN={$originalToken}");
            }
        }
    }

    public function test_command_handles_api_error(): void
    {
        Http::fake([
            'slack.com/api/users.list*' => Http::response([
                'ok' => false,
                'error' => 'invalid_auth',
            ]),
        ]);

        $this->artisan('slack:dump-users', ['--token' => 'xoxb-bad-token'])
            ->assertExitCode(1);
    }

    public function test_command_paginates_results(): void
    {
        Http::fake([
            'slack.com/api/users.list*' => Http::sequence()
                ->push([
                    'ok' => true,
                    'members' => [
                        ['id' => 'U1', 'name' => 'user1', 'is_bot' => false, 'deleted' => false, 'profile' => []],
                    ],
                    'response_metadata' => ['next_cursor' => 'cursor_page2'],
                ])
                ->push([
                    'ok' => true,
                    'members' => [
                        ['id' => 'U2', 'name' => 'user2', 'is_bot' => false, 'deleted' => false, 'profile' => []],
                    ],
                    'response_metadata' => ['next_cursor' => ''],
                ]),
        ]);

        $this->artisan('slack:dump-users', ['--token' => 'xoxb-test'])
            ->assertExitCode(0);

        $content = json_decode(Storage::disk('local')->get('slack/users.json'), true);
        $this->assertEquals(2, $content['count']);
    }
}
