<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_slot_id' => 'required|exists:time_slots,id',
            'notes'        => 'nullable|string|max:500'
        ];
    }
}
