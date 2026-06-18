<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations\Types;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;

interface FilterOperation
{
    public function handle(EloquentBuilder|QueryBuilder $query, string $field, Operators $operator, mixed $value): void;
}
