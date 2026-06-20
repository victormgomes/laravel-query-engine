<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class DateHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        match ($operator) {
            Operators::YEAR => $query->whereYear($field, $value),
            Operators::MONTH => $query->whereMonth($field, $value),
            Operators::DAY => $query->whereDay($field, $value),
            Operators::DATE => $query->whereDate($field, $value),
            Operators::TIME => $query->whereTime($field, '=', $value),
            default => null,
        };
    }
}
