<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Events\PaymentMarkedPaid;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Úprava platby (#36). Změna na zaplacenou vyvolá PaymentMarkedPaid jako mark-paid (bez data zaplacení se doplní
 * dnešek), jiná změna, která ovlivní trust score, ho přepočítá rovnou. U zaplacené platby jde měnit jen poznámka
 * (S8): zpětná změna částky, data nebo stavu by přepsala historii a Trust Score. Autorizaci dělá volající.
 */
final class UpdatePayment
{
    /** Sloupce, ze kterých se počítá trust score. */
    private const array SCORED = ['status', 'type', 'due_date', 'paid_date'];

    /**
     * @param  array<string, mixed>  $data  validovaná pole
     */
    public function handle(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $wasPaid = $payment->status === PaymentStatus::Paid;
            $payment->fill($data);

            if ($wasPaid && array_diff(array_keys($payment->getDirty()), ['note']) !== []) {
                throw ValidationException::withMessages([
                    'status' => __('Only the note of a paid payment can be changed.'),
                ]);
            }

            $markedPaid = ! $wasPaid && $payment->status === PaymentStatus::Paid;
            if ($markedPaid && $payment->paid_date === null) {
                $payment->paid_date = today();
            }
            $scoreChanged = $payment->isDirty(self::SCORED);
            $payment->save();

            if ($markedPaid) {
                PaymentMarkedPaid::dispatch($payment);
            } elseif ($scoreChanged) {
                $payment->loadMissing('lease.tenant');
                $payment->lease?->tenant?->recalculateTrustScore();
            }

            return $payment;
        });
    }
}
