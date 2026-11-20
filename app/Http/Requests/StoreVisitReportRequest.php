<?php

namespace App\Http\Requests;

use App\Enums\BatteryHealth;
use App\Enums\MaintenanceAction;
use App\Enums\MaintenanceUnit;
use App\Enums\VisitType;
use App\Models\VisitReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createReport', $this->route('site'));
    }

    public function rules(): array
    {
        $siteId = $this->route('site')->id;
        $engineerId = $this->user()->id;

        return [
            'visit_date' => [
                'required',
                'date',
                'before_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail) use ($siteId, $engineerId) {
                    $exists = VisitReport::where('site_id', $siteId)
                        ->where('engineer_id', $engineerId)
                        ->whereDate('visit_date', $value)
                        ->exists();

                    if ($exists) {
                        $fail('You already logged a report for this site on that date.');
                    }
                },
            ],
            'visit_type' => ['required', Rule::enum(VisitType::class)],
            'grid_power_available' => ['required', 'boolean'],
            'generator_hours' => ['required', 'numeric', 'min:0', 'max:9999'],
            'fuel_level_percent' => ['required', 'integer', 'between:0,100'],
            'battery_health' => ['required', Rule::enum(BatteryHealth::class)],
            'work_summary' => ['required', 'string', 'max:5000'],
            'maintenance_items' => ['sometimes', 'array'],
            'maintenance_items.*.item_name' => ['required_with:maintenance_items', 'string', 'max:255'],
            'maintenance_items.*.action' => ['required_with:maintenance_items', Rule::enum(MaintenanceAction::class)],
            'maintenance_items.*.quantity' => ['required_with:maintenance_items', 'numeric', 'min:0'],
            'maintenance_items.*.unit' => ['required_with:maintenance_items', Rule::enum(MaintenanceUnit::class)],
        ];
    }
}
