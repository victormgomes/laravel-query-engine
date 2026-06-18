<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Facades;

use Illuminate\Support\Facades\Facade;

class QueryBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'query-builder';
    }
}
