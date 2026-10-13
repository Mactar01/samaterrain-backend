<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'address'        => 'required|string|max:300',
            'city'           => 'required|string|max:100',
            'latitude'       => 'nullable|numeric|between:-90,90',
            'longitude'      => 'nullable|numeric|between:-180,180',
            'type'           => 'required|in:natural_grass,artificial_grass,concrete,futsal,beach',
            'capacity'       => 'required|integer|min:2',
            'size'           => 'nullable|string|max:50',
            'price_per_hour' => 'required|numeric|min:0',
            'currency'       => 'nullable|string|max:5',
            'amenities'      => 'nullable|array',
            'is_active'      => 'nullable|boolean',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ];
    }
}
