<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'user_id', 'hotel_id', 'supplier', 'supplier_reference', 'check_in', 'check_out',
        'adults', 'children', 'rooms', 'currency', 'total_amount', 'status',
        'payment_status', 'guest_name', 'guest_email', 'notes',
    ];
    protected function casts(): array {
        return ['check_in' => 'date', 'check_out' => 'date', 'total_amount' => 'decimal:2'];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }
}
