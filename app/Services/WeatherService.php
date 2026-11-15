<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * Current weather for a place.
     *
     * When an OpenWeather key is configured it is used, searching by
     * city name. Otherwise Open-Meteo is used, which needs no key but
     * does need the coordinates of the place.
     *
     * Returns temperature (°C), condition, humidity (%), wind (m/s)
     * and the source of the data, or null when nothing is available.
     */
    public function current(string $city, ?float $latitude = null, ?float $longitude = null): ?array
    {
        if (config('services.openweather.key')) {
            $conditions = $this->fromOpenWeather($city);

            if ($conditions !== null) {
                return $conditions;
            }
        }

        if ($latitude !== null && $longitude !== null) {
            return $this->fromOpenMeteo($latitude, $longitude);
        }

        return null;
    }

    /**
     * Weather by city name from OpenWeather (needs an API key).
     */
    protected function fromOpenWeather(string $city): ?array
    {
        $key = config('services.openweather.key');
        $cacheKey = 'weather_'.md5(mb_strtolower($city));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $city,
                'appid' => $key,
                'units' => 'metric',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (!is_numeric(data_get($data, 'main.temp'))) {
                return null;
            }

            $conditions = [
                'temperature' => data_get($data, 'main.temp'),
                'condition' => data_get($data, 'weather.0.description'),
                'humidity' => data_get($data, 'main.humidity'),
                'wind' => data_get($data, 'wind.speed'),
                'source' => 'OpenWeather',
            ];

            Cache::put($cacheKey, $conditions, now()->addMinutes(20));

            return $conditions;

        } catch (\Throwable $exception) {
            Log::warning('OpenWeather connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Weather by coordinates from Open-Meteo (no API key needed).
     */
    protected function fromOpenMeteo(float $latitude, float $longitude): ?array
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $cacheKey = 'weather_om_'.md5(round($latitude, 2).'|'.round($longitude, 2));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => round($latitude, 4),
                'longitude' => round($longitude, 4),
                'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                'wind_speed_unit' => 'ms',
            ]);

            if (!$response->successful()) {
                Log::warning('Open-Meteo request failed.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $current = $response->json('current');

            if (!is_array($current) || !is_numeric($current['temperature_2m'] ?? null)) {
                return null;
            }

            $conditions = [
                'temperature' => $current['temperature_2m'],
                'condition' => $this->describe($current['weather_code'] ?? null),
                'humidity' => $current['relative_humidity_2m'] ?? null,
                'wind' => $current['wind_speed_10m'] ?? null,
                'source' => 'Open-Meteo',
            ];

            // Only successful responses are kept in the cache.
            Cache::put($cacheKey, $conditions, now()->addMinutes(20));

            return $conditions;

        } catch (\Throwable $exception) {
            Log::warning('Open-Meteo connection error.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Turn a WMO weather code into a short description.
     */
    protected function describe(mixed $code): string
    {
        if (!is_numeric($code)) {
            return 'Current conditions';
        }

        return match (true) {
            $code == 0 => 'Clear sky',
            $code == 1 => 'Mainly clear',
            $code == 2 => 'Partly cloudy',
            $code == 3 => 'Overcast',
            $code == 45, $code == 48 => 'Fog',
            $code >= 51 && $code <= 55 => 'Drizzle',
            $code == 56, $code == 57 => 'Freezing drizzle',
            $code == 61 => 'Light rain',
            $code == 63 => 'Rain',
            $code == 65 => 'Heavy rain',
            $code == 66, $code == 67 => 'Freezing rain',
            $code == 71 => 'Light snow',
            $code == 73 => 'Snow',
            $code == 75 => 'Heavy snow',
            $code == 77 => 'Snow grains',
            $code >= 80 && $code <= 82 => 'Rain showers',
            $code == 85, $code == 86 => 'Snow showers',
            $code == 95 => 'Thunderstorm',
            $code == 96, $code == 99 => 'Thunderstorm with hail',
            default => 'Current conditions',
        };
    }
}
