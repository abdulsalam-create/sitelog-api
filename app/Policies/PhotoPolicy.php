<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Fault;
use App\Models\Photo;
use App\Models\User;
use App\Models\VisitReport;

class PhotoPolicy
{
    public function create(User $user, VisitReport|Fault $parent): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $parent->site?->region?->manager_id === $user->id;
        }

        return $parent->site->engineers()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Photo $photo): bool
    {
        if ($user->isAdmin() || $photo->uploaded_by === $user->id) {
            return true;
        }

        if ($user->role !== UserRole::Manager) {
            return false;
        }

        return $photo->photoable?->site?->region?->manager_id === $user->id;
    }
}
