<?php

namespace App\Jobs;

use App\Actions\Slack\SyncVendorEmojiToSlack;
use App\Models\Vendor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncVendorEmojiToSlackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public Vendor $vendor) {}

    public function handle(SyncVendorEmojiToSlack $action): void
    {
        $action->handle($this->vendor);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncVendorEmojiToSlackJob failed permanently', [
            'vendor_id' => $this->vendor->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
