<?php

namespace App\Notifications;

use App\Models\Fault;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreachNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Fault $fault) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'sla_breached',
            'message' => "SLA breached on {$this->fault->site->site_code}: {$this->fault->title}.",
            'fault_id' => $this->fault->id,
            'site_id' => $this->fault->site_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Fault has breached its SLA')
            ->line("{$this->fault->title} on {$this->fault->site->site_code} passed its SLA deadline at {$this->fault->sla_due_at->format('Y-m-d H:i')}.");
    }
}
