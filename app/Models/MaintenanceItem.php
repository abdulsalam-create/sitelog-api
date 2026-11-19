<?php

namespace App\Models;

use App\Enums\MaintenanceAction;
use App\Enums\MaintenanceUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_report_id',
        'item_name',
        'action',
        'quantity',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'action' => MaintenanceAction::class,
            'quantity' => 'decimal:2',
            'unit' => MaintenanceUnit::class,
        ];
    }

    public function visitReport(): BelongsTo
    {
        return $this->belongsTo(VisitReport::class);
    }
}
