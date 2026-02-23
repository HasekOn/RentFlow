<?php

namespace App\Notifications;

use App\Models\Lease;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaseExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Lease $lease
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysLeft = now()->diffInDays($this->lease->end_date);

        return (new MailMessage)
            ->subject('Lease expiring soon — ' . $this->lease->property->address)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your lease is expiring in ' . $daysLeft . ' days.')
            ->line('Property: ' . $this->lease->property->address)
            ->line('Disposition: ' . $this->lease->property->disposition)
            ->line('End date: ' . $this->lease->end_date->format('d.m.Y'))
            ->action('View in RentFlow', config('app.url'))
            ->line('Please contact your landlord to discuss renewal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lease_expiring',
            'lease_id' => $this->lease->id,
            'property_address' => $this->lease->property->address,
            'end_date' => $this->lease->end_date->toDateString(),
            'days_left' => now()->diffInDays($this->lease->end_date),
        ];
    }
}
