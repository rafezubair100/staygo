<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = $request->user()->bookings()->with('hotel')->latest()->paginate(10);
        return view('bookings.index', compact('bookings'));
    }

    public function store(Request $request, Hotel $hotel)
    {
        $data = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['required', 'integer', 'min:1', 'max:8'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:190'],
        ]);

        // A booking request is deliberately NOT labelled confirmed. Supplier inventory,
        // final price, payment and supplier confirmation must be completed before confirmation.
        $booking = Booking::create($data + [
            'user_id' => $request->user()->id,
            'hotel_id' => $hotel->id,
            'supplier' => $hotel->supplier ?? 'manual',
            'currency' => 'GBP',
            'total_amount' => 0,
            'status' => 'pending_supplier_check',
            'payment_status' => 'not_started',
        ]);

        return redirect()->route('bookings.index')->with('success',
            'Your request has been recorded. It is not a confirmed reservation yet; live availability and price must be checked by the supplier.');
    }
}
