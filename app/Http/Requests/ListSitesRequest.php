<?php

namespace App\Http\Requests;

use App\Enums\PowerSource;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'region_id' => ['sometimes', 'integer', 'exists:regions,id'],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
            'power_source' => ['sometimes', Rule::enum(PowerSource::class)],
            'site_type' => ['sometimes', Rule::enum(SiteType::class)],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
