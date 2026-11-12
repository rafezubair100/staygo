
<?php

namespace App\Providers;

use App\Contracts\HotelProvider;
use App\Services\AmadeusService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            HotelProvider::class,
            AmadeusService::class
        );
    }

    public function boot(): void
    {
        //
    }
}
