<?php

namespace App\Notifications;

use App\Models\VisitReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowFuelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly VisitReport $report) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_fuel',
            'message' => "Low fuel recorded on {$this->report->site->site_code}: {$this->report->fuel_level_percent}%.",
            'report_id' => $this->report->id,
            'site_id' => $this->report->site_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low fuel alert')
            ->line("Fuel level on {$this->report->site->site_code} is at {$this->report->fuel_level_percent}%.");
    }
}
