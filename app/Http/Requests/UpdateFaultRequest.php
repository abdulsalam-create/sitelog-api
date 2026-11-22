<?php

namespace App\Http\Requests;

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('fault'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'category' => ['sometimes', Rule::enum(FaultCategory::class)],
            'severity' => ['sometimes', Rule::enum(FaultSeverity::class)],
            'assigned_to' => [
                'sometimes',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $user = $this->user();

                    if (! $user->isAdmin() && $user->role !== UserRole::Manager) {
                        $fail('Only managers can assign a fault.');
                    }

                    $engineer = User::find($value);

                    if ($engineer && $engineer->role !== UserRole::Engineer) {
                        $fail('Only engineers can be assigned a fault.');
                    }

                    $site = $this->route('fault')->site;

                    if ($engineer && ! $site->engineers()->whereKey($engineer->id)->exists()) {
                        $fail('That engineer is not assigned to this site.');
                    }
                },
            ],
        ];
    }
}
