<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Victormgomes\QueryParams\Contracts\FieldResolver;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Support\ClassLoader;
use Victormgomes\QueryParams\Support\RelationMapper;
use Victormgomes\QueryParams\Support\Resource;

class QueryBuilder
{
    /**
     * Normalize fancy URL parameters to standard nested array structure.
     */
    public static function normalize(FormRequest|Request $request, ?string $modelFQCN = null): void
    {
        $data = $request->all();

        // 1. Includes
        $includes = self::parseSentence(
            $data[AssociatedIndex::INCLUDE] ?? $data[AssociatedIndex::INCLUDES] ?? [],
            fn ($val) => trim($val)
        );

        if ($modelFQCN) {
            $includes = array_map(function ($include) use ($modelFQCN) {
                return RelationMapper::resolveRelation($modelFQCN, $include) ?? $include;
            }, $includes);
        }

        $data[AssociatedIndex::INCLUDES] = $includes;
        unset($data[AssociatedIndex::INCLUDE]);

        // 2. Sorts
        $sorts = self::parseKeyValueSentence(
            $data[AssociatedIndex::SORT] ?? $data[AssociatedIndex::SORTS] ?? [],
            'asc'
        );

        if ($modelFQCN) {
            $mappedSorts = [];
            foreach ($sorts as $field => $dir) {
                $resolvedField = RelationMapper::resolveFilterField($modelFQCN, $field);
                $mappedSorts[$resolvedField] = $dir;
            }
            $sorts = $mappedSorts;
        }

        $data[AssociatedIndex::SORTS] = $sorts;
        unset($data[AssociatedIndex::SORT]);

        // 3. Fields
        $data[AssociatedIndex::FIELDS] = self::parseSentence(
            $data[AssociatedIndex::FIELD] ?? $data[AssociatedIndex::FIELDS] ?? [],
            fn ($val) => trim($val)
        );
        unset($data[AssociatedIndex::FIELD]);

        // 4. Filters
        $filters = self::parseFilterSentence(
            $data[AssociatedIndex::FILTER] ?? $data[AssociatedIndex::FILTERS] ?? []
        );

        if ($modelFQCN) {
            $mappedFilters = [];
            foreach ($filters as $field => $ops) {
                $resolvedField = RelationMapper::resolveFilterField($modelFQCN, $field);
                $mappedFilters[$resolvedField] = $ops;
            }
            $filters = $mappedFilters;
        }

        // Ensure all filters have an operator
        foreach ($filters as $field => $value) {
            if (! is_array($value)) {
                $filters[$field] = [Operators::EQ => $value];
            }
        }
        $data[AssociatedIndex::FILTERS] = $filters;
        unset($data[AssociatedIndex::FILTER]);

        // 5. Pagination
        $pageData = $data[AssociatedIndex::PAGE] ?? [];
        if (is_string($pageData)) {
            $data[AssociatedIndex::PAGE] = self::parseKeyValueSentence($pageData);
        }

        if (isset($data[AssociatedIndex::LIMIT])) {
            $data[AssociatedIndex::PAGE] = (array) ($data[AssociatedIndex::PAGE] ?? []);
            $data[AssociatedIndex::PAGE][AssociatedIndex::LIMIT] = $data[AssociatedIndex::LIMIT];
            unset($data[AssociatedIndex::LIMIT]);
        }

        // 6. Type Casting Intelligence (If model is known)
        if ($modelFQCN) {
            $data = self::castDataTypes($data, $modelFQCN);
        }

        $request->replace($data);
    }

    private static function castDataTypes(array $data, string $modelFQCN): array
    {
        $resources = Resource::generate($modelFQCN);
        $filters = $data[AssociatedIndex::FILTERS] ?? [];

        foreach ($filters as $field => $ops) {
            $type = $resources['filters'][$field]['type'] ?? 'string';

            foreach ($ops as $op => $val) {
                $data[AssociatedIndex::FILTERS][$field][$op] = self::castValue($val, $type);
            }
        }

        return $data;
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => self::castValue($v, $type), $value);
        }

        return match ($type) {
            'integer' => (int) $value,
            'numeric', 'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date', 'datetime' => Carbon::parse((string) $value),
            default => $value,
        };
    }

    public static function build(string $modelFQCN, FormRequest|Request $request): LengthAwarePaginator
    {
        $allKeys = array_keys(Arr::dot($request->all()));
        $ruleKeys = array_keys(($request instanceof FormRequest && method_exists($request, 'rules')) ? $request->rules() : []);

        if (! empty($ruleKeys)) {
            $normalizedInputKeys = array_map(fn ($key) => preg_replace('/\.\d+$/', '.*', $key), $allKeys);
            $extra_parameters = array_diff($normalizedInputKeys, $ruleKeys);

            if (! empty($extra_parameters)) {
                $actualExtras = array_values(array_intersect_key($allKeys, array_intersect($normalizedInputKeys, $extra_parameters)));
                throw ValidationException::withMessages([
                    'extra_fields' => 'Unexpected parameter(s) key(s): '.implode(', ', $actualExtras),
                ]);
            }
        }

        $validated = $request instanceof FormRequest ? $request->validated() : $request->all();
        $model = ClassLoader::instanceModel($modelFQCN);
        $query = $model->newQuery();
        $locale = app()->getLocale();

        /** @var class-string<FieldResolver>|null $driverClass */
        $driverClass = Config::get('query-params.drivers.translatable');
        $driver = $driverClass ? new $driverClass : null;

        // Apply Filters
        if ($filters = $validated[AssociatedIndex::FILTERS] ?? null) {
            self::applyFilters($query, (array) $filters, $locale, $driver);
        }

        // Apply Sorts
        if ($sorts = $validated[AssociatedIndex::SORTS] ?? null) {
            foreach (self::flattenToDotNotation((array) $sorts) as $field => $dir) {
                $applied = $driver ? $driver->applySort($query, $field, $dir, $locale) : false;
                if (! $applied) {
                    $query->orderBy($field, $dir);
                }
            }
        }

        // Apply Fields
        if ($fields = $validated[AssociatedIndex::FIELDS] ?? null) {
            $query->select((array) $fields);
        }

        // Apply Includes
        if ($includes = $validated[AssociatedIndex::INCLUDES] ?? null) {
            $query->with((array) $includes);
        }

        $page = (array) ($validated[AssociatedIndex::PAGE] ?? []);
        $paginator = $query->paginate(
            (int) ($page[AssociatedIndex::LIMIT] ?? 10),
            ['*'],
            AssociatedIndex::PAGE,
            (int) ($page[AssociatedIndex::NUMBER] ?? 1)
        );

        if ($driver) {
            $paginator->through(fn ($item) => $driver->translateItem($item, $locale));
        }

        return $paginator;
    }

    private static function parseSentence(mixed $input, callable $callback): array
    {
        if (is_string($input)) {
            return array_map($callback, explode(',', $input));
        }

        return (array) $input;
    }

    private static function parseKeyValueSentence(mixed $input, ?string $defaultVal = null): array
    {
        if (! is_string($input)) {
            return (array) $input;
        }

        $result = [];
        foreach (explode(',', $input) as $pair) {
            $parts = explode(':', $pair);
            $key = trim($parts[0]);
            $result[$key] = isset($parts[1]) ? trim($parts[1]) : $defaultVal;
        }

        return $result;
    }

    private static function parseFilterSentence(mixed $input): array
    {
        if (! is_string($input)) {
            return (array) $input;
        }

        $result = [];
        foreach (explode(',', $input) as $pair) {
            $parts = explode(':', $pair);
            $field = trim($parts[0]);
            if (count($parts) === 3) {
                $result[$field][trim($parts[1])] = trim($parts[2]);
            } elseif (count($parts) === 2) {
                $result[$field][Operators::EQ] = trim($parts[1]);
            }
        }

        return $result;
    }

    private static function applyFilters($query, array $filters, string $locale, ?FieldResolver $driver, string $prefix = ''): void
    {
        foreach ($filters as $key => $value) {
            if (Operators::tryFrom((string) $key)) {
                $applied = $driver ? $driver->applyFilter($query, $prefix, (string) $key, $value, $locale) : false;
                if (! $applied) {
                    Filter::build($query, $prefix, (string) $key, $value);
                }

                continue;
            }
            if (is_array($value)) {
                self::applyFilters($query, $value, $locale, $driver, $prefix === '' ? (string) $key : $prefix.'.'.$key);
            }
        }
    }

    private static function flattenToDotNotation(array $array, string $prefix = ''): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $result = array_merge($result, self::flattenToDotNotation($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }
}
