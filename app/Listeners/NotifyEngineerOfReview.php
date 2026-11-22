<?php

namespace App\Listeners;

use App\Events\ReportReviewed;
use App\Notifications\ReportReviewedNotification;

class NotifyEngineerOfReview
{
    public function handle(ReportReviewed $event): void
    {
        $engineer = $event->report->engineer;

        if ($engineer) {
            $engineer->notify(new ReportReviewedNotification($event->report));
        }
    }
}
