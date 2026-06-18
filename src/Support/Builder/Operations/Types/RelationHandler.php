<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

class RelationHandler implements FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void
    {
        $isExists = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        match ($operator) {
            Operators::EXISTS => (is_array($value) && isset($value['relation'], $value['callback']))
                ? $query->whereHas($value['relation'], $value['callback'])
                : ($isExists ? $query->has($field) : $query->doesntHave($field)),
            Operators::NOTEXISTS => (is_array($value) && isset($value['relation'], $value['callback']))
                ? $query->whereDoesntHave($value['relation'], $value['callback'])
                : ($isExists ? $query->doesntHave($field) : $query->has($field)),
            default => null,
        };
    }
}
