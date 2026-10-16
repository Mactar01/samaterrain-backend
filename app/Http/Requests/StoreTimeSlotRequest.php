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
                    
                    // Si l'heure de fin est 00:00 (minuit), c'est valide tant que l'heure de début n'est pas 00:00
                    if ($value === '00:00') {
                        if ($startTime === '00:00') {
                            $fail("L'heure de fin ne peut pas être identique à l'heure de début.");
                        }
                        return;
                    }

                    // Sinon, l'heure de fin doit être strictement supérieure à l'heure de début
                    if ($value <= $startTime) {
                        $fail("L'heure de fin doit être postérieure à l'heure de début.");
                    }
                }
            ],
            'price'      => 'nullable|numeric|min:0'
        ];
    }
}
