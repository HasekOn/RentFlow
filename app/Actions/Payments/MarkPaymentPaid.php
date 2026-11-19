<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Events\PaymentMarkedPaid;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Označení platby jako zaplacené (#36). Už zaplacená → 422: přepsání data zaplacení na dnešek by zhoršilo
 * trust score a event by odešel podruhé. Přepočet trust score dělá listener po commitu. Autorizaci dělá volající.
 */
final class MarkPaymentPaid
{
    public function handle(Payment $payment, CarbonInterface $paidDate): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => __('The payment is already paid.'),
            ]);
        }

        return DB::transaction(function () use ($payment, $paidDate): Payment {
            $payment->update(['paid_date' => $paidDate, 'status' => PaymentStatus::Paid]);
            PaymentMarkedPaid::dispatch($payment);

            return $payment;
        });
    }
}
