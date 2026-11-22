<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function view(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $site->region?->manager_id === $user->id;
        }

        return $site->engineers()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Site $site): bool
    {
        return $this->managesSite($user, $site);
    }

    public function decommission(User $user, Site $site): bool
    {
        return $this->managesSite($user, $site);
    }

    public function assignEngineer(User $user, Site $site): bool
    {
        return $this->managesSite($user, $site);
    }

    public function removeEngineer(User $user, Site $site): bool
    {
        return $this->managesSite($user, $site);
    }

    public function viewReports(User $user, Site $site): bool
    {
        return $this->view($user, $site);
    }

    public function createReport(User $user, Site $site): bool
    {
        return $user->role === UserRole::Engineer
            && $site->engineers()->whereKey($user->id)->exists();
    }

    public function raiseFault(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $site->region?->manager_id === $user->id;
        }

        return $site->engineers()->whereKey($user->id)->exists();
    }

    private function managesSite(User $user, Site $site): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->role === UserRole::Manager
            && $site->region?->manager_id === $user->id;
    }
}
