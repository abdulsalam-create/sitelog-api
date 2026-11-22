<?php

namespace App\Notifications;

use App\Models\Fault;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalFaultNotification extends Notification implements ShouldQueue
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
            'type' => 'critical_fault',
            'message' => "A critical fault was raised on {$this->fault->site->site_code}: {$this->fault->title}.",
            'fault_id' => $this->fault->id,
            'site_id' => $this->fault->site_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Critical fault raised')
            ->line("{$this->fault->title} was raised on {$this->fault->site->site_code}.")
            ->line('SLA deadline: '.$this->fault->sla_due_at->format('Y-m-d H:i'));
    }
}
