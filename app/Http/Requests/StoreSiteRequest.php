<?php

namespace App\Http\Requests;

use App\Enums\PowerSource;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Site::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('site_code')) {
            $this->merge(['site_code' => strtoupper($this->input('site_code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'site_code' => ['required', 'string', 'max:20', 'unique:sites,site_code'],
            'name' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'site_type' => ['required', Rule::enum(SiteType::class)],
            'power_source' => ['required', Rule::enum(PowerSource::class)],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
        ];
    }
}
