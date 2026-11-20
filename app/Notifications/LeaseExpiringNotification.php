<?php

namespace App\Notifications;

use App\Models\Lease;
use App\Models\User;
use App\Notifications\Concerns\CzechText;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseExpiringNotification extends Notification
{
    use CzechText, Queueable;

    public function __construct(
        private readonly Lease $lease
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $daysLeft = now()->diffInDays($this->lease->end_date);

        return (new MailMessage)
            ->subject('Lease expiring soon — '.$this->lease->property?->address)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your lease is expiring in '.$daysLeft.' days.')
            ->line('Property: '.$this->lease->property?->address)
            ->line('Disposition: '.$this->lease->property?->disposition)
            ->line('End date: '.$this->lease->end_date?->format('d.m.Y'))
            ->action('View in RentFlow', config('app.url'))
            ->line('Please contact your landlord to discuss renewal.');
    }

    /**
     * Zvoneček (fáze 5) čte `title` a `message`, ostatní klíče jsou odkazy na záznam (#47).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $address = $this->lease->property?->address;

        return [
            'type' => 'lease_expiring',
            'title' => 'Smlouva brzy končí',
            'message' => "Nájemní smlouva na {$address} končí {$this->czDate($this->lease->end_date)}.",
            'lease_id' => $this->lease->id,
            'property_address' => $address,
            'end_date' => $this->lease->end_date?->toDateString(),
            'days_left' => (int) today()->diffInDays($this->lease->end_date),
        ];
    }
}
