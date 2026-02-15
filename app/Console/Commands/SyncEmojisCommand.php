<?php

namespace App\Console\Commands;

use App\Actions\Slack\SyncVendorEmojiToSlack;
use App\Models\Vendor;
use Illuminate\Console\Command;

class SyncEmojisCommand extends Command
{
    protected $signature = 'lunch:sync-emojis
                            {--vendor= : Sync a single vendor by ID}
                            {--force : Re-sync even if emoji_name is already set}';

    protected $description = 'Sync vendor logos as custom Slack emojis';

    public function handle(SyncVendorEmojiToSlack $action): int
    {
        $vendorId = $this->option('vendor');
        $force = $this->option('force');

        $query = Vendor::query()->whereHas('media', function ($q) {
            $q->where('collection_name', 'logo');
        });

        if ($vendorId) {
            $query->where('id', $vendorId);
        }

        if (! $force) {
            $query->whereNull('emoji_name');
        }

        $vendors = $query->get();

        if ($vendors->isEmpty()) {
            $this->info('No vendors to sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing {$vendors->count()} vendor(s)...");

        $successes = 0;
        $failures = 0;

        foreach ($vendors as $vendor) {
            $this->comment("Processing `{$vendor->name}`...");

            try {
                $action->handle($vendor);
                $this->info("  -> :{$vendor->fresh()->emoji_name}:");
                $successes++;
            } catch (\Throwable $e) {
                $this->error("  -> Failed: {$e->getMessage()}");
                $failures++;
            }

            if ($vendors->count() > 1) {
                usleep(3_000_000);
            }
        }

        $this->newLine();
        $this->info("Done. {$successes} succeeded, {$failures} failed.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
