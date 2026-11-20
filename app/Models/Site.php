<?php

namespace App\Models;

use App\Enums\PowerSource;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_code',
        'name',
        'region_id',
        'address',
        'latitude',
        'longitude',
        'site_type',
        'power_source',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'site_type' => SiteType::class,
            'power_source' => PowerSource::class,
            'status' => SiteStatus::class,
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function engineers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_at');
    }

    public function visitReports(): HasMany
    {
        return $this->hasMany(VisitReport::class);
    }

    public function faults(): HasMany
    {
        return $this->hasMany(Fault::class);
    }
}
