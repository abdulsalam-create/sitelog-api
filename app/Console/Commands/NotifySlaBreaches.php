<?php

namespace App\Console\Commands;

use App\Enums\FaultStatus;
use App\Models\Fault;
use App\Notifications\SlaBreachNotification;
use Illuminate\Console\Command;

class NotifySlaBreaches extends Command
{
    protected $signature = 'sla:notify-breaches';

    protected $description = 'Notify regional managers of faults that have breached their SLA';

    public function handle(): int
    {
        $breached = Fault::with('site.region.manager')
            ->where('sla_due_at', '<', now())
            ->whereNull('sla_notified_at')
            ->whereNotIn('status', [FaultStatus::Resolved, FaultStatus::Closed])
            ->get();

        foreach ($breached as $fault) {
            $manager = $fault->site?->region?->manager;

            if ($manager) {
                $manager->notify(new SlaBreachNotification($fault));
            }

            $fault->update(['sla_notified_at' => now()]);
        }

        $this->info("Notified managers about {$breached->count()} SLA breaches.");

        return self::SUCCESS;
    }
}
