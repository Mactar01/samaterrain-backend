<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// Nommé FootballMatch pour éviter le conflit avec le mot-clé PHP `match`
class FootballMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'field_id', 'reservation_id', 'organizer_id',
        'title', 'description', 'date', 'start_time',
        'max_players', 'level', 'status', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'is_public' => 'boolean',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function players()
    {
        return $this->belongsToMany(User::class, 'match_players', 'match_id', 'user_id')
                    ->withPivot('status', 'joined_at');
    }

    // ─── Helpers ────────────────────────────────────────────────────

    public function isFull(): bool
    {
        return $this->players()->where('match_players.status', 'confirmed')->count() >= $this->max_players;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function availableSpots(): int
    {
        $confirmed = $this->players()->where('match_players.status', 'confirmed')->count();
        return max(0, $this->max_players - $confirmed);
    }
}
