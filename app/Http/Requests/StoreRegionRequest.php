<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Region::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:regions,name'],
            'code' => ['required', 'string', 'max:10', 'unique:regions,code'],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Manager->value),
            ],
        ];
    }
}
