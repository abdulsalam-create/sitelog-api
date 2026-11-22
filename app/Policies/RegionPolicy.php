<?php

namespace App\Policies;

use App\Models\Region;
use App\Models\User;

class RegionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Region $region): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function summary(User $user, Region $region): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $region->manager_id === $user->id;
    }
}
