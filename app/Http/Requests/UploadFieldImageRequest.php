<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFieldImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorizations handled by policy
    }

    public function rules(): array
    {
        return [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_primary' => 'nullable|boolean'
        ];
    }
}
