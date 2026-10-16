<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $startTime = $this->input('start_time');
                    if (!$startTime) return;
                    
                    if ($value === $startTime) {
                        $fail("L'heure de fin ne peut pas être identique à l'heure de début.");
                        return;
                    }

                    // Permettre les créneaux qui traversent minuit (ex: 23:00 -> 00:00, 23:30 -> 01:00)
                    $isNightCrossing = ($value === '00:00' || ($startTime >= '18:00' && $value <= '06:00'));

                    // Sinon, l'heure de fin doit être strictement supérieure à l'heure de début
                    if (!$isNightCrossing && $value <= $startTime) {
                        $fail("L'heure de fin doit être postérieure à l'heure de début.");
                    }
                }
            ],
            'price'      => 'nullable|numeric|min:0'
        ];
    }
}
