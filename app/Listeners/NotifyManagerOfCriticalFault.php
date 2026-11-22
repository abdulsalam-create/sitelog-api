<?php

namespace App\Listeners;

use App\Enums\FaultSeverity;
use App\Events\FaultRaised;
use App\Notifications\CriticalFaultNotification;

class NotifyManagerOfCriticalFault
{
    public function handle(FaultRaised $event): void
    {
        if ($event->fault->severity !== FaultSeverity::Critical) {
            return;
        }

        $manager = $event->fault->site->region?->manager;

        if ($manager) {
            $manager->notify(new CriticalFaultNotification($event->fault));
        }
    }
}
