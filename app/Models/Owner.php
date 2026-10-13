<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Owner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'address',
        'siret',
        'verified_at',
        'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'verified_at'     => 'datetime',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}
