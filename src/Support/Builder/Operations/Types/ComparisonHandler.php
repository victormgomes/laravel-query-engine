<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class ComparisonHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        match ($operator) {
            Operators::EQ => $query->where($field, $value),
            Operators::NE => $query->where($field, '!=', $value),
            Operators::GT => $query->where($field, '>', $value),
            Operators::GTE => $query->where($field, '>=', $value),
            Operators::LT => $query->where($field, '<', $value),
            Operators::LTE => $query->where($field, '<=', $value),
            default => null,
        };
    }
}
