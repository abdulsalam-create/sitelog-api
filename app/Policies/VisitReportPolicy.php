<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VisitReport;

class VisitReportPolicy
{
    public function view(User $user, VisitReport $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === UserRole::Manager) {
            return $report->site?->region?->manager_id === $user->id;
        }

        return $report->site->engineers()->whereKey($user->id)->exists();
    }

    public function update(User $user, VisitReport $report): bool
    {
        return $this->ownsEditableReport($user, $report);
    }

    public function delete(User $user, VisitReport $report): bool
    {
        return $this->ownsEditableReport($user, $report);
    }

    public function submit(User $user, VisitReport $report): bool
    {
        return $this->ownsEditableReport($user, $report);
    }

    public function review(User $user, VisitReport $report): bool
    {
        if ($report->status !== ReportStatus::Submitted) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->role === UserRole::Manager
            && $report->site?->region?->manager_id === $user->id;
    }

    private function ownsEditableReport(User $user, VisitReport $report): bool
    {
        return $report->engineer_id === $user->id && $report->isEditable();
    }
}
