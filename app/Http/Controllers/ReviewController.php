<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\FlightBooking;
use App\Models\Hotel;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    /**
     * Most photos allowed on one review.
     */
    protected const MAX_PHOTOS = 4;

    /**
     * Public page listing the latest published reviews.
     */
    public function index()
    {
        $reviews = Review::with('user')
            ->where('status', 'published')
            ->latest()
            ->paginate(12);

        return view('reviews.index', compact('reviews'));
    }

    /**
     * Review of a hotel stored in the local hotels table. These are
     * held for moderation, as before.
     */
    public function store(Request $request, Hotel $hotel)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:15', 'max:3000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('review-photos', 'public');
        }
        unset($data['photo']);
        $hotel->reviews()->create($data + [
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'verified_stay' => false,
        ]);
        return back()->with('success', 'Thanks. Your review has been submitted for moderation.');
    }

    /**
     * Form to review a live hotel booking.
     */
    public function createForBooking(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        if (!$booking->canBeReviewed()) {
            return redirect()->route('bookings.index')
                ->withErrors(['review' => 'This booking cannot be reviewed, or has already been reviewed.']);
        }

        return view('reviews.create', [
            'kind' => 'hotel',
            'heading' => $booking->hotelDisplayName(),
            'detail' => collect([
                $booking->room_name,
                $booking->check_in->format('d M Y').' – '.$booking->check_out->format('d M Y'),
            ])->filter()->implode(' · '),
            'action' => route('reviews.booking.store', $booking),
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    /**
     * Save a review of a live hotel booking.
     */
    public function storeForBooking(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 404);

        if (!$booking->canBeReviewed()) {
            return redirect()->route('bookings.index')
                ->withErrors(['review' => 'This booking cannot be reviewed, or has already been reviewed.']);
        }

        return $this->save($request, [
            'booking_id' => $booking->id,
            'supplier_hotel_id' => $booking->supplier_hotel_id,
            'subject_name' => $booking->hotelDisplayName(),
        ]);
    }

    /**
     * Form to review a flight booking.
     */
    public function createForFlight(Request $request, FlightBooking $flightBooking)
    {
        abort_unless($flightBooking->user_id === $request->user()->id, 404);

        if (!$flightBooking->canBeReviewed()) {
            return redirect()->route('bookings.index')
                ->withErrors(['review' => 'This flight cannot be reviewed, or has already been reviewed.']);
        }

        return view('reviews.create', [
            'kind' => 'flight',
            'heading' => $flightBooking->routeLabel(),
            'detail' => collect([
                $flightBooking->trip === 'return' ? 'Return' : 'One way',
                $flightBooking->depart_at->format('d M Y'),
            ])->filter()->implode(' · '),
            'action' => route('reviews.flight.store', $flightBooking),
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    /**
     * Save a review of a flight booking.
     */
    public function storeForFlight(Request $request, FlightBooking $flightBooking)
    {
        abort_unless($flightBooking->user_id === $request->user()->id, 404);

        if (!$flightBooking->canBeReviewed()) {
            return redirect()->route('bookings.index')
                ->withErrors(['review' => 'This flight cannot be reviewed, or has already been reviewed.']);
        }

        return $this->save($request, [
            'flight_booking_id' => $flightBooking->id,
            'subject_name' => $flightBooking->routeLabel(),
        ]);
    }

    /**
     * Validate the form, store the photos and create the review.
     *
     * Reviews of real bookings are published straight away and marked
     * as verified. An administrator can still reject one afterwards.
     */
    protected function save(Request $request, array $subject)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:15', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'photos.max' => 'You can add up to '.self::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Each photo must be an image file.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP files.',
            'photos.*.max' => 'Each photo must be 4 MB or smaller.',
            'photos.*.uploaded' => 'A photo could not be uploaded. It may be too large; try a smaller one.',
        ]);

        $paths = [];

        try {
            foreach ($request->file('photos', []) as $photo) {
                $paths[] = $photo->store('review-photos', 'public');
            }

            Review::create($subject + [
                'user_id' => $request->user()->id,
                'hotel_id' => null,
                'rating' => (int) $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'photos' => $paths,
                'status' => 'published',
                'verified_stay' => true,
            ]);
        } catch (\Throwable $exception) {
            // Do not leave photos behind for a review that was not saved.
            Storage::disk('public')->delete($paths);

            Log::error('Review could not be saved.', [
                'user_id' => $request->user()->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()
                ->withErrors(['review' => 'Your review could not be saved. Please try again.']);
        }

        return redirect()->route('bookings.index')
            ->with('success', 'Thank you. Your review is now published.');
    }
}
