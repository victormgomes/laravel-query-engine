<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Enums;

enum AbstractType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case NUMERIC = 'numeric';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case ARRAY = 'array';
}
