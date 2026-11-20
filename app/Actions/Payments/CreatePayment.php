<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ruční platba ke smlouvě. Bez zadaného stavu podle data zaplacení. Přepočet trust score nájemníka (#36).
 * Autorizaci dělá volající.
 */
final class CreatePayment
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (lease_id odpovídá $lease)
     */
    public function handle(Lease $lease, array $data): Payment
    {
        $data['status'] ??= empty($data['paid_date']) ? PaymentStatus::Unpaid : PaymentStatus::Paid;

        // platby jen u přijaté smlouvy (S2): pozvánka ani odmítnutá smlouva nesmí ovlivnit Trust Score
        if (! $lease->isAccepted()) {
            throw ValidationException::withMessages([
                'lease_id' => __('Payments can only be added to a lease the tenant has accepted.'),
            ]);
        }

        return DB::transaction(function () use ($lease, $data): Payment {
            $payment = Payment::query()->create([...$data, 'lease_id' => $lease->id]);
            $lease->tenant?->recalculateTrustScore();

            return $payment;
        });
    }
}
