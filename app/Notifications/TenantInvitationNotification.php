<?php

namespace App\Notifications;

use App\Models\Lease;
use App\Notifications\Concerns\CzechText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification
{
    use CzechText, Queueable;

    public function __construct(
        private readonly Lease $lease
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->lease->property;

        return (new MailMessage)
            ->subject('Welcome to RentFlow — Your new lease')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have been added as a tenant in RentFlow.')
            ->line('Property: '.$property->address)
            ->line('Disposition: '.$property->disposition)
            ->line('Monthly rent: '.Money::format(Money::cents($this->lease->rent_amount)))
            ->line('Lease starts: '.$this->lease->start_date->format('d.m.Y'))
            ->line('Lease ends: '.($this->lease->end_date ? $this->lease->end_date->format('d.m.Y') : 'Indefinite'))
            ->action('Log in to RentFlow', config('app.url'))
            ->line('You can now view your apartment details, submit meter readings, and report issues.');
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
            'type' => 'tenant_invitation',
            'title' => 'Nová nájemní smlouva',
            'message' => "Máte novou nájemní smlouvu na {$address} od {$this->czDate($this->lease->start_date)}.",
            'lease_id' => $this->lease->id,
            'property_address' => $address,
            'start_date' => $this->lease->start_date->toDateString(),
        ];
    }
}
