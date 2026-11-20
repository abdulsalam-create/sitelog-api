<?php

namespace App\Http\Requests;

use App\Enums\PowerSource;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('site'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('site_code')) {
            $this->merge(['site_code' => strtoupper($this->input('site_code'))]);
        }
    }

    public function rules(): array
    {
        $site = $this->route('site');

        return [
            'site_code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('sites', 'site_code')->ignore($site->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'region_id' => [
                'sometimes',
                'integer',
                'exists:regions,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $user = $this->user();

                    if ($user->role !== UserRole::Manager) {
                        return;
                    }

                    if (! $user->managedRegions()->whereKey($value)->exists()) {
                        $fail('You can only move sites into regions you manage.');
                    }
                },
            ],
            'address' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'site_type' => ['sometimes', Rule::enum(SiteType::class)],
            'power_source' => ['sometimes', Rule::enum(PowerSource::class)],
            'status' => ['sometimes', Rule::enum(SiteStatus::class)],
        ];
    }
}
