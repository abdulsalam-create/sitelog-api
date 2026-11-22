<?php

namespace App\Listeners;

use App\Events\ReportSubmitted;
use App\Notifications\ReportSubmittedNotification;

class NotifyManagerOfReportSubmission
{
    public function handle(ReportSubmitted $event): void
    {
        $manager = $event->report->site->region?->manager;

        if ($manager) {
            $manager->notify(new ReportSubmittedNotification($event->report));
        }
    }
}
