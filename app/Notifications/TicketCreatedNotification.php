<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New ticket: '.$this->ticket->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new issue has been reported.')
            ->line('Property: '.$this->ticket->property?->address)
            ->line('Title: '.$this->ticket->title)
            ->line('Category: '.$this->ticket->category->value)
            ->line('Priority: '.$this->ticket->priority->value)
            ->line('Reported by: '.$this->ticket->tenant?->name)
            ->action('View ticket in RentFlow', config('app.url'))
            ->line('Please review and assign this ticket.');
    }

    /**
     * Zvoneček (fáze 5) čte `title` a `message`, ostatní klíče jsou odkazy na záznam (#47).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $address = $this->ticket->property?->address;

        return [
            'type' => 'ticket_created',
            'title' => 'Nový tiket',
            'message' => "„{$this->ticket->title}“ – {$address}",
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'property_address' => $address,
            'priority' => $this->ticket->priority->value,
        ];
    }
}
