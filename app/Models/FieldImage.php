<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldImage extends Model
{
    public $timestamps = false;

    protected $fillable = ['field_id', 'url', 'is_primary', 'sort_order'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }
}
