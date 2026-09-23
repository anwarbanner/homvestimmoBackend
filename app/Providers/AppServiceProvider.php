<?php

namespace App\Providers;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Observers\PropertyImageObserver;
use App\Observers\PropertyObserver;
use App\Services\SocialMedia\FacebookPublisher;
use App\Services\SocialMedia\InstagramPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FacebookPublisher::class, fn () => new FacebookPublisher(
            pageId: config('services.facebook.page_id'),
            pageToken: config('services.facebook.page_token'),
        ));

        $this->app->singleton(InstagramPublisher::class, fn () => new InstagramPublisher(
            businessAccountId: config('services.instagram.business_account_id'),
            accessToken: config('services.instagram.access_token'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Property::observe(PropertyObserver::class);
        PropertyImage::observe(PropertyImageObserver::class);
    }
}
