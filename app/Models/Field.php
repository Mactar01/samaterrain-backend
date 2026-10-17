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

    public static function resolveImageUrl(?string $raw): ?string
    {
        if (!$raw || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);

        if (str_starts_with($raw, 'data:image')) {
            return $raw;
        }

        // Extract relative storage path if it points to local storage
        $relPath = null;
        if (preg_match('#/storage/(.+)$#', $raw, $m)) {
            $relPath = $m[1];
        } elseif (str_starts_with($raw, 'storage/')) {
            $relPath = substr($raw, strlen('storage/'));
        } elseif (!str_starts_with($raw, 'http://') && !str_starts_with($raw, 'https://')) {
            $relPath = ltrim($raw, '/');
        }

        if ($relPath !== null) {
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($relPath)) {
                return null;
            }
            $baseUrl = request() && request()->getHttpHost()
                ? request()->getSchemeAndHttpHost()
                : rtrim(config('app.url', 'http://localhost:8000'), '/');
            return $baseUrl . '/storage/' . ltrim($relPath, '/');
        }

        return $raw;
    }

    public static function uploadedFileToDataUri($file, int $maxWidth = 1200, int $maxHeight = 800, int $quality = 82): ?string
    {
        try {
            $realPath = $file->getRealPath();
            if (!$realPath || !file_exists($realPath)) {
                return null;
            }
            $contents = file_get_contents($realPath);
            if ($contents === false) {
                return null;
            }

            if (function_exists('imagecreatefromstring')) {
                $srcImg = @imagecreatefromstring($contents);
                if ($srcImg !== false) {
                    $width = imagesx($srcImg);
                    $height = imagesy($srcImg);
                    $newWidth = $width;
                    $newHeight = $height;

                    if ($width > $maxWidth || $height > $maxHeight) {
                        $ratio = min($maxWidth / $width, $maxHeight / $height);
                        $newWidth = max(1, (int) round($width * $ratio));
                        $newHeight = max(1, (int) round($height * $ratio));
                    }

                    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
                    $white = imagecolorallocate($dstImg, 255, 255, 255);
                    imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $white);
                    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                    ob_start();
                    imagejpeg($dstImg, null, $quality);
                    $jpegData = ob_get_clean();
                    imagedestroy($srcImg);
                    imagedestroy($dstImg);

                    if ($jpegData) {
                        return 'data:image/jpeg;base64,' . base64_encode($jpegData);
                    }
                }
            }

            $mime = $file->getMimeType() ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            $resolved = self::resolveImageUrl($this->photo);
            if ($resolved) {
                return $resolved;
            }
        }
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            $resolved = self::resolveImageUrl($this->primaryImage->getRawOriginal('url') ?? $this->primaryImage->url);
            if ($resolved) {
                return $resolved;
            }
        }
        if ($this->relationLoaded('images') && $this->images) {
            foreach ($this->images as $img) {
                $resolved = self::resolveImageUrl($img->getRawOriginal('url') ?? $img->url);
                if ($resolved) {
                    return $resolved;
                }
            }
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


