<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Hotel extends Model
{
    protected $fillable = [
        'supplier', 'supplier_hotel_id', 'name', 'slug', 'description', 'address',
        'city', 'country', 'country_code', 'latitude', 'longitude', 'stars',
        'rating', 'review_count', 'image_url', 'amenities', 'source_url', 'indicative_price',
    ];

    protected function casts(): array { return ['amenities' => 'array']; }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }
    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function favouritedBy(): BelongsToMany { return $this->belongsToMany(User::class, 'favourites')->withTimestamps(); }
}
