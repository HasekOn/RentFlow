<?php

namespace App\Notifications;

use App\Models\Lease;
use App\Models\User;
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

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $property = $this->lease->property;

        return (new MailMessage)
            ->subject('RentFlow — Lease invitation')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A landlord has invited you to a lease in RentFlow. Please accept or decline it.')
            ->line('Property: '.$property?->address)
            ->line('Disposition: '.$property?->disposition)
            ->line('Monthly rent: '.Money::format(Money::cents($this->lease->rent_amount)))
            ->line('Lease starts: '.$this->lease->start_date->format('d.m.Y'))
            ->line('Lease ends: '.($this->lease->end_date ? $this->lease->end_date->format('d.m.Y') : 'Indefinite'))
            ->action('Log in to RentFlow', config('app.url'))
            ->line('After you accept, you can view your apartment details, submit meter readings, and report issues.');
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
            'title' => 'Pozvánka ke smlouvě',
            'message' => "Pronajímatel vás zve k\u{00A0}nájemní smlouvě na {$address} od {$this->czDate($this->lease->start_date)}. Přijměte ji, nebo odmítněte.",
            'lease_id' => $this->lease->id,
            'property_address' => $address,
            'start_date' => $this->lease->start_date->toDateString(),
        ];
    }
}
