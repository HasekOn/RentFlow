<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Filterable
{
    protected function applyFilters(
        Builder $query,
        Request $request,
        array   $filterableFields = [],
        array   $sortableFields = [],
        array   $searchableFields = [],
    ): Builder
    {
        foreach ($filterableFields as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('date_from')) {
            $dateField = $this->getDateField();
            $query->where($dateField, '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $dateField = $this->getDateField();
            $query->where($dateField, '<=', $request->input('date_to'));
        }

        if ($request->filled('search') && !empty($searchableFields)) {
            $search = $request->input('search');
            $query->where(function (Builder $q) use ($search, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', '%' . $search . '%');
                }
            });
        }

        if ($request->filled('sort')) {
            $sortParam = $request->input('sort');
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
     * Override in controller to set the date field for range filters.
     */
    protected function getDateField(): string
    {
        return 'created_at';
    }
}
