<?php

namespace App\Notifications;

use App\Models\VisitReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportSubmittedNotification extends Notification implements ShouldQueue
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
            'type' => 'report_submitted',
            'message' => "{$this->report->engineer->name} submitted a visit report for {$this->report->site->site_code}.",
            'report_id' => $this->report->id,
            'site_id' => $this->report->site_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New visit report submitted')
            ->line("{$this->report->engineer->name} submitted a report for {$this->report->site->site_code} on {$this->report->visit_date->format('Y-m-d')}.")
            ->line('Log in to SiteLog to review it.');
    }
}
