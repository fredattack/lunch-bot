<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Observers\VendorMediaObserver;
use App\Policies\OrderPolicy;
use App\Policies\VendorPolicy;
use App\Policies\VendorProposalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(VendorProposal::class, VendorProposalPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        Media::observe(VendorMediaObserver::class);
    }
}
