<?php

namespace App\Providers;

use App\Contracts\HotelProvider;
use App\Services\AmadeusService;
use App\Services\LiteApiHotelProvider;
use App\Services\OpenTripMapPlaceProvider;
use App\Services\WikipediaPlaceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register LiteAPI as the default hotel provider.
        $this->app->bind(
            HotelProvider::class,
            LiteApiHotelProvider::class
        );

        // Register hotel providers.
        $this->app->tag([
            LiteApiHotelProvider::class,
            AmadeusService::class,
        ], 'hotel.providers');

        // Register destination and attraction providers.
        $this->app->tag([
            WikipediaPlaceProvider::class,
            OpenTripMapPlaceProvider::class,
        ], 'place.providers');
    }

    public function boot(): void
    {
        //
    }
}