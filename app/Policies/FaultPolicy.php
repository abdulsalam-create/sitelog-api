<?php

namespace App\Policies;

use App\Enums\FaultStatus;
use App\Enums\UserRole;
use App\Models\Fault;
use App\Models\User;

class FaultPolicy
{
    public function view(User $user, Fault $fault): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $fault->site?->region?->manager_id === $user->id;
        }

        return $fault->site->engineers()->whereKey($user->id)->exists();
    }

    public function update(User $user, Fault $fault): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $fault->site?->region?->manager_id === $user->id;
        }

        return $fault->raised_by === $user->id && $fault->status === FaultStatus::Open;
    }

    public function changeStatus(User $user, Fault $fault, FaultStatus $newStatus): bool
    {
        if ($user->isAdmin() || ($user->role === UserRole::Manager && $fault->site?->region?->manager_id === $user->id)) {
            return true;
        }

        if ($fault->assigned_to !== $user->id) {
            return false;
        }

        $engineerTransitions = [
            FaultStatus::Open->value => [FaultStatus::InProgress->value],
            FaultStatus::InProgress->value => [FaultStatus::Resolved->value],
        ];

        return in_array($newStatus->value, $engineerTransitions[$fault->status->value] ?? [], true);
    }
}
