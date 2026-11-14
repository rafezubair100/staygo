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
        'status', 'payment_status', 'refundable', 'is_sandbox',
        'guest_name', 'guest_email', 'guest_phone', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'total_amount' => 'decimal:2',
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
     * The hotel's name, from the supplier or from the local record.
     */
    public function hotelDisplayName(): string
    {
        return $this->hotel_name
            ?: ($this->hotel?->name ?? 'Hotel');
    }
}