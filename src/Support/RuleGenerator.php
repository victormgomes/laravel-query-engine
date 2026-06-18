<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support;

use Illuminate\Validation\Rule;
use Victormgomes\QueryParams\Enums\AbstractType;
use Victormgomes\QueryParams\Enums\RuleType;

class RuleGenerator
{
    public static function generate(array $resources): array
    {
        $filtersRules = self::generateFilters($resources);
        $sortsRules = self::generateSorts($resources);
        $fieldsRules = self::generateFields($resources);
        $includesRules = self::generateIncludes($resources);
        $pagesRules = self::generatePages($resources);

        return array_merge(
            $filtersRules,
            $sortsRules,
            $fieldsRules,
            $includesRules,
            $pagesRules
        );
    }

    private static function generateFilters(array $resources): array
    {
        $rules = [];
        $allowedFields = array_keys($resources['filters']);

        $rules['filters'] = ['sometimes', 'array'.(! empty($allowedFields) ? ':'.implode(',', $allowedFields) : '')];

        if (empty($allowedFields)) {
            return $rules;
        }

        $operatorRules = Types::getOperatorRules();

        foreach ($resources['filters'] as $field => $config) {
            $allowedOps = $config['operations'];
            $rules['filters.'.$field] = ['sometimes', 'array:'.implode(',', $allowedOps)];

            $dbType = $config['type'] ?? AbstractType::STRING;
            $dbTypeValue = $dbType instanceof AbstractType ? $dbType->value : (string) $dbType;

            foreach ($allowedOps as $operator) {
                $baseRule = $operatorRules[$operator];
                $rules['filters.'.$field.'.'.$operator] = RuleType::build($dbTypeValue, $baseRule);
            }
        }

        return $rules;
    }

    private static function generateSorts(array $resources): array
    {
        $rules = [];
        $allowedFields = array_keys($resources['sorts']);

        $rules['sorts'] = ['sometimes', 'array'.(! empty($allowedFields) ? ':'.implode(',', $allowedFields) : '')];

        if (empty($allowedFields)) {
            return $rules;
        }

        foreach ($resources['sorts'] as $field => $config) {
            $rules['sorts.'.$field] = [RuleType::SOMETIMES, Rule::in($config['operations'])];
        }

        return $rules;
    }

    private static function generateFields(array $resources): array
    {
        $rules = [];
        $allowedFields = array_keys($resources['fields']);

        $rules['fields'] = ['sometimes', 'array'];

        if (empty($allowedFields)) {
            return $rules;
        }

        $rules['fields.*'] = ['string', Rule::in($allowedFields)];

        return $rules;
    }

    private static function generateIncludes(array $resources): array
    {
        $rules = [];
        $allowedIncludes = array_keys($resources['includes']);

        $rules['includes'] = ['sometimes', 'array'];

        if (empty($allowedIncludes)) {
            return $rules;
        }

        $rules['includes.*'] = ['string', Rule::in($allowedIncludes)];

        return $rules;
    }

    private static function generatePages(array $resources): array
    {
        $rules = [];
        $allowedPages = $resources['pagination']['keys'] ?? [];

        if (empty($allowedPages)) {
            return $rules;
        }

        $rules['page'] = ['sometimes', 'array:'.implode(',', $allowedPages)];

        foreach ($allowedPages as $page) {
            $rule_value = ['sometimes', 'integer', 'min:1'];
            if ($page === 'limit') {
                $maxLimit = \Illuminate\Support\Facades\Config::get('query-params.pagination.max_limit', 100);
                $rule_value = ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"];
            } elseif ($page === 'cursor') {
                $rule_value = ['sometimes', 'string'];
            }
            $rules['page.'.$page] = $rule_value;
        }

        return $rules;
    }
}
