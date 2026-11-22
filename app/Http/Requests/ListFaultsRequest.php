<?php

namespace App\Http\Requests;

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'severity' => ['sometimes', Rule::enum(FaultSeverity::class)],
            'category' => ['sometimes', Rule::enum(FaultCategory::class)],
            'status' => ['sometimes', Rule::enum(FaultStatus::class)],
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'sla_breached' => ['sometimes', 'in:true,false,1,0'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
