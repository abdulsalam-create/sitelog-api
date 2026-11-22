<?php

namespace App\Listeners;

use App\Events\LowFuelRecorded;
use App\Notifications\LowFuelNotification;

class NotifyManagerOfLowFuel
{
    public function handle(LowFuelRecorded $event): void
    {
        $manager = $event->report->site->region?->manager;

        if ($manager) {
            $manager->notify(new LowFuelNotification($event->report));
        }
    }
}
