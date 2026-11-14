<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Services\LiteApiService;
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

    /**
     * Ask the guest to confirm before a live booking is cancelled.
     */
    public function cancelForm(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        if (!$booking->canBeCancelled()) {
            return redirect()->route('bookings.index')
                ->withErrors(['booking' => 'This booking cannot be cancelled online.']);
        }

        return view('bookings.cancel', [
            'booking' => $booking,
            'nights' => max(1, (int) $booking->check_in->diffInDays($booking->check_out)),
        ]);
    }

    /**
     * Cancel a live booking with the supplier and record the outcome.
     */
    public function cancel(Request $request, Booking $booking, LiteApiService $liteApi)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        if ($booking->isCancelled()) {
            return redirect()->route('bookings.index')
                ->with('success', 'This booking is already cancelled.');
        }

        if (!$booking->canBeCancelled()) {
            return redirect()->route('bookings.index')
                ->withErrors(['booking' => 'This booking cannot be cancelled online.']);
        }

        $request->validate([
            'confirm_cancel' => ['accepted'],
        ], [
            'confirm_cancel.accepted' => 'Please tick the box to confirm you want to cancel.',
        ]);

        $result = $liteApi->cancel($booking->supplier_reference);

        if (!$result['ok']) {
            return back()->withErrors(['booking' => $result['error']]);
        }

        $outcome = $result['data'];
        $refund = $outcome['refund_amount'];

        if ($booking->is_sandbox) {
            $paymentStatus = 'test_no_charge';
        } elseif ($refund !== null && $refund >= (float) $booking->total_amount) {
            $paymentStatus = 'refunded';
        } elseif ($refund !== null && $refund > 0) {
            $paymentStatus = 'partially_refunded';
        } else {
            $paymentStatus = 'not_refunded';
        }

        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_fee' => $outcome['cancellation_fee'],
            'refund_amount' => $refund,
            'payment_status' => $paymentStatus,
        ]);

        $currency = $outcome['currency'] ?: $booking->currency;

        $message = 'Your booking at '.$booking->hotelDisplayName().' has been cancelled.';

        if ($refund !== null) {
            $message .= $refund > 0
                ? ' Refund: '.$currency.' '.number_format($refund, 2).'.'
                : ' This booking was non-refundable, so no refund is due.';
        }

        return redirect()->route('bookings.index')->with('success', $message);
    }
}
