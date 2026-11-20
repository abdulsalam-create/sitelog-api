<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use App\Enums\VisitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'engineer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'visit_type' => ['sometimes', Rule::enum(VisitType::class)],
            'status' => ['sometimes', Rule::enum(ReportStatus::class)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
