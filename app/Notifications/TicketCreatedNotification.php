<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New ticket: ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new issue has been reported.')
            ->line('Property: ' . $this->ticket->property->address)
            ->line('Title: ' . $this->ticket->title)
            ->line('Category: ' . $this->ticket->category)
            ->line('Priority: ' . $this->ticket->priority)
            ->line('Reported by: ' . $this->ticket->tenant->name)
            ->action('View ticket in RentFlow', config('app.url'))
            ->line('Please review and assign this ticket.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_created',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'property_address' => $this->ticket->property->address,
            'priority' => $this->ticket->priority,
        ];
    }
}
