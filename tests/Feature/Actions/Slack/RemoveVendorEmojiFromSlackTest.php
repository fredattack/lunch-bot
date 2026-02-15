<?php

namespace Tests\Feature\Actions\Slack;

use App\Actions\Slack\RemoveVendorEmojiFromSlack;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemoveVendorEmojiFromSlackTest extends TestCase
{
    use RefreshDatabase;

    private RemoveVendorEmojiFromSlack $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new RemoveVendorEmojiFromSlack;

        config(['services.slack.admin_token' => 'xoxp-test-token']);
    }

    public function test_skips_vendor_without_emoji_name(): void
    {
        $vendor = Vendor::factory()->create(['emoji_name' => null]);

        $this->action->handle($vendor);

        Http::assertNothingSent();
    }

    public function test_removes_emoji_and_clears_name(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.remove' => Http::response(['ok' => true]),
        ]);

        $vendor = Vendor::factory()->create(['emoji_name' => 'lb_quick']);

        $this->action->handle($vendor);

        $this->assertNull($vendor->fresh()->emoji_name);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'admin.emoji.remove')
                && $request['name'] === 'lb_quick';
        });
    }

    public function test_clears_emoji_name_even_when_emoji_not_found(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.remove' => Http::response(['ok' => false, 'error' => 'emoji_not_found']),
        ]);

        $vendor = Vendor::factory()->create(['emoji_name' => 'lb_deleted']);

        $this->action->handle($vendor);

        $this->assertNull($vendor->fresh()->emoji_name);
    }

    public function test_clears_emoji_name_on_api_failure(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.remove' => Http::response(['ok' => false, 'error' => 'ratelimited']),
        ]);

        $vendor = Vendor::factory()->create(['emoji_name' => 'lb_ratelimited']);

        $this->action->handle($vendor);

        $this->assertNull($vendor->fresh()->emoji_name);
    }

    public function test_handles_missing_admin_token_gracefully(): void
    {
        config(['services.slack.admin_token' => null]);

        $vendor = Vendor::factory()->create(['emoji_name' => 'lb_no_token']);

        $this->action->handle($vendor);

        $this->assertEquals('lb_no_token', $vendor->fresh()->emoji_name);

        Http::assertNothingSent();
    }
}
