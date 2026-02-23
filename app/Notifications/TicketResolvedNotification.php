<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketResolvedNotification extends Notification
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
            ->subject('Ticket resolved: ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your reported issue has been resolved.')
            ->line('Property: ' . $this->ticket->property->address)
            ->line('Title: ' . $this->ticket->title)
            ->line('Resolved at: ' . $this->ticket->resolved_at->format('d.m.Y H:i'))
            ->action('View in RentFlow', config('app.url'))
            ->line('If the issue persists, please create a new ticket.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_resolved',
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'property_address' => $this->ticket->property->address,
        ];
    }
}
