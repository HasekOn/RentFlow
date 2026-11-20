<?php

namespace App\Actions\Meters;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Nový odečet měřiče. Stav nesmí být nižší než poslední odečet. Autorizaci dělá volající.
 */
final class CreateMeterReading
{
    /**
     * @param  array<string, mixed>  $data  validovaná pole (reading_value, reading_date …)
     */
    public function handle(Meter $meter, User $user, array $data): MeterReading
    {
        $last = $meter->readings()->orderByDesc('reading_date')->orderByDesc('id')->first();

        // stav měřiče (decimal:3, ne peníze): porovnání floatů na tři desetinná místa stačí
        if ($last !== null && (float) $data['reading_value'] < (float) $last->reading_value) {
            throw ValidationException::withMessages([
                'reading_value' => __('Reading value cannot be less than the previous reading (:value).', ['value' => $last->reading_value]),
            ]);
        }

        return MeterReading::query()->create([...$data, 'meter_id' => $meter->id, 'submitted_by' => $user->id]);
    }
}
