<?php

namespace App\Http\Requests;

use App\Enums\BatteryHealth;
use App\Enums\MaintenanceAction;
use App\Enums\MaintenanceUnit;
use App\Enums\VisitType;
use App\Models\VisitReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('report'));
    }

    public function rules(): array
    {
        $report = $this->route('report');

        return [
            'visit_date' => [
                'sometimes',
                'date',
                'before_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail) use ($report) {
                    $exists = VisitReport::where('site_id', $report->site_id)
                        ->where('engineer_id', $report->engineer_id)
                        ->where('id', '!=', $report->id)
                        ->whereDate('visit_date', $value)
                        ->exists();

                    if ($exists) {
                        $fail('You already logged a report for this site on that date.');
                    }
                },
            ],
            'visit_type' => ['sometimes', Rule::enum(VisitType::class)],
            'grid_power_available' => ['sometimes', 'boolean'],
            'generator_hours' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'fuel_level_percent' => ['sometimes', 'integer', 'between:0,100'],
            'battery_health' => ['sometimes', Rule::enum(BatteryHealth::class)],
            'work_summary' => ['sometimes', 'string', 'max:5000'],
            'maintenance_items' => ['sometimes', 'array'],
            'maintenance_items.*.item_name' => ['required_with:maintenance_items', 'string', 'max:255'],
            'maintenance_items.*.action' => ['required_with:maintenance_items', Rule::enum(MaintenanceAction::class)],
            'maintenance_items.*.quantity' => ['required_with:maintenance_items', 'numeric', 'min:0'],
            'maintenance_items.*.unit' => ['required_with:maintenance_items', Rule::enum(MaintenanceUnit::class)],
        ];
    }
}
