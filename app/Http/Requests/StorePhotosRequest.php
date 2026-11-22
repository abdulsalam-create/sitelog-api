<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.max' => 'A report or fault can hold at most 10 photos.',
            'photos.*.mimes' => 'Only jpg, png and webp images are allowed.',
            'photos.*.max' => 'Each photo must be 5 MB or smaller.',
        ];
    }
}
