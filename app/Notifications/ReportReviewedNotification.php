<?php

namespace App\Notifications;

use App\Models\VisitReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReviewedNotification extends Notification implements ShouldQueue
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
            'type' => 'report_reviewed',
            'message' => "Your visit report for {$this->report->site->site_code} was {$this->report->status->value}.",
            'report_id' => $this->report->id,
            'site_id' => $this->report->site_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your visit report was '.$this->report->status->value)
            ->line("Your report for {$this->report->site->site_code} was {$this->report->status->value}.")
            ->when($this->report->review_comment, fn (MailMessage $mail) => $mail->line('Reviewer comment: '.$this->report->review_comment));
    }
}
