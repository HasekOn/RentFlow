<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Filterable
{
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

        $dateFrom = $this->stringInput($request, 'date_from');
        if ($dateFrom !== null) {
            $query->where($this->getDateField(), '>=', $dateFrom);
        }

        $dateTo = $this->stringInput($request, 'date_to');
        if ($dateTo !== null) {
            $query->where($this->getDateField(), '<=', $dateTo);
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
