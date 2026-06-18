<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support;

use Illuminate\Database\Eloquent\ModelInfo;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;

class Resource
{
    public static function generate(string $modelFQCN, $connection = null): array
    {
        $inspector = new ModelInspector(app());
        $modelInstance = new $modelFQCN;
        $visible = $modelInstance->getVisible();
        $hidden = $modelInstance->getHidden();

        // Use configured metadata connection if none provided
        $connection ??= Config::get('query-params.metadata_connection');

        /** @var ModelInfo $modelInfo */
        $modelInfo = $inspector->inspect($modelFQCN, $connection);

        $attributes = [];
        foreach ($modelInfo->attributes as $attribute) {
            $name = $attribute['name'];

            // 1. If $visible is defined, only include fields in $visible
            if (! empty($visible) && ! in_array($name, $visible, true)) {
                continue;
            }

            // 2. Always respect $hidden
            if (in_array($name, $hidden, true) || ($attribute['hidden'] ?? false) === true) {
                continue;
            }

            $attributes[] = $attribute;
        }

        $relationMap = RelationMapper::getMap($modelFQCN);

        $features = Config::get('query-params.features', [
            'filters' => true,
            'sorts' => true,
            'includes' => true,
            'fields' => true,
            'page' => true,
        ]);

        return [
            'filters' => ($features['filters'] ?? true) ? self::generateFilters($attributes, $relationMap, $modelFQCN) : [],
            'sorts' => ($features['sorts'] ?? true) ? self::generateSorts($attributes, $relationMap) : [],
            'pagination' => ($features['page'] ?? true) ? self::generatePagination() : [],
            'fields' => ($features['fields'] ?? true) ? self::generateFields($attributes) : [],
            'includes' => ($features['includes'] ?? true) ? self::generateIncludes($relationMap) : [],
        ];
    }

    /**
     * Returns a rich metadata structure specifically designed for frontend dynamic filter builders.
     * Fully covers all 5 operations: Filters, Sorts, Fields, Includes, and Pagination.
     */
    public static function getQueryGuide(string $modelFQCN): array
    {
        $resource = self::generate($modelFQCN);

        return [
            'model' => class_basename($modelFQCN),
            'available_filters' => $resource['filters'],
            'available_sorts' => $resource['sorts'],
            'available_fields' => array_keys($resource['fields']),
            'available_includes' => $resource['includes'],
            'pagination_settings' => $resource['pagination'],
            'syntax' => [
                'filters' => '{"field":{"operator":"value"}}',
                'sorts' => '{"field":"direction"}',
                'fields' => '["field1","field2"]',
                'includes' => '["relation1","relation2"]',
                'page' => '{"number":1,"limit":10}',
            ],
        ];
    }

    /**
     * Returns a definitive, cleaned-up metadata structure for frontend services.
     * Hides redundant aliases and internal mapping details.
     */
    public static function getFilterSchema(string $modelFQCN): array
    {
        $resource = self::generate($modelFQCN);

        // 1. Clean up Filters: Prioritize unique names, prefer snake_case for relations
        $filters = [];
        foreach ($resource['filters'] as $name => $data) {
            if ($data['type'] === 'relation_id') {
                // Skip if it is a camelCase method (we prefer the snake_case alias)
                if (isset($data['is_alias']) && $data['is_alias'] === false && Str::snake($name) !== $name) {
                    continue;
                }

                // If it is an alias, check if it's the FK name.
                // We keep the "fancy" name (like 'people') and skip the FK name (like 'people_id')
                // unless the fancy name itself doesn't exist.
                if (isset($data['is_alias']) && $data['is_alias'] === true) {
                    $fancyName = Str::snake($data['maps_to']);
                    if ($name !== $fancyName && isset($resource['filters'][$fancyName])) {
                        continue;
                    }
                }
            }

            $filters[$name] = [
                'type' => $data['type'],
                'operations' => $data['operations'],
            ];
        }

        // 2. Clean up Includes: Unique snake_case names only
        $includes = [];
        foreach ($resource['includes'] as $name => $data) {
            // Keep only the snake_case version of the relation
            if (Str::snake($name) !== $name) {
                continue;
            }

            // If this name maps to the same relation as another name already in the list, skip it if it's "less fancy"
            // For example, if we have 'people' and 'people_id' mapping to 'people', we keep 'people'.
            $fancyName = Str::snake($data['maps_to']);
            if ($name !== $fancyName && isset($resource['includes'][$fancyName])) {
                continue;
            }

            $includes[$name] = [
                'related' => $data['related'],
                'type' => $data['type'],
            ];
        }

        return [
            'model' => class_basename($modelFQCN),
            'filters' => $filters,
            'sorts' => array_keys($filters),
            'fields' => array_keys($resource['fields']),
            'includes' => $includes,
            'pagination' => $resource['pagination'],
        ];
    }

    private static function generateFilters(array|Collection $attributes, array $relationMap = [], ?string $modelFQCN = null): array
    {
        $allowedOperators = Config::get('query-params.allowed_operators', Operators::values());
        $operators = array_intersect(Operators::values(), $allowedOperators);
        $operatorTypes = Types::getOperatorTypes();

        $filters = [];

        foreach ($attributes as $attribute) {
            $columnType = Types::resolveType($attribute['type'] ?? 'string');
            $allowedOps = [];

            foreach ($operators as $operator) {
                $allowedTypes = $operatorTypes[$operator] ?? [];
                if (in_array($columnType, $allowedTypes, true)) {
                    $allowedOps[] = $operator;
                }
            }

            if (! empty($allowedOps)) {
                $filters[$attribute['name']] = [
                    'type' => $columnType,
                    'operations' => $allowedOps,
                ];
            }
        }

        // Add relations that can be filtered via Foreign Key
        foreach ($relationMap as $name => $data) {
            if (isset($data['foreign_key']) && ! isset($filters[$name])) {
                $relationOps = array_intersect(
                    [Operators::EQ->value, Operators::NE->value, Operators::IN->value, Operators::NIN->value],
                    $allowedOperators
                );

                if (! empty($relationOps)) {
                    $filters[$name] = [
                        'type' => 'relation_id',
                        'operations' => $relationOps,
                        'is_alias' => $data['is_alias'] ?? false,
                        'maps_to' => $data['foreign_key'],
                    ];
                }
            }
        }

        // Add relationship existence checks (exists / notexists) for all relationships
        foreach ($relationMap as $name => $data) {
            $relationOps = array_intersect([Operators::EXISTS->value, Operators::NOTEXISTS->value], $allowedOperators);
            if (! empty($relationOps)) {
                if (isset($filters[$name])) {
                    $filters[$name]['operations'] = array_values(array_unique(array_merge(
                        $filters[$name]['operations'],
                        $relationOps
                    )));
                } else {
                    $filters[$name] = [
                        'type' => 'relation',
                        'operations' => array_values($relationOps),
                        'is_alias' => $data['is_alias'] ?? false,
                        'maps_to' => $data['real_name'],
                    ];
                }
            }
        }

        // Add Soft Deletes options (with_deleted, only_deleted) if the model uses SoftDeletes
        if ($modelFQCN && in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelFQCN), true)) {
            $booleanOps = array_intersect([Operators::EQ->value], $allowedOperators);
            if (! empty($booleanOps)) {
                $filters['with_deleted'] = [
                    'type' => 'boolean',
                    'operations' => array_values($booleanOps),
                ];
                $filters['only_deleted'] = [
                    'type' => 'boolean',
                    'operations' => array_values($booleanOps),
                ];
            }
        }

        return $filters;
    }

    private static function generateSorts(array|Collection $attributes, array $relationMap = []): array
    {
        $sorts = [];
        foreach ($attributes as $attribute) {
            $sorts[$attribute['name']] = [
                'operations' => ['asc', 'desc'],
            ];
        }

        // Add relations that can be sorted via Foreign Key
        foreach ($relationMap as $name => $data) {
            if (isset($data['foreign_key']) && ! isset($sorts[$name])) {
                $sorts[$name] = [
                    'operations' => ['asc', 'desc'],
                    'is_alias' => $data['is_alias'] ?? false,
                    'maps_to' => $data['foreign_key'],
                ];
            }
        }

        return $sorts;
    }

    private static function generatePagination(): array
    {
        return [
            'keys' => [AssociatedIndex::NUMBER->value, AssociatedIndex::LIMIT->value, 'cursor'],
            'defaults' => [
                'limit' => 10,
                'max_limit' => 100,
            ],
        ];
    }

    private static function generateFields(array|Collection $attributes): array
    {
        $fields = [];
        foreach ($attributes as $attribute) {
            $fields[$attribute['name']] = [
                'operations' => ['add'],
            ];
        }

        return $fields;
    }

    private static function generateIncludes(array $relationMap): array
    {
        $includes = [];
        foreach ($relationMap as $name => $data) {
            $includes[$name] = [
                'type' => $data['type'] ?? 'Relation',
                'related' => $data['related'] ?? '',
                'is_alias' => $data['is_alias'] ?? false,
                'maps_to' => $data['real_name'],
            ];
        }

        return $includes;
    }
}
