<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use App\Services\WikipediaService;

class DestinationController extends Controller
{
    public function show(string $city, WikipediaService $wiki, WeatherService $weather)
    {
        $summary = $wiki->summary($city);
        $conditions = $weather->current(str_replace('-', ' ', $city));
        return view('destinations.show', compact('city', 'summary', 'conditions'));
    }
}
