<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentOverdueNotification;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Scheduler (denně): nezaplacené platby 5 a víc dní po splatnosti → `overdue`, upozornění nájemníkovi a přepočet
 * trust score jednou na nájemníka (#34, #48). Platby smazaných smluv nebo nemovitostí se přeskočí a chyba
 * u jednoho upozornění (např. výpadek SMTP) nezastaví zbytek.
 */
final class MarkOverduePayments
{
    public const int GRACE_DAYS = 5;

    /**
     * @return int počet plateb nově po splatnosti
     */
    public function handle(CarbonInterface $today): int
    {
        // splatnost nejpozději před 5 dny: due_date < dnes − 4 dny (půlnoc)
        $payments = Payment::query()
            ->with(['lease.tenant', 'lease.property'])
            ->where('status', PaymentStatus::Unpaid)
            ->where('due_date', '<', $today->copy()->startOfDay()->subDays(self::GRACE_DAYS - 1))
            ->whereHas('lease.property')
            ->get();

        if ($payments->isEmpty()) {
            return 0;
        }

        Payment::query()->whereKey($payments->modelKeys())->update(['status' => PaymentStatus::Overdue]);

        /** @var array<int, User> $tenants */
        $tenants = [];
        foreach ($payments as $payment) {
            $payment->status = PaymentStatus::Overdue;
            $tenant = $payment->lease?->tenant;
            if ($tenant === null) {
                continue;
            }
            $tenants[$tenant->id] = $tenant;

            try {
                $tenant->notify(new PaymentOverdueNotification($payment));
            } catch (Throwable $e) {
                report($e);
            }
        }

        foreach ($tenants as $tenant) {
            $tenant->recalculateTrustScore();
        }

        return $payments->count();
    }
}
