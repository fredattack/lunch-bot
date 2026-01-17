<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogRequestMiddlewareTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = storage_path('logs/requests-'.now()->format('Y-m-d').'.log');

        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }
    }

    public function test_logs_request_start_and_end_events(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeader('X-Request-ID');

        $this->assertFileExists($this->logPath);

        $logContent = File::get($this->logPath);

        $this->assertStringContainsString('request.start', $logContent);
        $this->assertStringContainsString('request.end', $logContent);
        $this->assertStringContainsString('[GET]', $logContent);
    }

    public function test_skips_excluded_paths(): void
    {
        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        $response = $this->get('/up');

        $response->assertStatus(200);
        $response->assertHeaderMissing('X-Request-ID');

        if (File::exists($this->logPath)) {
            $logContent = File::get($this->logPath);
            $this->assertStringNotContainsString('[GET] up', $logContent);
        }
    }

    public function test_request_id_header_is_uuid_format(): void
    {
        $response = $this->get('/');

        $requestId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $requestId);
    }

    public function test_logging_can_be_disabled_via_config(): void
    {
        config(['logging.request_logging.enabled' => false]);

        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeaderMissing('X-Request-ID');
    }

    public function test_skips_wildcard_excluded_paths(): void
    {
        config(['logging.request_logging.excluded_paths' => ['api/*']]);

        if (File::exists($this->logPath)) {
            File::delete($this->logPath);
        }

        $response = $this->post('/api/slack/events', [], [
            'X-Slack-Request-Timestamp' => time(),
            'X-Slack-Signature' => 'v0=test',
        ]);

        $response->assertHeaderMissing('X-Request-ID');
    }

    public function test_logs_contain_structured_data(): void
    {
        $response = $this->get('/?foo=bar');

        $response->assertStatus(200);

        $logContent = File::get($this->logPath);

        $this->assertStringContainsString('"method":"GET"', $logContent);
        $this->assertStringContainsString('"query":', $logContent);
        $this->assertStringContainsString('"foo":"bar"', $logContent);
    }

    public function test_logs_request_id_is_consistent(): void
    {
        $response = $this->get('/');

        $requestId = $response->headers->get('X-Request-ID');
        $logContent = File::get($this->logPath);

        $this->assertStringContainsString($requestId, $logContent);
    }

    public function test_sanitizes_sensitive_data(): void
    {
        config(['logging.request_logging.log_body' => true]);

        $response = $this->postJson('/', [
            'username' => 'john',
            'password' => 'secret123',
            'api_key' => 'my-key',
        ]);

        $logContent = File::get($this->logPath);

        $this->assertStringContainsString('"username":"john"', $logContent);
        $this->assertStringContainsString('***REDACTED***', $logContent);
        $this->assertStringNotContainsString('secret123', $logContent);
        $this->assertStringNotContainsString('my-key', $logContent);
    }
}
