<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class PostgresHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        // CONTAINS is fully supported across all databases natively by Laravel
        if ($operator === Operators::CONTAINS) {
            $query->whereJsonContains($field, $value);

            return;
        }

        // The remaining operators require raw PostgreSQL syntax.
        // Safety Check: Prevent MySQL/SQLite from crashing.
        $connection = $query->getConnection();
        if (! method_exists($connection, 'getDriverName') || $connection->getDriverName() !== 'pgsql') {
            throw new \InvalidArgumentException("The '{$operator->value}' operator is only supported on PostgreSQL databases.");
        }

        // @codeCoverageIgnoreStart
        $grammar = $query instanceof EloquentBuilder ? $query->getQuery()->getGrammar() : $query->getGrammar();
        $wrappedField = $grammar->wrap($field);

        match ($operator) {
            Operators::CONTAINEDBY => $query->whereRaw("? <@ {$wrappedField}", [$value]),
            Operators::OVERLAP => $query->whereRaw("? && {$wrappedField}", [$value]),
            default => null,
        };
        // @codeCoverageIgnoreEnd
    }
}
