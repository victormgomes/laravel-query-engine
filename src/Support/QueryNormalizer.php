<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Victormgomes\QueryParams\Contracts\FieldResolver;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;

class QueryNormalizer
{
    protected static \WeakMap $normalized;

    public static function normalize(FormRequest|Request $request, ?string $modelFQCN = null): void
    {
        self::$normalized ??= new \WeakMap;

        if (isset(self::$normalized[$request])) {
            return;
        }

        $data = self::decodeJsonValues($request->all());

        $includes = (array) ($data[AssociatedIndex::INCLUDES->value] ?? []);
        $parsed = [];

        foreach ($includes as $key => $value) {
            if (is_string($key)) {
                $relation = $modelFQCN
                    ? (RelationMapper::resolveRelation($modelFQCN, $key) ?? $key)
                    : $key;

                if (is_array($value)) {
                    if (array_is_list($value)) {
                        $value = ['fields' => $value];
                    }

                    $parsed[$relation] = [
                        'fields' => (array) ($value['fields'] ?? []),
                    ];
                } else {
                    $parsed[$relation] = ['fields' => []];
                }
            } else {
                $include = trim((string) $value);

                if ($modelFQCN) {
                    $include = RelationMapper::resolveRelation($modelFQCN, $include) ?? $include;
                }

                $parsed[] = $include;
            }
        }

        $data[AssociatedIndex::INCLUDES->value] = $parsed;

        $sorts = (array) ($data[AssociatedIndex::SORTS->value] ?? []);

        if ($modelFQCN) {
            $mappedSorts = [];

            foreach ($sorts as $field => $dir) {
                $mappedSorts[RelationMapper::resolveFilterField($modelFQCN, $field)] = $dir;
            }

            $sorts = $mappedSorts;
        }

        $data[AssociatedIndex::SORTS->value] = $sorts;
        $data[AssociatedIndex::FIELDS->value] = (array) ($data[AssociatedIndex::FIELDS->value] ?? []);

        $filters = (array) ($data[AssociatedIndex::FILTERS->value] ?? []);

        if ($modelFQCN) {
            $mappedFilters = [];

            foreach ($filters as $field => $ops) {
                $mappedFilters[RelationMapper::resolveFilterField($modelFQCN, $field)] = $ops;
            }

            $filters = $mappedFilters;
        }

        $allowedOperators = Config::get('query-params.allowed_operators', Operators::values());

        foreach ($filters as $field => $value) {
            if (! is_array($value)) {
                $value = [Operators::EQ->value => $value];
            }

            $filteredOps = [];
            foreach ($value as $op => $val) {
                if (in_array($op, $allowedOperators, true)) {
                    $filteredOps[$op] = $val;
                }
            }

            if (empty($filteredOps)) {
                unset($filters[$field]);
            } else {
                $filters[$field] = $filteredOps;
            }
        }

        $data[AssociatedIndex::FILTERS->value] = $filters;

        $data[AssociatedIndex::PAGE->value] = (array) ($data[AssociatedIndex::PAGE->value] ?? []);

        $features = Config::get('query-params.features', [
            'filters' => true,
            'sorts' => true,
            'includes' => true,
            'fields' => true,
            'page' => true,
        ]);

        if (! ($features['includes'] ?? true)) {
            unset($data[AssociatedIndex::INCLUDES->value]);
        }
        if (! ($features['sorts'] ?? true)) {
            unset($data[AssociatedIndex::SORTS->value]);
        }
        if (! ($features['fields'] ?? true)) {
            unset($data[AssociatedIndex::FIELDS->value]);
        }
        if (! ($features['filters'] ?? true)) {
            unset($data[AssociatedIndex::FILTERS->value]);
        }
        if (! ($features['page'] ?? true)) {
            unset($data[AssociatedIndex::PAGE->value]);
        }

        $request->query->replace($data);
        $request->replace($data);

        self::$normalized[$request] = true;
    }

    public static function resolveDriver(): ?FieldResolver
    {
        /** @var class-string<FieldResolver>|null $driverClass */
        $driverClass = Config::get('query-params.drivers.default');

        return $driverClass ? new $driverClass : null;
    }

    private static function decodeJsonValues(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && isset($value[0]) && in_array($value[0], ['{', '['], true)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = $decoded;
                }
            }
        }

        return $data;
    }
}
