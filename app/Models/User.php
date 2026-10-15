<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les attributs assignables en masse.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'is_active',
        'must_change_password',
        'fcm_token',
    ];

    /**
     * Les attributs cachés dans les sérialisations.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    /**
     * Les castings de types.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    // ─── Helpers de rôle ───────────────────────────────────────────

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function owner()
    {
        return $this->hasOne(Owner::class);
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

    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function organizedMatches()
    {
        return $this->hasMany(FootballMatch::class, 'organizer_id');
    }

    public function joinedMatches()
    {
        return $this->belongsToMany(FootballMatch::class, 'match_players', 'user_id', 'match_id')
                    ->withPivot('status', 'joined_at');
    }
}
