<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherService
{
    public function current(string $city): ?array
    {
        $key = config('services.openweather.key');
        if (!$key) return null;
        return Cache::remember('weather_'.md5($city), now()->addMinutes(20), function () use ($city, $key) {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $city, 'appid' => $key, 'units' => 'metric',
            ]);
            if (!$response->successful()) return null;
            $data = $response->json();
            return [
                'temperature' => data_get($data, 'main.temp'),
                'condition' => data_get($data, 'weather.0.description'),
                'humidity' => data_get($data, 'main.humidity'),
                'wind' => data_get($data, 'wind.speed'),
            ];
        });
    }
}
