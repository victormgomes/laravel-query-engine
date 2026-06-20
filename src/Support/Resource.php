<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\ModelInfo;
use Illuminate\Database\Eloquent\ModelInspector;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Victormgomes\QueryParams\Attributes\QueryOptions;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;

class Resource
{
    private static array $cache = [];

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public static function generate(string $modelFQCN, $connection = null): array
    {
        if (isset(self::$cache[$modelFQCN])) {
            return self::$cache[$modelFQCN];
        }

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

        $reflection = new ReflectionClass($modelFQCN);
        $attributesList = $reflection->getAttributes(QueryOptions::class);
        $modelConfig = ! empty($attributesList) ? $attributesList[0]->newInstance() : null;

        $features = Config::get('query-params.features', [
            'filters' => true,
            'sorts' => true,
            'includes' => true,
            'fields' => true,
            'page' => true,
        ]);

        if ($modelConfig) {
            if ($modelConfig->filters !== null) {
                $features['filters'] = $modelConfig->filters;
            }
            if ($modelConfig->sorts !== null) {
                $features['sorts'] = $modelConfig->sorts;
            }
            if ($modelConfig->includes !== null) {
                $features['includes'] = $modelConfig->includes;
            }
            if ($modelConfig->fields !== null) {
                $features['fields'] = $modelConfig->fields;
            }
            if ($modelConfig->page !== null) {
                $features['page'] = $modelConfig->page;
            }
        }

        $allowedFilters = $modelConfig ? $modelConfig->allowedFilters : null;
        $disabledFilters = $modelConfig ? ($modelConfig->disableFilters ?? []) : [];
        $allowedSorts = $modelConfig ? $modelConfig->allowedSorts : null;
        $disabledSorts = $modelConfig ? ($modelConfig->disableSorts ?? []) : [];
        $allowedIncludes = $modelConfig ? $modelConfig->allowedIncludes : null;
        $disabledIncludes = $modelConfig ? ($modelConfig->disableIncludes ?? []) : [];
        $allowedFields = $modelConfig ? $modelConfig->allowedFields : null;
        $disabledFields = $modelConfig ? ($modelConfig->disableFields ?? []) : [];
        $allowedScopes = $modelConfig ? $modelConfig->allowedScopes : [];
        $allowedAggregations = $modelConfig ? $modelConfig->allowedAggregations : [];

        $availableColumns = array_column($attributes, 'name');
        $availableRelations = array_keys($relationMap);
        $allValidFields = array_merge($availableColumns, $availableRelations);

        self::validateConfigFields($modelFQCN, $allowedFilters ?? [], $allValidFields, 'filters (allowed)');
        self::validateConfigFields($modelFQCN, $disabledFilters, $allValidFields, 'filters (disabled)');
        self::validateConfigFields($modelFQCN, $allowedSorts ?? [], $allValidFields, 'sorts (allowed)');
        self::validateConfigFields($modelFQCN, $disabledSorts, $allValidFields, 'sorts (disabled)');
        self::validateConfigFields($modelFQCN, $allowedIncludes ?? [], $availableRelations, 'includes (allowed)');
        self::validateConfigFields($modelFQCN, $disabledIncludes, $availableRelations, 'includes (disabled)');
        self::validateConfigFields($modelFQCN, $allowedFields ?? [], $availableColumns, 'fields (allowed)');
        self::validateConfigFields($modelFQCN, $disabledFields, $availableColumns, 'fields (disabled)');

        return self::$cache[$modelFQCN] = [
            'filters' => ($features['filters'] ?? true) ? self::generateFilters($attributes, $relationMap, $modelFQCN, $allowedFilters, $disabledFilters, $modelConfig?->allowedOperators, $modelConfig?->disableOperators, $allowedScopes) : [],
            'sorts' => ($features['sorts'] ?? true) ? self::generateSorts($attributes, $relationMap, $allowedSorts, $disabledSorts) : [],
            'pagination' => ($features['page'] ?? true) ? self::generatePagination() : [],
            'fields' => ($features['fields'] ?? true) ? self::generateFields($attributes, $relationMap, $allowedFields, $disabledFields, $modelFQCN, $allowedAggregations) : [],
            'includes' => ($features['includes'] ?? true) ? self::generateIncludes($relationMap, $allowedIncludes, $disabledIncludes) : [],
        ];
    }

    private static function validateConfigFields(string $modelFQCN, array $configFields, array $validFields, string $featureName): void
    {
        if (empty($configFields)) {
            return;
        }

        $invalid = array_diff($configFields, $validFields);
        if (! empty($invalid)) {
            throw new \LogicException(sprintf(
                'Configuration error in Model [%s]. You tried to configure %s for fields/relations that do not exist: %s',
                $modelFQCN,
                $featureName,
                implode(', ', $invalid)
            ));
        }
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

    private static function generateFilters(array|Collection $attributes, array $relationMap = [], ?string $modelFQCN = null, ?array $allowedFilters = null, array $disabledFilters = [], ?array $modelAllowedOperators = null, ?array $modelDisableOperators = null, array $allowedScopes = []): array
    {
        $allowedOperators = $modelAllowedOperators ?? Config::get('query-params.allowed_operators', Operators::values());
        if (! empty($modelDisableOperators)) {
            $allowedOperators = array_values(array_diff($allowedOperators, $modelDisableOperators));
        }
        $operators = array_intersect(Operators::values(), $allowedOperators);
        $operatorTypes = Types::getOperatorTypes();

        $filters = [];

        foreach ($attributes as $attribute) {
            if ($allowedFilters !== null && ! in_array($attribute['name'], $allowedFilters, true)) {
                continue;
            }
            if (in_array($attribute['name'], $disabledFilters, true)) {
                continue;
            }

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
            if ($allowedFilters !== null && ! in_array($name, $allowedFilters, true)) {
                continue;
            }
            if (in_array($name, $disabledFilters, true)) {
                continue;
            }

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
            if ($allowedFilters !== null && ! in_array($name, $allowedFilters, true)) {
                continue;
            }
            if (in_array($name, $disabledFilters, true)) {
                continue;
            }

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
        if ($modelFQCN && in_array(SoftDeletes::class, class_uses_recursive($modelFQCN), true)) {
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

        // Add Local Scopes as virtual filters
        if (! empty($allowedScopes) && $modelFQCN) {
            $reflection = new ReflectionClass($modelFQCN);
            foreach ($allowedScopes as $scope) {
                $methodName = 'scope'.ucfirst($scope);
                if ($reflection->hasMethod($methodName)) {
                    $method = $reflection->getMethod($methodName);
                    // Scopes always receive $query as the first argument. If it has > 1, it requires value(s).
                    $hasParams = $method->getNumberOfParameters() > 1;

                    $filters[$scope] = [
                        'type' => $hasParams ? 'string' : 'boolean',
                        'operations' => [Operators::EQ->value],
                        'is_scope' => true,
                    ];
                }
            }
        }

        return $filters;
    }

    private static function generateSorts(array|Collection $attributes, array $relationMap = [], ?array $allowedSorts = null, array $disabledSorts = []): array
    {
        $sorts = [];
        foreach ($attributes as $attribute) {
            if ($allowedSorts !== null && ! in_array($attribute['name'], $allowedSorts, true)) {
                continue;
            }
            if (in_array($attribute['name'], $disabledSorts, true)) {
                continue;
            }

            $sorts[$attribute['name']] = [
                'operations' => ['asc', 'desc'],
            ];
        }

        // Add relations that can be sorted via Foreign Key
        foreach ($relationMap as $name => $data) {
            if ($allowedSorts !== null && ! in_array($name, $allowedSorts, true)) {
                continue;
            }
            if (in_array($name, $disabledSorts, true)) {
                continue;
            }
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

    private static function generateFields(array|Collection $attributes, array $relationMap = [], ?array $allowedFields = null, array $disabledFields = [], ?string $modelFQCN = null, array $allowedAggregations = []): array
    {
        $fields = [];
        foreach ($attributes as $attribute) {
            if ($allowedFields !== null && ! in_array($attribute['name'], $allowedFields, true)) {
                continue;
            }
            if (in_array($attribute['name'], $disabledFields, true)) {
                continue;
            }

            $fields[$attribute['name']] = [
                'operations' => ['add'],
                'is_accessor' => false,
            ];
        }

        if ($modelFQCN) {
            $accessors = self::getAccessors($modelFQCN);
            foreach ($accessors as $accessor) {
                if ($allowedFields !== null && ! in_array($accessor, $allowedFields, true)) {
                    continue;
                }
                if (in_array($accessor, $disabledFields, true)) {
                    continue;
                }

                $fields[$accessor] = [
                    'operations' => ['add'],
                    'is_accessor' => true,
                ];
            }
        }

        // Add Aggregations as virtual fields
        foreach ($allowedAggregations as $agg) {
            if ($allowedFields !== null && ! in_array($agg, $allowedFields, true)) {
                continue;
            }
            if (in_array($agg, $disabledFields, true)) {
                continue;
            }

            if (preg_match('/^(.+)_(count|exists)$/', $agg, $matches)) {
                $relation = $matches[1];
                $func = $matches[2];
                if (isset($relationMap[$relation])) {
                    $fields[$agg] = [
                        'operations' => ['add'],
                        'is_aggregation' => true,
                        'agg_type' => $func,
                        'relation' => $relation,
                    ];
                }
            } elseif (preg_match('/^(.+)_(sum|avg|min|max)_(.+)$/', $agg, $matches)) {
                $relation = $matches[1];
                $func = $matches[2];
                $column = $matches[3];
                if (isset($relationMap[$relation])) {
                    $fields[$agg] = [
                        'operations' => ['add'],
                        'is_aggregation' => true,
                        'agg_type' => $func,
                        'relation' => $relation,
                        'column' => $column,
                    ];
                }
            }
        }

        return $fields;
    }

    private static function getAccessors(string $modelFQCN): array
    {
        $reflection = new ReflectionClass($modelFQCN);
        $accessors = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            $name = $method->getName();

            // Old syntax: getFirstNameAttribute()
            if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute') && $name !== 'getAttribute') {
                $accessors[] = Str::snake(substr($name, 3, -9));

                continue;
            }

            // New syntax PHP 8+: firstName(): Attribute
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === Attribute::class) {
                $accessors[] = Str::snake($name);
            }
        }

        $instance = new $modelFQCN;
        $appends = $instance->getAppends();

        return array_values(array_unique(array_merge($accessors, $appends)));
    }

    private static function generateIncludes(array $relationMap, ?array $allowedIncludes = null, array $disabledIncludes = []): array
    {
        $includes = [];
        foreach ($relationMap as $name => $data) {
            if ($allowedIncludes !== null && ! in_array($name, $allowedIncludes, true)) {
                continue;
            }
            if (in_array($name, $disabledIncludes, true)) {
                continue;
            }

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
