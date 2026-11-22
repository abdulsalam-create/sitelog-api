<?php

namespace App\Http\Requests;

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('raiseFault', $this->route('site'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(FaultCategory::class)],
            'severity' => ['required', Rule::enum(FaultSeverity::class)],
        ];
    }
}
