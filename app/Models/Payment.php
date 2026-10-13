<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id', 'amount', 'currency',
        'method', 'status', 'transaction_ref', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount'   => 'decimal:2',
            'paid_at'  => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isPending(): bool   { return $this->status === 'pending'; }
}
