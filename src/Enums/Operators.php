<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Enums;

enum Operators: string
{
    case EQ = 'eq';
    case NE = 'ne';
    case GT = 'gt';
    case GTE = 'gte';
    case LT = 'lt';
    case LTE = 'lte';
    case IN = 'in';
    case NIN = 'nin';
    case NULL = 'null';
    case NOTNULL = 'notnull';
    case BETWEEN = 'between';
    case NBETWEEN = 'nbetween';
    case LIKE = 'like';
    case NOTLIKE = 'notlike';
    case ILIKE = 'ilike';
    case NOTILIKE = 'notilike';
    case OR = 'or';
    case AND = 'and';
    case NOT = 'not';
    case EXISTS = 'exists';
    case NOTEXISTS = 'notexists';
    case CONTAINS = 'contains';
    case CONTAINEDBY = 'containedby';
    case OVERLAP = 'overlap';
    case FTS = 'fts';
    case YEAR = 'year';
    case MONTH = 'month';
    case DAY = 'day';
    case DATE = 'date';
    case TIME = 'time';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
