<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightBooking extends Model
{
    protected $fillable = [
        'user_id', 'supplier', 'supplier_booking_id', 'booking_ref', 'pnr',
        'status', 'payment_status', 'is_sandbox',
        'trip', 'origin', 'destination', 'origin_name', 'destination_name',
        'depart_at', 'return_at', 'airlines', 'cabin', 'refundable',
        'adults', 'children', 'infants', 'currency', 'total_amount',
        'itinerary', 'passengers', 'contact_name', 'contact_email', 'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'return_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'is_sandbox' => 'boolean',
            'refundable' => 'boolean',
            'itinerary' => 'array',
            'passengers' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the airline has confirmed this booking.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Whether this booking has been cancelled.
     */
    public function isCancelled(): bool
    {
        return str_starts_with((string) $this->status, 'cancelled');
    }

    /**
     * A short label for the booking status.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'confirmed' => 'Confirmed',
            'pending_confirmation', 'created' => 'Awaiting airline confirmation',
            'cancelled', 'cancelled_with_charges' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * Total number of travellers.
     */
    public function travellerCount(): int
    {
        return (int) $this->adults + (int) $this->children + (int) $this->infants;
    }
}