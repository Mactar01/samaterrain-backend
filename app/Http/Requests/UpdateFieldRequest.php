<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'sometimes|required|string|max:200',
            'description'    => 'nullable|string',
            'address'        => 'sometimes|required|string|max:300',
            'city'           => 'sometimes|required|string|max:100',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'type'           => 'sometimes|required|in:natural_grass,artificial_grass,concrete,futsal,beach',
            'capacity'       => 'sometimes|required|integer|min:2',
            'size'           => 'nullable|string|max:50',
            'price_per_hour' => 'sometimes|required|numeric|min:0',
            'currency'       => 'nullable|string|max:5',
            'amenities'      => 'nullable|array',
            'is_active'      => 'nullable|boolean',
            'photo'          => 'nullable|image|max:5120',
        ];
    }
}

