<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'hotel_id', 'supplier', 'supplier_reference', 'supplier_hotel_id',
        'hotel_name', 'room_name', 'board', 'hotel_confirmation_code', 'client_reference',
        'check_in', 'check_out', 'adults', 'children', 'rooms', 'currency', 'total_amount',
        'status', 'payment_status', 'cancelled_at', 'cancellation_fee', 'refund_amount',
        'refundable', 'is_sandbox', 'guest_name', 'guest_email', 'guest_phone', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
            'cancellation_fee' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'refundable' => 'boolean',
            'is_sandbox' => 'boolean',
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

    /**
     * Whether this booking is for a live supplier hotel, as opposed
     * to a hotel stored in the local hotels table.
     */
    public function isLive(): bool
    {
        return !empty($this->supplier_hotel_id);
    }

    /**
     * Whether this booking has been cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Whether the guest can cancel this booking online: it must be a
     * confirmed live booking whose check-in day has not passed.
     */
    public function canBeCancelled(): bool
    {
        return $this->isLive()
            && $this->status === 'confirmed'
            && !empty($this->supplier_reference)
            && $this->check_in !== null
            && $this->check_in->copy()->endOfDay()->isFuture();
    }

    /**
     * The hotel's name, from the supplier or from the local record.
     */
    public function hotelDisplayName(): string
    {
        return $this->hotel_name
            ?: ($this->hotel?->name ?? 'Hotel');
    }
}
