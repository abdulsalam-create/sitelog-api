<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'review_comment' => ['required', 'string', 'max:1000'],
        ];
    }
}
