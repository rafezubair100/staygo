<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Services\AmadeusService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HotelController extends Controller
{
    public function home()
    {
        $featured = Hotel::latest()->take(6)->get();
        return view('home', compact('featured'));
    }

    public function suggest(Request $request, AmadeusService $amadeus)
    {
        $keyword = trim((string) $request->query('q', ''));
        if (mb_strlen($keyword) < 2 || !$amadeus->configured()) {
            return response()->json([]);
        }
        return response()->json(collect($amadeus->destinations($keyword))->map(fn ($place) => [
            'name' => data_get($place, 'name'),
            'country' => data_get($place, 'address.countryName'),
            'city_code' => data_get($place, 'iataCode'),
            'label' => trim(data_get($place, 'name', '').', '.data_get($place, 'address.countryName', ''), ', '),
        ])->filter(fn ($place) => $place['name'] && $place['city_code'])->values());
    }

    public function search(Request $request, AmadeusService $amadeus)
    {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:120'],
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

        $offers = [];
        $liveAvailable = false;
        if ($amadeus->configured() && !empty($data['city_code'])) {
            $offers = $amadeus->searchHotels($data['city_code'], $data['check_in'], $data['check_out'], $data['adults'], $data['rooms']);
            $liveAvailable = count($offers) > 0;
        }

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
            'hotels' => $localHotels, 'offers' => $offers, 'liveAvailable' => $liveAvailable,
            'criteria' => $data, 'supplierConfigured' => $amadeus->configured(),
        ]);
    }

    public function show(Hotel $hotel)
    {
        $hotel->load(['reviews' => fn ($q) => $q->where('status', 'published')->with('user')->latest()]);
        return view('hotels.show', compact('hotel'));
    }
}
