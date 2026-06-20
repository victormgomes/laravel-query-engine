<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support\Builder\Operations;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\ArrayHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\ComparisonHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\DateHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\FilterOperation;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\LogicalHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\NullHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\PostgresHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\RelationHandler;
use Victormgomes\QueryParams\Support\Builder\Operations\Types\StringHandler;

class Filter
{
    private static array $handlerMap = [
        Operators::EQ->value => ComparisonHandler::class,
        Operators::NE->value => ComparisonHandler::class,
        Operators::GT->value => ComparisonHandler::class,
        Operators::GTE->value => ComparisonHandler::class,
        Operators::LT->value => ComparisonHandler::class,
        Operators::LTE->value => ComparisonHandler::class,

        Operators::IN->value => ArrayHandler::class,
        Operators::NIN->value => ArrayHandler::class,
        Operators::BETWEEN->value => ArrayHandler::class,
        Operators::NBETWEEN->value => ArrayHandler::class,

        Operators::NULL->value => NullHandler::class,
        Operators::NOTNULL->value => NullHandler::class,

        Operators::LIKE->value => StringHandler::class,
        Operators::NOTLIKE->value => StringHandler::class,
        Operators::ILIKE->value => StringHandler::class,
        Operators::NOTILIKE->value => StringHandler::class,

        Operators::CONTAINS->value => PostgresHandler::class,
        Operators::CONTAINEDBY->value => PostgresHandler::class,
        Operators::OVERLAP->value => PostgresHandler::class,
        Operators::FTS->value => StringHandler::class,

        Operators::OR->value => LogicalHandler::class,
        Operators::AND->value => LogicalHandler::class,
        Operators::NOT->value => LogicalHandler::class,

        Operators::EXISTS->value => RelationHandler::class,
        Operators::NOTEXISTS->value => RelationHandler::class,

        Operators::YEAR->value => DateHandler::class,
        Operators::MONTH->value => DateHandler::class,
        Operators::DAY->value => DateHandler::class,
        Operators::DATE->value => DateHandler::class,
        Operators::TIME->value => DateHandler::class,
    ];

    private static array $handlerInstances = [];

    public static function build(EloquentBuilder|QueryBuilder $query, string $field, string $operator, $value): void
    {
        $op = Operators::tryFrom($operator);

        if ($op === null) {
            return;
        }

        $handlerClass = self::$handlerMap[$op->value] ?? null;

        if ($handlerClass) {
            self::getHandler($handlerClass)->handle($query, $field, $op, $value);
        }
    }

    private static function getHandler(string $class): FilterOperation
    {
        if (! isset(self::$handlerInstances[$class])) {
            self::$handlerInstances[$class] = new $class;
        }

        return self::$handlerInstances[$class];
    }
}
