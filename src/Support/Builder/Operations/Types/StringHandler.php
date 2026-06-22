<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\LaravelQueryEngine\Enums\Operators;

class StringHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        $connection = $query->getConnection();
        $isPgsql = method_exists($connection, 'getDriverName') && $connection->getDriverName() === 'pgsql';

        match ($operator) {
            Operators::LIKE => $query->where($field, 'like', "%{$value}%"),
            Operators::NOTLIKE => $query->where($field, 'not like', "%{$value}%"),
            Operators::ILIKE => $isPgsql ? $query->where($field, 'ilike', "%{$value}%") : $query->where($field, 'like', "%{$value}%"),
            Operators::NOTILIKE => $isPgsql ? $query->where($field, 'not ilike', "%{$value}%") : $query->where($field, 'not like', "%{$value}%"),
            Operators::FTS => $query->whereFullText($field, (string) $value),
            default => null,
        };
    }
}
