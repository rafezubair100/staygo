<?php

namespace App\Http\Controllers;

use App\Services\LiteApiFlightService;
use App\Services\LiteApiService;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    /**
     * How many journeys are shown on the results page.
     */
    protected const RESULTS_SHOWN = 40;

    /**
     * Airport autocomplete for the flight search form.
     */
    public function airports(Request $request, LiteApiFlightService $flights)
    {
        $keyword = trim((string) $request->query('q', ''));

        if (mb_strlen($keyword) < 2) {
            return response()->json([]);
        }

        return response()->json(
            collect($flights->airports($keyword))
                ->take(8)
                ->map(fn ($airport) => [
                    'iata' => $airport['iata'],
                    'label' => $airport['label'],
                ])
                ->values()
        );
    }

    /**
     * Show the flight search form and, when a search was made, the results.
     */
    public function index(Request $request, LiteApiFlightService $flights, LiteApiService $liteApi)
    {
        $searched = $request->hasAny(['origin', 'origin_label', 'destination', 'destination_label']);

        $view = [
            'searched' => false,
            'journeys' => [],
            'total' => 0,
            'criteria' => [],
            'configured' => $flights->configured(),
            'sandbox' => $liteApi->sandbox(),
        ];

        if (!$searched) {
            return view('flights.index', $view);
        }

        // If an airport was typed but not picked from the list, look it up.
        foreach (['origin', 'destination'] as $field) {
            $code = strtoupper(trim((string) $request->input($field)));
            $label = trim((string) $request->input($field.'_label'));

            if (!preg_match('/^[A-Z]{3}$/', $code) && $label !== '') {
                $match = $flights->airports($label)[0] ?? null;

                if ($match !== null) {
                    $request->merge([
                        $field => $match['iata'],
                        $field.'_label' => $match['label'],
                    ]);
                }
            } else {
                $request->merge([$field => $code]);
            }
        }

        $data = $request->validate([
            'trip' => ['nullable', 'in:one_way,return'],
            'origin' => ['required', 'regex:/^[A-Z]{3}$/'],
            'origin_label' => ['nullable', 'string', 'max:160'],
            'destination' => ['required', 'regex:/^[A-Z]{3}$/', 'different:origin'],
            'destination_label' => ['nullable', 'string', 'max:160'],
            'depart_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:depart_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4', 'lte:adults'],
            'cabin_class' => ['nullable', 'in:ECONOMY,PREMIUM_ECONOMY,BUSINESS,FIRST'],
            'direct' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:price,duration,departure'],
        ], [
            'origin.required' => 'Choose a departure airport from the list.',
            'origin.regex' => 'Choose a departure airport from the list.',
            'destination.required' => 'Choose a destination airport from the list.',
            'destination.regex' => 'Choose a destination airport from the list.',
            'destination.different' => 'The destination must be different from the departure airport.',
            'infants.lte' => 'Each infant must travel with an adult.',
        ]);

        $return = ($data['trip'] ?? 'return') === 'return' && !empty($data['return_date']);

        $criteria = [
            'trip' => $return ? 'return' : 'one_way',
            'origin' => $data['origin'],
            'origin_label' => $data['origin_label'] ?? $data['origin'],
            'destination' => $data['destination'],
            'destination_label' => $data['destination_label'] ?? $data['destination'],
            'depart_date' => date('Y-m-d', strtotime($data['depart_date'])),
            'return_date' => $return ? date('Y-m-d', strtotime($data['return_date'])) : null,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'infants' => (int) ($data['infants'] ?? 0),
            'cabin_class' => $data['cabin_class'] ?? 'ECONOMY',
            'direct' => (bool) ($data['direct'] ?? false),
            'sort' => $data['sort'] ?? 'price',
        ];

        $search = [
            'origin' => $criteria['origin'],
            'destination' => $criteria['destination'],
            'depart_date' => $criteria['depart_date'],
            'return_date' => $criteria['return_date'],
            'adults' => $criteria['adults'],
            'children' => $criteria['children'],
            'infants' => $criteria['infants'],
            'cabin_class' => $criteria['cabin_class'],
            'sort' => $criteria['sort'],
        ];

        if ($criteria['direct']) {
            $search['max_stops'] = 0;
        }

        $journeys = $flights->search($search);

        return view('flights.index', array_merge($view, [
            'searched' => true,
            'journeys' => array_slice($journeys, 0, self::RESULTS_SHOWN),
            'total' => count($journeys),
            'criteria' => $criteria,
        ]));
    }
}
