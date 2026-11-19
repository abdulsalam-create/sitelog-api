<?php

namespace App\Models;

use App\Enums\FaultCategory;
use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Fault extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'raised_by',
        'assigned_to',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'sla_due_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => FaultCategory::class,
            'severity' => FaultSeverity::class,
            'status' => FaultStatus::class,
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'photoable');
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_due_at->isPast()
            && ! in_array($this->status, [FaultStatus::Resolved, FaultStatus::Closed], true);
    }
}
