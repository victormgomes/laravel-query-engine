<?php
declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class ArrayHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        match ($operator) {
            Operators::IN => $query->whereIn($field, (array) $value),
            Operators::NIN => $query->whereNotIn($field, (array) $value),
            Operators::BETWEEN => (is_array($value) && count($value) === 2) ? $query->whereBetween($field, $value) : null,
            Operators::NBETWEEN => (is_array($value) && count($value) === 2) ? $query->whereNotBetween($field, $value) : null,
            default => null,
        };
    }
}
