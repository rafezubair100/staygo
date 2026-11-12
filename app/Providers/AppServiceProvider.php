<?php

namespace App\Providers;

use App\Contracts\HotelProvider;
use App\Contracts\PlaceProvider;
use App\Services\AmadeusService;
use App\Services\WikipediaPlaceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Amadeus as the default hotel provider.
        $this->app->bind(
            HotelProvider::class,
            AmadeusService::class
        );

        // Register hotel providers.
        $this->app->tag([
            AmadeusService::class,
        ], 'hotel.providers');

        // Register place providers.
        $this->app->tag([
            WikipediaPlaceProvider::class,
        ], 'place.providers');
    }

    public function boot(): void
    {
        //
    }
}
