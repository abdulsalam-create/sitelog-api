<?php

namespace App\Services;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Events\FaultRaised;
use App\Models\Fault;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class FaultService
{
    public function create(Site $site, User $raiser, array $data): Fault
    {
        $severity = FaultSeverity::from($data['severity']);

        $fault = $site->faults()->create([
            ...Arr::except($data, ['severity']),
            'raised_by' => $raiser->id,
            'severity' => $severity->value,
            'status' => FaultStatus::Open,
            'sla_due_at' => now()->addHours($severity->slaHours()),
        ]);

        FaultRaised::dispatch($fault);

        return $fault;
    }

    public function update(Fault $fault, array $data): Fault
    {
        $fault->update($data);

        return $fault;
    }

    public function changeStatus(Fault $fault, FaultStatus $newStatus): Fault
    {
        $allowed = [
            FaultStatus::Open->value => [FaultStatus::InProgress->value],
            FaultStatus::InProgress->value => [FaultStatus::Resolved->value],
            FaultStatus::Resolved->value => [FaultStatus::Closed->value, FaultStatus::Open->value],
        ];

        if (! in_array($newStatus->value, $allowed[$fault->status->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move a fault from {$fault->status->value} to {$newStatus->value}.",
            ]);
        }

        $fault->status = $newStatus;

        if ($newStatus === FaultStatus::Resolved) {
            $fault->resolved_at = now();
        }

        if ($newStatus === FaultStatus::Open) {
            $fault->resolved_at = null;
            $fault->sla_due_at = now()->addHours($fault->severity->slaHours());
        }

        $fault->save();

        return $fault;
    }
}
