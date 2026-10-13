<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnerInvitation extends Model
{
    protected $fillable = [
        'email',
        'business_name',
        'invited_by_name',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }
}