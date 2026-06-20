<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Maps;

use Victormgomes\QueryParams\Enums\AbstractType;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Enums\RuleType;

class TypesMap
{
    public static function abstract(): array
    {
        $typeMappings = [
            'integer' => [AbstractType::INTEGER, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int', 'int4', 'int8']],
            'numeric' => [AbstractType::NUMERIC, ['decimal', 'float', 'double', 'numeric', 'real']],
            'string' => [AbstractType::STRING, ['string', 'text', 'guid', 'char', 'varchar', 'character varying']],
            'boolean' => [AbstractType::BOOLEAN, ['boolean', 'bool']],
            'date' => [AbstractType::DATE, ['date']],
            'datetime' => [AbstractType::DATETIME, ['datetime', 'datetimetz', 'timestamp', 'timestamptz', 'timestamp without time zone', 'timestamp with time zone']],
            'array' => [AbstractType::ARRAY, ['json', 'array', 'simple_array', 'jsonb']],
        ];

        $flattened = [];
        foreach ($typeMappings as $item) {
            [$abstractType, $dbTypes] = $item;
            foreach ($dbTypes as $dbType) {
                $flattened[$dbType] = $abstractType;
            }
        }

        return $flattened;
    }

    public static function operator(): array
    {
        $allTypes = [
            AbstractType::STRING,
            AbstractType::INTEGER,
            AbstractType::NUMERIC,
            AbstractType::BOOLEAN,
            AbstractType::DATE,
            AbstractType::DATETIME,
        ];

        $numericTypes = [
            AbstractType::INTEGER,
            AbstractType::NUMERIC,
            AbstractType::DATE,
            AbstractType::DATETIME,
        ];

        return [
            Operators::EQ->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::NE->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::GT->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::GTE->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::LT->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::LTE->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::SOMETIMES),
            ],
            Operators::IN->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::ARRAY, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::NIN->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::ARRAY, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::NULL->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::BOOLEAN, RuleType::SOMETIMES),
            ],
            Operators::NOTNULL->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::BOOLEAN, RuleType::SOMETIMES),
            ],
            Operators::BETWEEN->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::ARRAY, RuleType::SIZE_2, RuleType::SOMETIMES),
            ],
            Operators::NBETWEEN->value => [
                AssociatedIndex::TYPES->value => $numericTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::ARRAY, RuleType::SIZE_2, RuleType::SOMETIMES),
            ],
            Operators::LIKE->value => [
                AssociatedIndex::TYPES->value => [AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::NOTLIKE->value => [
                AssociatedIndex::TYPES->value => [AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::ILIKE->value => [
                AssociatedIndex::TYPES->value => [AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::NOTILIKE->value => [
                AssociatedIndex::TYPES->value => [AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::CONTAINS->value => [
                AssociatedIndex::TYPES->value => [AbstractType::ARRAY, AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::CONTAINEDBY->value => [
                AssociatedIndex::TYPES->value => [AbstractType::ARRAY, AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::OVERLAP->value => [
                AssociatedIndex::TYPES->value => [AbstractType::ARRAY, AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::FTS->value => [
                AssociatedIndex::TYPES->value => [AbstractType::STRING],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::MIN_1, RuleType::SOMETIMES),
            ],
            Operators::EXISTS->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::BOOLEAN, RuleType::SOMETIMES),
            ],
            Operators::NOTEXISTS->value => [
                AssociatedIndex::TYPES->value => $allTypes,
                AssociatedIndex::RULES->value => RuleType::build(RuleType::BOOLEAN, RuleType::SOMETIMES),
            ],
            Operators::YEAR->value => [
                AssociatedIndex::TYPES->value => [AbstractType::DATE, AbstractType::DATETIME],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::INTEGER, RuleType::SOMETIMES),
            ],
            Operators::MONTH->value => [
                AssociatedIndex::TYPES->value => [AbstractType::DATE, AbstractType::DATETIME],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::INTEGER, RuleType::SOMETIMES, 'min:1', 'max:12'),
            ],
            Operators::DAY->value => [
                AssociatedIndex::TYPES->value => [AbstractType::DATE, AbstractType::DATETIME],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::INTEGER, RuleType::SOMETIMES, 'min:1', 'max:31'),
            ],
            Operators::DATE->value => [
                AssociatedIndex::TYPES->value => [AbstractType::DATE, AbstractType::DATETIME],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::DATE, RuleType::SOMETIMES),
            ],
            Operators::TIME->value => [
                AssociatedIndex::TYPES->value => [AbstractType::DATETIME],
                AssociatedIndex::RULES->value => RuleType::build(RuleType::STRING, RuleType::SOMETIMES),
            ],
        ];
    }
}
