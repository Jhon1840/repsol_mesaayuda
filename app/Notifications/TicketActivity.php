<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TicketActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId, public string $code, public string $message)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['ticket_id' => $this->ticketId, 'code' => $this->code, 'message' => $this->message, 'url' => '/tickets/'.$this->ticketId];
    }
}
