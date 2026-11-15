<?php

namespace App\Http\Controllers;

use App\Models\FlightBooking;
use App\Services\LiteApiFlightService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FlightCheckoutController extends Controller
{
    /**
     * Longest time a checked fare is kept before the guest must search again.
     */
    protected const MINUTES_VALID = 12;

    /**
     * Step 1: the guest chose a flight. Find it in the search results,
     * recheck the fare and send the guest to the traveller details page.
     */
    public function select(Request $request, LiteApiFlightService $flights)
    {
        $data = $request->validate([
            'journey_key' => ['required', 'string', 'max:120'],
            'origin' => ['required', 'regex:/^[A-Z]{3}$/'],
            'destination' => ['required', 'regex:/^[A-Z]{3}$/', 'different:origin'],
            'origin_label' => ['nullable', 'string', 'max:160'],
            'destination_label' => ['nullable', 'string', 'max:160'],
            'depart_date' => ['required', 'date', 'after_or_equal:today'],
            'return_date' => ['nullable', 'date', 'after_or_equal:depart_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:9'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:4'],
            'cabin_class' => ['nullable', 'in:ECONOMY,PREMIUM_ECONOMY,BUSINESS,FIRST'],
            'direct' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:price,duration,departure'],
        ]);

        $criteria = [
            'trip' => !empty($data['return_date']) ? 'return' : 'one_way',
            'origin' => $data['origin'],
            'origin_label' => $data['origin_label'] ?? $data['origin'],
            'destination' => $data['destination'],
            'destination_label' => $data['destination_label'] ?? $data['destination'],
            'depart_date' => date('Y-m-d', strtotime($data['depart_date'])),
            'return_date' => !empty($data['return_date']) ? date('Y-m-d', strtotime($data['return_date'])) : null,
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'infants' => (int) ($data['infants'] ?? 0),
            'cabin_class' => $data['cabin_class'] ?? 'ECONOMY',
            'direct' => (bool) ($data['direct'] ?? false),
            'sort' => $data['sort'] ?? 'price',
        ];

        // Back to the same search if anything goes wrong.
        $searchAgain = fn (string $message) => redirect()
            ->route('flights.index', array_filter($criteria, fn ($value) => $value !== null && $value !== false))
            ->withErrors(['flight' => $message]);

        // The same search is repeated so that flight details come from the
        // supplier and not from the browser. Recent searches are cached.
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

        $journey = collect($flights->search($search))->firstWhere('key', $data['journey_key']);

        if ($journey === null) {
            return $searchAgain('That fare is no longer available. Here are the latest flights for your search.');
        }

        $verified = $flights->verify($journey['offer_id']);

        if (!$verified['ok']) {
            return $searchAgain($verified['error']);
        }

        $expiry = now()->addMinutes(self::MINUTES_VALID);

        if (!empty($verified['data']['expires_at'])) {
            $offerExpiry = rescue(fn () => Carbon::parse($verified['data']['expires_at'])->subMinute(), null, false);

            if ($offerExpiry !== null && $offerExpiry->lt($expiry)) {
                $expiry = $offerExpiry;
            }
        }

        if ($expiry->isPast()) {
            return $searchAgain('That fare has just expired. Here are the latest flights for your search.');
        }

        $token = Str::random(40);

        $request->session()->put('flight_checkout.'.$token, [
            'offer_id' => $journey['offer_id'],
            'expires_at' => $expiry->timestamp,
            'criteria' => $criteria,
            'journey' => $journey,
            'price' => $verified['data']['price'],
            'currency' => $verified['data']['currency'] ?: $journey['currency'],
            'price_changed' => $verified['data']['price_changed']
                || abs((float) $verified['data']['price'] - (float) $journey['price']) >= 0.01,
            'searched_price' => $journey['price'],
            'booking_id' => null,
        ]);

        return redirect()->route('flights.checkout.show', $token);
    }

    /**
     * Step 2: show the flight summary and the traveller details form.
     */
    public function show(Request $request, string $token, LiteApiFlightService $flights)
    {
        $checkout = $this->checkout($request, $token);

        if ($checkout === null) {
            return redirect()->route('flights.index')
                ->withErrors(['flight' => 'This fare has expired. Please search again.']);
        }

        return view('flights.checkout', [
            'token' => $token,
            'checkout' => $checkout,
            'travellers' => $this->travellers($checkout['criteria']),
            'countries' => config('countries', []),
            'sandbox' => $flights->sandbox(),
            'minutesLeft' => max(1, (int) ceil(($checkout['expires_at'] - now()->timestamp) / 60)),
        ]);
    }

    /**
     * Step 3: reserve and book the flight, then save it.
     */
    public function confirm(Request $request, string $token, LiteApiFlightService $flights)
    {
        $checkout = $request->session()->get('flight_checkout.'.$token);

        // A repeated submit after a successful booking goes to the confirmation.
        if (is_array($checkout) && !empty($checkout['booking_id'])) {
            $existing = FlightBooking::find($checkout['booking_id']);

            if ($existing) {
                return redirect()->route('flights.confirmation', $existing);
            }
        }

        $checkout = $this->checkout($request, $token);

        if ($checkout === null) {
            return redirect()->route('flights.index')
                ->withErrors(['flight' => 'This fare has expired. Please search again.']);
        }

        $travellers = $this->travellers($checkout['criteria']);
        $countryCodes = array_keys(config('countries', []));

        $data = $request->validate([
            'contact.first_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\s\'\-]+$/u'],
            'contact.last_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\s\'\-]+$/u'],
            'contact.email' => ['required', 'email', 'max:190'],
            'contact.phone_country_code' => ['required', 'regex:/^[0-9]{1,4}$/'],
            'contact.phone_number' => ['required', 'regex:/^[0-9 \-]{6,20}$/'],
            'passengers' => ['required', 'array', 'size:'.count($travellers)],
            'passengers.*.title' => ['required', 'in:MR,MS,MRS,MISS'],
            'passengers.*.first_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\s\'\-]+$/u'],
            'passengers.*.last_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\s\'\-]+$/u'],
            'passengers.*.birthday' => ['required', 'date', 'before:today'],
            'passengers.*.gender' => ['required', 'in:M,F,X'],
            'passengers.*.nationality' => ['required', Rule::in($countryCodes)],
            'accept_terms' => ['accepted'],
        ], [
            'contact.phone_country_code.regex' => 'Enter the country calling code as numbers only, for example 44.',
            'contact.phone_number.regex' => 'Enter the phone number using numbers only.',
            'passengers.*.first_name.regex' => 'Names can contain letters, spaces, hyphens and apostrophes only.',
            'passengers.*.last_name.regex' => 'Names can contain letters, spaces, hyphens and apostrophes only.',
            'passengers.size' => 'Details are needed for every traveller.',
        ], [
            'contact.first_name' => 'contact first name',
            'contact.last_name' => 'contact last name',
            'contact.email' => 'contact email',
            'contact.phone_country_code' => 'country calling code',
            'contact.phone_number' => 'phone number',
            'passengers.*.title' => 'title',
            'passengers.*.first_name' => 'first name',
            'passengers.*.last_name' => 'last name',
            'passengers.*.birthday' => 'date of birth',
            'passengers.*.gender' => 'gender',
            'passengers.*.nationality' => 'nationality',
        ]);

        $submitted = array_values($data['passengers']);

        // Each traveller's age on the day of travel must match the fare type.
        $travelDate = Carbon::parse($checkout['criteria']['return_date'] ?: $checkout['criteria']['depart_date']);
        $ageErrors = [];

        foreach ($travellers as $index => $traveller) {
            $age = Carbon::parse($submitted[$index]['birthday'])->diffInYears($travelDate);
            $number = $index + 1;

            if ($traveller['type'] === 0 && $age < 12) {
                $ageErrors['passengers.'.$index.'.birthday'] = 'Traveller '.$number.' is booked as an adult and must be 12 or older on the day of travel.';
            } elseif ($traveller['type'] === 1 && ($age < 2 || $age >= 12)) {
                $ageErrors['passengers.'.$index.'.birthday'] = 'Traveller '.$number.' is booked as a child and must be aged 2 to 11 on the day of travel.';
            } elseif ($traveller['type'] === 2 && $age >= 2) {
                $ageErrors['passengers.'.$index.'.birthday'] = 'Traveller '.$number.' is booked as an infant and must be under 2 on the day of travel.';
            }
        }

        if (count($ageErrors) > 0) {
            throw ValidationException::withMessages($ageErrors);
        }

        $contact = [
            'firstName' => trim($data['contact']['first_name']),
            'lastName' => trim($data['contact']['last_name']),
            'email' => $data['contact']['email'],
            'phoneCountryCode' => $data['contact']['phone_country_code'],
            'phoneNumber' => preg_replace('/[^0-9]/', '', $data['contact']['phone_number']),
        ];

        $passengers = [];

        foreach ($travellers as $index => $traveller) {
            $passengers[] = [
                'passengerType' => $traveller['type'],
                'title' => $submitted[$index]['title'],
                'firstName' => trim($submitted[$index]['first_name']),
                'lastName' => trim($submitted[$index]['last_name']),
                'birthday' => date('Y-m-d', strtotime($submitted[$index]['birthday'])),
                'gender' => $submitted[$index]['gender'],
                'nationality' => $submitted[$index]['nationality'],
            ];
        }

        $prebook = $flights->prebook($checkout['offer_id'], $contact, $passengers, false);

        if (!$prebook['ok']) {
            return back()->withInput()->withErrors(['flight' => $prebook['error']]);
        }

        $result = $flights->book($prebook['data']['prebook_id']);

        if (!$result['ok']) {
            return back()->withInput()->withErrors(['flight' => $result['error']]);
        }

        $confirmed = $result['data'];
        $journey = $checkout['journey'];
        $legs = $journey['legs'];
        $criteria = $checkout['criteria'];

        try {
            $booking = FlightBooking::create([
                'user_id' => $request->user()->id,
                'supplier' => 'liteapi',
                'supplier_booking_id' => $confirmed['booking_id'],
                'booking_ref' => $confirmed['booking_ref'] ?: $prebook['data']['booking_ref'],
                'pnr' => implode(', ', $confirmed['pnrs']) ?: null,
                'status' => strtolower($confirmed['status'] ?: 'pending_confirmation'),
                'payment_status' => $confirmed['sandbox'] ? 'test_no_charge' : $confirmed['payment_status'],
                'is_sandbox' => $confirmed['sandbox'],
                'trip' => count($legs) > 1 ? 'return' : 'one_way',
                'origin' => $legs[0]['origin_code'] ?: $criteria['origin'],
                'destination' => $legs[0]['destination_code'] ?: $criteria['destination'],
                'origin_name' => $legs[0]['origin_name'],
                'destination_name' => $legs[0]['destination_name'],
                'depart_at' => $legs[0]['departure'],
                'return_at' => $legs[1]['departure'] ?? null,
                'airlines' => Str::limit(implode(', ', $journey['airlines']), 250, ''),
                'cabin' => $journey['cabin'],
                'refundable' => $journey['refundable'],
                'adults' => $criteria['adults'],
                'children' => $criteria['children'],
                'infants' => $criteria['infants'],
                'currency' => $confirmed['currency'] ?: $checkout['currency'],
                'total_amount' => $confirmed['price'] ?? ($prebook['data']['price'] ?? $checkout['price']),
                'itinerary' => $legs,
                // Only names and traveller types are kept. Dates of birth and
                // nationalities are sent to the airline but not stored here.
                'passengers' => array_map(fn ($passenger, $traveller) => [
                    'name' => trim($passenger['title'].' '.$passenger['firstName'].' '.$passenger['lastName']),
                    'type' => $traveller['label'],
                ], $passengers, $travellers),
                'contact_name' => trim($contact['firstName'].' '.$contact['lastName']),
                'contact_email' => $contact['email'],
                'contact_phone' => '+'.$contact['phoneCountryCode'].' '.$contact['phoneNumber'],
            ]);
        } catch (\Throwable $exception) {
            // The supplier holds a booking that could not be saved locally.
            Log::critical('Supplier flight booking made but could not be saved.', [
                'supplier_booking_id' => $confirmed['booking_id'],
                'booking_ref' => $confirmed['booking_ref'],
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            $request->session()->forget('flight_checkout.'.$token);

            return redirect()->route('bookings.index')->withErrors([
                'flight' => 'Your flight was booked with reference '.($confirmed['booking_ref'] ?: $confirmed['booking_id'])
                    .', but we could not save it to your account. Please keep this reference and contact support.',
            ]);
        }

        // Remember the result so a repeated submit cannot book twice.
        $request->session()->put('flight_checkout.'.$token.'.booking_id', $booking->id);

        return redirect()->route('flights.confirmation', $booking)
            ->with('success', $booking->isConfirmed()
                ? 'Your flight is confirmed.'
                : 'Your flight is booked and awaiting confirmation from the airline.');
    }

    /**
     * Step 4: the confirmation page for one of the user's own flight bookings.
     */
    public function confirmation(Request $request, FlightBooking $flightBooking)
    {
        abort_unless($flightBooking->user_id === $request->user()->id, 404);

        return view('flights.confirmation', ['booking' => $flightBooking]);
    }

    /**
     * Read an unfinished checkout from the session, or null when it is
     * missing, already booked or expired.
     */
    protected function checkout(Request $request, string $token): ?array
    {
        $checkout = $request->session()->get('flight_checkout.'.$token);

        if (
            !is_array($checkout)
            || !empty($checkout['booking_id'])
            || ($checkout['expires_at'] ?? 0) < now()->timestamp
        ) {
            return null;
        }

        return $checkout;
    }

    /**
     * The list of travellers to collect details for: adults first,
     * then children, then infants.
     */
    protected function travellers(array $criteria): array
    {
        $travellers = [];

        foreach ([
            ['count' => $criteria['adults'], 'type' => 0, 'label' => 'Adult', 'hint' => 'Aged 12 or over'],
            ['count' => $criteria['children'], 'type' => 1, 'label' => 'Child', 'hint' => 'Aged 2 to 11'],
            ['count' => $criteria['infants'], 'type' => 2, 'label' => 'Infant', 'hint' => 'Under 2'],
        ] as $group) {
            for ($i = 0; $i < (int) $group['count']; $i++) {
                $travellers[] = [
                    'type' => $group['type'],
                    'label' => $group['label'],
                    'hint' => $group['hint'],
                ];
            }
        }

        return $travellers;
    }
}
