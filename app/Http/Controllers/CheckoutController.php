<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\LiteApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * How long a checked price is kept before the guest must start again.
     */
    protected const MINUTES_VALID = 20;

    /**
     * Step 1: the guest chose a room. Check it with the supplier, keep
     * the result in the session and send the guest to the checkout page.
     */
    public function start(Request $request, LiteApiService $liteApi)
    {
        $data = $request->validate([
            'offer_id' => ['required', 'string', 'max:8000'],
            'hotel_id' => ['required', 'string', 'regex:/^[A-Za-z0-9]+$/', 'max:60'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'rooms' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $stay = [
            'hotelId' => $data['hotel_id'],
            'check_in' => date('Y-m-d', strtotime($data['check_in'])),
            'check_out' => date('Y-m-d', strtotime($data['check_out'])),
            'adults' => (int) $data['adults'],
            'rooms' => (int) $data['rooms'],
        ];

        $prebook = $liteApi->prebook($data['offer_id']);

        if (!$prebook['ok']) {
            return redirect()->route('stays.show', $stay)->withErrors(['booking' => $prebook['error']]);
        }

        $hotel = $liteApi->hotel($data['hotel_id']);
        $offer = $prebook['data'];

        if ($hotel === null || $offer['price'] === null) {
            return redirect()->route('stays.show', $stay)
                ->withErrors(['booking' => 'This room could not be prepared for booking. Please try again.']);
        }

        $token = Str::random(40);

        $request->session()->put('checkout.'.$token, [
            'prebook_id' => $offer['prebook_id'],
            'client_reference' => 'SG-'.strtoupper(Str::random(12)),
            'expires_at' => now()->addMinutes(self::MINUTES_VALID)->timestamp,
            'hotel' => [
                'id' => $hotel['id'],
                'name' => $hotel['name'],
                'address' => collect([$hotel['address'], $hotel['city'], $hotel['country']])->filter()->implode(', '),
                'image' => $hotel['main_photo'],
            ],
            'stay' => [
                'check_in' => $offer['check_in'] ?: $stay['check_in'],
                'check_out' => $offer['check_out'] ?: $stay['check_out'],
                'adults' => $stay['adults'],
                'rooms' => $stay['rooms'],
            ],
            'offer' => [
                'room_name' => $offer['room_name'],
                'board' => $offer['board'],
                'room_count' => max(1, (int) $offer['room_count']),
                'price' => $offer['price'],
                'currency' => $offer['currency'],
                'refundable' => $offer['refundable'],
                'cancel_by' => $offer['cancel_by'],
                'pay_at_hotel' => $offer['pay_at_hotel'],
                'terms' => $offer['terms'],
                'price_change_percent' => $offer['price_change_percent'],
                'cancellation_changed' => $offer['cancellation_changed'],
                'board_changed' => $offer['board_changed'],
            ],
        ]);

        return redirect()->route('checkout.show', $token);
    }

    /**
     * Step 2: show the stay summary, final price and guest details form.
     */
    public function show(Request $request, string $token, LiteApiService $liteApi)
    {
        $checkout = $this->checkout($request, $token);

        if ($checkout === null) {
            return redirect()->route('home')
                ->withErrors(['booking' => 'This checkout has expired. Please search and choose your room again.']);
        }

        return view('checkout.show', [
            'token' => $token,
            'checkout' => $checkout,
            'nights' => $this->nights($checkout['stay']['check_in'], $checkout['stay']['check_out']),
            'sandbox' => $liteApi->sandbox(),
            'minutesLeft' => max(1, (int) ceil(($checkout['expires_at'] - now()->timestamp) / 60)),
        ]);
    }

    /**
     * Step 3: confirm the booking with the supplier and save it.
     */
    public function confirm(Request $request, string $token, LiteApiService $liteApi)
    {
        $checkout = $this->checkout($request, $token);

        if ($checkout === null) {
            return redirect()->route('home')
                ->withErrors(['booking' => 'This checkout has expired. Please search and choose your room again.']);
        }

        // If this booking was already saved, never book it a second time.
        $existing = Booking::where('client_reference', $checkout['client_reference'])->first();

        if ($existing) {
            $request->session()->forget('checkout.'.$token);

            return redirect()->route('bookings.confirmation', $existing);
        }

        $guest = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[0-9+() \-]+$/'],
            'accept_terms' => ['accepted'],
        ]);

        $holder = [
            'firstName' => $guest['first_name'],
            'lastName' => $guest['last_name'],
            'email' => $guest['email'],
            'phone' => $guest['phone'],
        ];

        // One named guest per room. The lead guest is used for each room.
        $guests = [];

        for ($room = 1; $room <= $checkout['offer']['room_count']; $room++) {
            $guests[] = [
                'occupancyNumber' => $room,
                'firstName' => $guest['first_name'],
                'lastName' => $guest['last_name'],
                'email' => $guest['email'],
            ];
        }

        $result = $liteApi->book(
            $checkout['prebook_id'],
            $holder,
            $guests,
            $checkout['client_reference']
        );

        if (!$result['ok']) {
            return back()->withInput()->withErrors(['booking' => $result['error']]);
        }

        $confirmed = $result['data'];

        try {
            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'hotel_id' => null,
                'supplier' => 'liteapi',
                'supplier_reference' => $confirmed['booking_id'],
                'supplier_hotel_id' => $checkout['hotel']['id'],
                'hotel_name' => $confirmed['hotel_name'] ?: $checkout['hotel']['name'],
                'room_name' => $checkout['offer']['room_name'],
                'board' => $checkout['offer']['board'],
                'hotel_confirmation_code' => $confirmed['hotel_confirmation_code'],
                'client_reference' => $checkout['client_reference'],
                'check_in' => $confirmed['check_in'] ?: $checkout['stay']['check_in'],
                'check_out' => $confirmed['check_out'] ?: $checkout['stay']['check_out'],
                'adults' => $checkout['stay']['adults'],
                'children' => 0,
                'rooms' => $checkout['stay']['rooms'],
                'currency' => $confirmed['currency'] ?: $checkout['offer']['currency'],
                'total_amount' => $confirmed['price'] ?? $checkout['offer']['price'],
                'status' => strtolower((string) $confirmed['status']) === 'confirmed' ? 'confirmed' : 'pending_supplier_check',
                'payment_status' => $confirmed['sandbox'] ? 'test_no_charge' : 'paid',
                'refundable' => $confirmed['refundable'] || $checkout['offer']['refundable'],
                'is_sandbox' => $confirmed['sandbox'],
                'guest_name' => trim($guest['first_name'].' '.$guest['last_name']),
                'guest_email' => $guest['email'],
                'guest_phone' => $guest['phone'],
            ]);
        } catch (\Throwable $exception) {
            // The supplier holds a booking that could not be saved locally.
            Log::critical('Supplier booking confirmed but could not be saved.', [
                'supplier_reference' => $confirmed['booking_id'],
                'client_reference' => $checkout['client_reference'],
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            $request->session()->forget('checkout.'.$token);

            return redirect()->route('bookings.index')->withErrors([
                'booking' => 'Your booking was confirmed with reference '.$confirmed['booking_id']
                    .', but we could not save it to your account. Please keep this reference and contact support.',
            ]);
        }

        $request->session()->forget('checkout.'.$token);

        return redirect()->route('bookings.confirmation', $booking)
            ->with('success', 'Your booking is confirmed.');
    }

    /**
     * Step 4: the confirmation page for one of the user's own bookings.
     */
    public function confirmation(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        return view('checkout.confirmation', [
            'booking' => $booking,
            'nights' => $this->nights($booking->check_in->format('Y-m-d'), $booking->check_out->format('Y-m-d')),
        ]);
    }

    /**
     * Read a checkout from the session, or null when missing or expired.
     */
    protected function checkout(Request $request, string $token): ?array
    {
        $checkout = $request->session()->get('checkout.'.$token);

        if (!is_array($checkout) || ($checkout['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('checkout.'.$token);

            return null;
        }

        return $checkout;
    }

    /**
     * Number of nights between two dates, at least one.
     */
    protected function nights(string $checkIn, string $checkOut): int
    {
        return max(1, (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400));
    }
}
