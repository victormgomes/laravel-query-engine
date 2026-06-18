<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class NullHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        match ($operator) {
            Operators::NULL => $query->whereNull($field),
            Operators::NOTNULL => $query->whereNotNull($field),
            default => null,
        };
    }
}
