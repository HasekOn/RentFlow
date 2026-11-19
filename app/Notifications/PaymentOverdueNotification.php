<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Notifications\Concerns\CzechText;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentOverdueNotification extends Notification
{
    use CzechText, Queueable;

    public function __construct(
        private readonly Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = $this->payment->due_date->diffInDays(now());
        $property = $this->payment->lease->property;

        return (new MailMessage)
            ->subject('Payment overdue — '.$property->address)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('You have an overdue payment.')
            ->line('Property: '.$property->address)
            ->line('Amount: '.Money::format(Money::cents($this->payment->amount)))
            ->line('Due date: '.$this->payment->due_date->format('d.m.Y'))
            ->line('Days overdue: '.$daysOverdue)
            ->action('View in RentFlow', config('app.url'))
            ->line('Please make the payment as soon as possible.');
    }

    /**
     * Zvoneček (fáze 5) čte `title` a `message`, ostatní klíče jsou odkazy na záznam (#47).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $address = $this->payment->lease?->property?->address;
        $amount = Money::format(Money::cents($this->payment->amount));

        return [
            'type' => 'payment_overdue',
            'title' => 'Platba po splatnosti',
            'message' => "Platba {$amount} za {$address} je po splatnosti od {$this->czDate($this->payment->due_date)}.",
            'payment_id' => $this->payment->id,
            'property_address' => $address,
            'amount' => $this->payment->amount,
            'due_date' => $this->payment->due_date->toDateString(),
            'days_overdue' => (int) $this->payment->due_date->diffInDays(today()),
        ];
    }
}
