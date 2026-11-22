<?php

namespace App\Http\Requests;

use App\Enums\FaultStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeFaultStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', [
            $this->route('fault'),
            FaultStatus::from($this->input('status')),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FaultStatus::class)],
        ];
    }
}
