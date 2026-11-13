<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\HotelSearchAggregator;
use App\Services\LiteApiService;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    public function home()
    {
        $featured = Hotel::latest()->take(6)->get();
        return view('home', compact('featured'));
    }

    /**
     * Destination autocomplete for the search form.
     */
    public function suggest(Request $request, LiteApiService $liteApi)
    {
        $keyword = trim((string) $request->query('q', ''));

        if (mb_strlen($keyword) < 2 || !$liteApi->configured()) {
            return response()->json([]);
        }

        return response()->json(
            collect($liteApi->places($keyword))
                ->take(8)
                ->map(fn ($place) => [
                    'name' => $place['name'],
                    'label' => trim($place['name'].', '.($place['address'] ?? ''), ', '),
                    'place_id' => $place['place_id'],
                    // Kept empty for the existing search form script.
                    'city_code' => '',
                ])
                ->values()
        );
    }

    /**
     * Search live hotel offers worldwide, plus local hotel records.
     */
    public function search(Request $request, HotelSearchAggregator $aggregator, LiteApiService $liteApi)
    {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:120'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'city_code' => ['nullable', 'string', 'size:3'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['required', 'integer', 'min:1', 'max:8'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'stars' => ['nullable', 'integer', 'between:1,5'],
            'sort' => ['nullable', 'in:recommended,price_asc,price_desc,rating'],
        ]);

        // Live offers from every registered hotel provider.
        $search = $aggregator->search([
            'destination' => $data['destination'],
            'place_id' => $data['place_id'] ?? null,
            'check_in' => date('Y-m-d', strtotime($data['check_in'])),
            'check_out' => date('Y-m-d', strtotime($data['check_out'])),
            'adults' => (int) $data['adults'],
            'rooms' => (int) $data['rooms'],
            'stars' => $data['stars'] ?? null,
            'sort' => $data['sort'] ?? null,
            'limit' => 24,
        ]);

        $liveHotels = collect($search['results'])->pluck('hotel');

        // Price filters apply to the price per night.
        if (!empty($data['min_price'])) {
            $liveHotels = $liveHotels->filter(
                fn ($hotel) => ($hotel['price_per_night'] ?? 0) >= (float) $data['min_price']
            );
        }

        if (!empty($data['max_price'])) {
            $liveHotels = $liveHotels->filter(
                fn ($hotel) => ($hotel['price_per_night'] ?? 0) <= (float) $data['max_price']
            );
        }

        if (($data['sort'] ?? '') === 'rating') {
            $liveHotels = $liveHotels->sortByDesc(fn ($hotel) => $hotel['rating'] ?? 0);
        }

        $liveHotels = $liveHotels->values()->all();

        // Local hotel records (development samples).
        $query = Hotel::query()->where(function ($q) use ($data) {
            $q->where('city', 'like', '%'.$data['destination'].'%')
              ->orWhere('country', 'like', '%'.$data['destination'].'%');
        });
        if (!empty($data['stars'])) $query->where('stars', $data['stars']);
        if (!empty($data['min_price'])) $query->where('indicative_price', '>=', $data['min_price']);
        if (!empty($data['max_price'])) $query->where('indicative_price', '<=', $data['max_price']);
        if (($data['sort'] ?? '') === 'price_asc') $query->orderBy('indicative_price');
        elseif (($data['sort'] ?? '') === 'price_desc') $query->orderByDesc('indicative_price');
        elseif (($data['sort'] ?? '') === 'rating') $query->orderByDesc('rating');
        $localHotels = $query->paginate(12)->withQueryString();

        return view('hotels.search', [
            'hotels' => $localHotels,
            'liveHotels' => $liveHotels,
            'providers' => $search['providers'],
            'criteria' => $data,
            'supplierConfigured' => $liteApi->configured(),
            // Used by the previous search page layout.
            'offers' => [],
            'liveAvailable' => false,
        ]);
    }

    public function show(Hotel $hotel)
    {
        $hotel->load(['reviews' => fn ($q) => $q->where('status', 'published')->with('user')->latest()]);
        return view('hotels.show', compact('hotel'));
    }
}