<?php

namespace App\Observers;

use App\Actions\Slack\RemoveVendorEmojiFromSlack;
use App\Jobs\SyncVendorEmojiToSlackJob;
use App\Models\Vendor;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VendorMediaObserver
{
    public function created(Media $media): void
    {
        $this->dispatchSyncIfLogoCollection($media);
    }

    public function updated(Media $media): void
    {
        $this->dispatchSyncIfLogoCollection($media);
    }

    public function deleted(Media $media): void
    {
        if (! $this->isVendorLogo($media)) {
            return;
        }

        $vendor = $media->model;

        if ($vendor instanceof Vendor && $vendor->emoji_name) {
            app(RemoveVendorEmojiFromSlack::class)->handle($vendor);
        }
    }

    private function dispatchSyncIfLogoCollection(Media $media): void
    {
        if (! $this->isVendorLogo($media)) {
            return;
        }

        $vendor = $media->model;

        if ($vendor instanceof Vendor) {
            SyncVendorEmojiToSlackJob::dispatch($vendor);
        }
    }

    private function isVendorLogo(Media $media): bool
    {
        return $media->model_type === Vendor::class
            && $media->collection_name === 'logo';
    }
}
