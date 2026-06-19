<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;

class LogicalHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        match ($operator) {
            Operators::OR => $query->where(function ($q) use ($value): void {
                foreach ($value as $subField => $subOps) {
                    foreach ((array) $subOps as $op => $val) {
                        Filter::build($q, $subField, $op, $val);
                    }
                }
            }),
            Operators::AND => (function () use ($query, $value): void {
                foreach ($value as $subField => $subOps) {
                    foreach ((array) $subOps as $op => $val) {
                        Filter::build($query, $subField, $op, $val);
                    }
                }
            })(),
            Operators::NOT => $query->whereNot(function ($q) use ($value): void {
                foreach ($value as $subField => $subOps) {
                    foreach ((array) $subOps as $op => $val) {
                        Filter::build($q, $subField, $op, $val);
                    }
                }
            }),
            default => null,
        };
    }
}
