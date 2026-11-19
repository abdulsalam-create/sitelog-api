<?php

namespace App\Models;

use App\Enums\BatteryHealth;
use App\Enums\ReportStatus;
use App\Enums\VisitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VisitReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'engineer_id',
        'visit_date',
        'visit_type',
        'grid_power_available',
        'generator_hours',
        'fuel_level_percent',
        'battery_health',
        'work_summary',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'visit_type' => VisitType::class,
            'grid_power_available' => 'boolean',
            'generator_hours' => 'decimal:2',
            'battery_health' => BatteryHealth::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function maintenanceItems(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class);
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ReportStatus::Draft, ReportStatus::Rejected], true);
    }
}
