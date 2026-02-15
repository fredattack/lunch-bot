<?php

namespace Tests\Feature\Actions\Slack;

use App\Actions\Slack\SyncVendorEmojiToSlack;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncVendorEmojiToSlackTest extends TestCase
{
    use RefreshDatabase;

    private SyncVendorEmojiToSlack $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SyncVendorEmojiToSlack;

        config(['services.slack.admin_token' => 'xoxp-test-token']);

        Queue::fake();
        Storage::fake('public');
    }

    public function test_skips_vendor_without_logo(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'No Logo Place']);

        $result = $this->action->handle($vendor);

        $this->assertNull($result->emoji_name);

        Http::assertNothingSent();
    }

    public function test_syncs_emoji_successfully(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.add' => Http::response(['ok' => true]),
        ]);

        $vendor = Vendor::factory()->create(['name' => 'Tatie Crouton']);
        $this->addLogoToVendor($vendor);

        $result = $this->action->handle($vendor);

        $this->assertEquals('lb_tatie_crouton', $result->emoji_name);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'admin.emoji.add')
                && $request['name'] === 'lb_tatie_crouton';
        });

        Storage::disk('public')->assertMissing('emoji-temp/lb_tatie_crouton.png');
    }

    public function test_handles_existing_emoji_by_removing_then_adding(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.add' => Http::sequence()
                ->push(['ok' => false, 'error' => 'error_name_taken'])
                ->push(['ok' => true]),
            'slack.com/api/admin.emoji.remove' => Http::response(['ok' => true]),
        ]);

        $vendor = Vendor::factory()->create(['name' => 'Quick']);
        $this->addLogoToVendor($vendor);

        $result = $this->action->handle($vendor);

        $this->assertEquals('lb_quick', $result->emoji_name);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'admin.emoji.remove')
                && $request['name'] === 'lb_quick';
        });
    }

    public function test_throws_on_api_failure(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.add' => Http::response(['ok' => false, 'error' => 'ratelimited']),
        ]);

        $vendor = Vendor::factory()->create(['name' => 'Failing Place']);
        $this->addLogoToVendor($vendor);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Slack admin.emoji.add failed: ratelimited');

        $this->action->handle($vendor);
    }

    public function test_throws_when_admin_token_not_configured(): void
    {
        config(['services.slack.admin_token' => null]);

        $vendor = Vendor::factory()->create(['name' => 'No Token']);
        $this->addLogoToVendor($vendor);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SLACK_ADMIN_TOKEN is not configured');

        $this->action->handle($vendor);
    }

    public function test_cleans_up_temporary_file_after_sync(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.add' => Http::response(['ok' => true]),
        ]);

        $vendor = Vendor::factory()->create(['name' => 'Cleanup Test']);
        $this->addLogoToVendor($vendor);

        $this->action->handle($vendor);

        Storage::disk('public')->assertMissing('emoji-temp/lb_cleanup_test.png');
    }

    public function test_cleans_up_temporary_file_on_failure(): void
    {
        Http::fake([
            'slack.com/api/admin.emoji.add' => Http::response(['ok' => false, 'error' => 'some_error']),
        ]);

        $vendor = Vendor::factory()->create(['name' => 'Fail Cleanup']);
        $this->addLogoToVendor($vendor);

        try {
            $this->action->handle($vendor);
        } catch (\RuntimeException) {
            // expected
        }

        Storage::disk('public')->assertMissing('emoji-temp/lb_fail_cleanup.png');
    }

    public function test_generate_emoji_name_basic(): void
    {
        $this->assertEquals('lb_quick', SyncVendorEmojiToSlack::generateEmojiName('Quick'));
        $this->assertEquals('lb_tatie_crouton', SyncVendorEmojiToSlack::generateEmojiName('Tatie Crouton'));
        $this->assertEquals('lb_laurent_dumont', SyncVendorEmojiToSlack::generateEmojiName('Laurent Dumont'));
    }

    public function test_generate_emoji_name_handles_special_characters(): void
    {
        $this->assertEquals('lb_cafe_de_la_gare', SyncVendorEmojiToSlack::generateEmojiName('Cafe de la Gare'));
        $this->assertEquals('lb_l_etoile_du_nord', SyncVendorEmojiToSlack::generateEmojiName("L'Etoile du Nord"));
        $this->assertEquals('lb_pizza_hut_express', SyncVendorEmojiToSlack::generateEmojiName('Pizza Hut (Express)'));
    }

    public function test_generate_emoji_name_handles_accented_characters(): void
    {
        $this->assertEquals('lb_boulangerie_eclair', SyncVendorEmojiToSlack::generateEmojiName('Boulangerie Eclair'));
    }

    public function test_generate_emoji_name_truncates_to_100_chars(): void
    {
        $longName = str_repeat('Restaurant ', 20);
        $result = SyncVendorEmojiToSlack::generateEmojiName($longName);

        $this->assertLessThanOrEqual(100, strlen($result));
        $this->assertStringStartsWith('lb_', $result);
    }

    private function addLogoToVendor(Vendor $vendor): void
    {
        $imagePath = $this->createTestImage();

        $vendor->addMedia($imagePath)
            ->usingFileName('logo.png')
            ->toMediaCollection('logo');
    }

    private function createTestImage(): string
    {
        $image = imagecreatetruecolor(200, 200);
        $color = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $color);

        $path = tempnam(sys_get_temp_dir(), 'test_logo_').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
