<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'hotel_id', 'booking_id', 'flight_booking_id', 'supplier_hotel_id',
        'subject_name', 'rating', 'title', 'body', 'photo_path', 'photos',
        'status', 'verified_stay',
    ];

    protected function casts(): array
    {
        return [
            'verified_stay' => 'boolean',
            'photos' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function flightBooking(): BelongsTo
    {
        return $this->belongsTo(FlightBooking::class);
    }

    /**
     * Whether this review is about a flight.
     */
    public function isFlight(): bool
    {
        return !empty($this->flight_booking_id);
    }

    /**
     * What the review is about: a hotel name or a flight route.
     */
    public function subjectName(): string
    {
        return $this->subject_name
            ?: ($this->hotel?->name ?? ($this->isFlight() ? 'Flight' : 'Hotel'));
    }

    /**
     * Web addresses of the review's photos.
     */
    public function photoUrls(): array
    {
        $paths = is_array($this->photos) ? $this->photos : [];

        // Reviews written before several photos were supported.
        if ($this->photo_path) {
            array_unshift($paths, $this->photo_path);
        }

        return array_values(array_map(
            fn ($path) => asset('storage/'.ltrim((string) $path, '/')),
            array_filter($paths, fn ($path) => is_string($path) && $path !== '')
        ));
    }

    /**
     * The reviewer's first name only, to protect their privacy.
     */
    public function reviewerName(): string
    {
        $name = trim((string) ($this->user?->name ?? ''));

        return $name !== '' ? explode(' ', $name)[0] : 'Staygo guest';
    }
}
