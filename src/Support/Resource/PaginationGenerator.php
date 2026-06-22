<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support\Resource;

use Victormgomes\LaravelQueryEngine\Enums\AssociatedIndex;

final class PaginationGenerator
{
    public static function generate(): array
    {
        return [
            'keys' => [AssociatedIndex::NUMBER->value, AssociatedIndex::LIMIT->value, 'cursor'],
            'defaults' => [
                'limit' => 10,
                'max_limit' => 100,
            ],
        ];
    }
}
