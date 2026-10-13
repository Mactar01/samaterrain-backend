<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'photo',
        'name',
        'description',
        'address',
        'city',
        'latitude',
        'longitude',
        'type',
        'capacity',
        'size',
        'price_per_hour',
        'currency',
        'amenities',
        'is_active',
        'is_featured',
    ];

        protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return url('storage/' . $this->photo);
        }
        // Check if there is a primary image from the old relationship (fallback)
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            return url($this->primaryImage->url);
        }
        return null;
    }

    protected function casts(): array
    {
        return [
            'amenities'      => 'array',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'latitude'       => 'float',
            'longitude'      => 'float',
            'price_per_hour' => 'decimal:2',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function images()
    {
        return $this->hasMany(FieldImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(FieldImage::class)->where('is_primary', true);
    }

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function matches()
    {
        return $this->hasMany(FootballMatch::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Filtre les terrains dans un rayon (en km) autour d'un point GPS.
     * Formule de Haversine approximée.
     */
    public function scopeNearby($query, float $lat, float $lng, int $radiusKm = 10)
    {
        return $query->selectRaw("
                *,
                ( 6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            ", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');
    }

    // ─── Accessors ──────────────────────────────────────────────────

    public function getAvgRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }
}


