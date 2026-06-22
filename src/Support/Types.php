<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support;

use Victormgomes\LaravelQueryEngine\Enums\AbstractType;
use Victormgomes\LaravelQueryEngine\Enums\AssociatedIndex;
use Victormgomes\LaravelQueryEngine\Maps\TypesMap;

final class Types
{
    public static function getOperatorTypes(): array
    {
        return array_map(fn ($config) => $config[AssociatedIndex::TYPES->value], TypesMap::operator());
    }

    public static function getOperatorRules(): array
    {
        return array_map(fn ($config) => $config[AssociatedIndex::RULES->value], TypesMap::operator());
    }

    public static function resolveType(string $databaseType): AbstractType
    {
        $map = TypesMap::abstract();
        $databaseType = strtolower($databaseType);

        return $map[$databaseType] ?? AbstractType::STRING;
    }

    public static function getColumnTypes(array $table): array
    {
        $columns = $table[AssociatedIndex::COLUMNS->value];
        $columnsTypes = [];

        foreach ($columns as $column) {
            $databaseType = $column[AssociatedIndex::TYPE->value];
            $abstractType = Types::resolveType($databaseType);
            $columnsTypes[$column[AssociatedIndex::NAME->value]] = $abstractType;
        }

        return $columnsTypes;
    }
}
