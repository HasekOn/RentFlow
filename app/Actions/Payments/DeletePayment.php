<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smazání nezaplacené platby a přepočet trust score (nezaplacená platba ho snižuje). Zaplacená je doklad
 * o přijatých penězích, smazat ji nejde. Autorizaci dělá volající.
 */
final class DeletePayment
{
    public function handle(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'status' => __('Cannot delete a paid payment. Only unpaid or overdue payments can be deleted.'),
            ]);
        }

        DB::transaction(function () use ($payment): void {
            $payment->delete();
            $payment->loadMissing('lease.tenant');
            $payment->lease?->tenant?->recalculateTrustScore();
        });
    }
}
