<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

trait Filterable
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $filterableFields
     * @param  list<string>  $sortableFields
     * @param  list<string>  $searchableFields
     * @return Builder<TModel>
     */
    protected function applyFilters(
        Builder $query,
        Request $request,
        array $filterableFields = [],
        array $sortableFields = [],
        array $searchableFields = [],
    ): Builder {
        foreach ($filterableFields as $field) {
            $value = $this->stringInput($request, $field);
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        // rozsah >= začátek dne a < začátek dne po date_to (#27): poslední den se započítá i u sloupců s časem;
        // neplatné datum → 422, pole se ignoruje jako u ostatních parametrů
        $dates = array_filter([
            'date_from' => $this->stringInput($request, 'date_from'),
            'date_to' => $this->stringInput($request, 'date_to'),
        ]);
        Validator::make($dates, ['date_from' => ['date'], 'date_to' => ['date']])->validate();

        if (isset($dates['date_from'])) {
            $query->where($this->getDateField(), '>=', Carbon::parse($dates['date_from'])->startOfDay());
        }
        if (isset($dates['date_to'])) {
            $query->where($this->getDateField(), '<', Carbon::parse($dates['date_to'])->startOfDay()->addDay());
        }

        $search = $this->stringInput($request, 'search');
        if ($search !== null && ! empty($searchableFields)) {
            $query->where(function (Builder $q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', '%'.$search.'%');
                }
            });
        }

        $sortParam = $this->stringInput($request, 'sort');
        if ($sortParam !== null) {
            $direction = 'asc';

            if (str_starts_with($sortParam, '-')) {
                $direction = 'desc';
                $sortParam = substr($sortParam, 1);
            }

            if (in_array($sortParam, $sortableFields)) {
                $query->orderBy($sortParam, $direction);
            }
        } else {
            $query->latest(); // Default: newest first
        }

        return $query;
    }

    /**
     * Hodnota parametru jen jako neprázdný řetězec. Pole (`?sort[]=…`) se ignoruje, jinak by skončilo chybou 500.
     */
    protected function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Override in controller to set the date field for range filters.
     */
    protected function getDateField(): string
    {
        return 'created_at';
    }
}
