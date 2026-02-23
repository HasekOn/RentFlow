<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = $this->payment->due_date->diffInDays(now());
        $property = $this->payment->lease->property;

        return (new MailMessage)
            ->subject('Payment overdue — ' . $property->address)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have an overdue payment.')
            ->line('Property: ' . $property->address)
            ->line('Amount: ' . number_format($this->payment->amount, 2) . ' CZK')
            ->line('Due date: ' . $this->payment->due_date->format('d.m.Y'))
            ->line('Days overdue: ' . $daysOverdue)
            ->action('View in RentFlow', config('app.url'))
            ->line('Please make the payment as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_overdue',
            'payment_id' => $this->payment->id,
            'property_address' => $this->payment->lease->property->address,
            'amount' => $this->payment->amount,
            'due_date' => $this->payment->due_date->toDateString(),
            'days_overdue' => $this->payment->due_date->diffInDays(now()),
        ];
    }
}
