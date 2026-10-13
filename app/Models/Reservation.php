<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_phone',
        'field_id',
        'time_slot_id',
        'status',
        'total_price',
        'commission',
        'notes',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'total_price'  => 'decimal:2',
            'commission'   => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    // ─── Relations ──────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function match()
    {
        return $this->hasOne(FootballMatch::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isConfirmed(): bool  { return $this->status === 'confirmed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }

    public function canBeCancelled(): bool
    {
        // Annulable uniquement si pending ou confirmée (pas encore passée)
        return in_array($this->status, ['pending', 'confirmed'])
            && $this->timeSlot->date->isFuture();
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
