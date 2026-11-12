<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['user_id', 'hotel_id', 'rating', 'title', 'body', 'photo_path', 'status', 'verified_stay'];
    protected function casts(): array { return ['verified_stay' => 'boolean']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function hotel(): BelongsTo { return $this->belongsTo(Hotel::class); }
}
