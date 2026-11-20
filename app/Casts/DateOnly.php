<?php

namespace App\Casts;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sloupec typu date bez času (#27, #28). Laravel cast `date` v SQLite ukládá celý `Y-m-d H:i:s`, takže čas
 * z API nebo seederu se uložil a rozsahy `<= konec` pak vynechaly poslední den. Ukládá se vždy půlnoc ve formátu
 * modelu (v MariaDB je sloupec DATE, tam to nic nemění), takže bindování Carbonu na začátku dne sedí.
 *
 * @implements CastsAttributes<Carbon, mixed>
 */
final class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse((string) $value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value);

        return $date->startOfDay()->format($model->getDateFormat());
    }
}
