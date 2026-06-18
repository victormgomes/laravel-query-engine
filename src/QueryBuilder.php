<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\ValidationException;
use Victormgomes\QueryParams\Contracts\FieldResolver;
use Victormgomes\QueryParams\Enums\AbstractType;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Support\ClassLoader;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Support\Resource;

class QueryBuilder
{
    use Macroable;

    /**
     * @param  Builder|class-string<Model>  $queryOrModel
     */
    public static function buildQuery(EloquentBuilder|string $queryOrModel, FormRequest|Request $request): EloquentBuilder
    {
        if (is_string($queryOrModel)) {
            $model = ClassLoader::instantiateModel($queryOrModel);
            $query = $model->newQuery();
            $modelFQCN = $queryOrModel;
        } else {
            $query = $queryOrModel;
            $modelFQCN = get_class($query->getModel());
        }

        QueryNormalizer::normalize($request, $modelFQCN);
        $validated = $request instanceof FormRequest ? $request->validated() : $request->all();
        $validated = self::castDataTypes($validated, $modelFQCN);
        $locale = app()->getLocale();
        $driver = QueryNormalizer::resolveDriver();

        if ($filters = $validated[AssociatedIndex::FILTERS->value] ?? null) {
            $filters = (array) $filters;
            if (in_array(SoftDeletes::class, class_uses_recursive($modelFQCN), true)) {
                $withDeleted = filter_var($filters['with_deleted'][Operators::EQ->value] ?? false, FILTER_VALIDATE_BOOLEAN);
                $onlyDeleted = filter_var($filters['only_deleted'][Operators::EQ->value] ?? false, FILTER_VALIDATE_BOOLEAN);

                if ($onlyDeleted) {
                    /** @phpstan-ignore-next-line */
                    $query->onlyTrashed();
                } elseif ($withDeleted) {
                    /** @phpstan-ignore-next-line */
                    $query->withTrashed();
                }

                unset($filters['with_deleted'], $filters['only_deleted']);
            }

            if (! empty($filters)) {
                self::applyFilters($query, $filters, $locale, $driver);
            }
        }

        if ($sorts = $validated[AssociatedIndex::SORTS->value] ?? null) {
            foreach (Arr::dot((array) $sorts) as $field => $dir) {
                $applied = $driver ? $driver->applySort($query, $field, $dir, $locale) : false;
                if (! $applied) {
                    $query->orderBy($field, $dir);
                }
            }
        }

        if ($fields = $validated[AssociatedIndex::FIELDS->value] ?? null) {
            $query->select((array) $fields);
        }

        if ($includes = $validated[AssociatedIndex::INCLUDES->value] ?? null) {
            $with = [];
            foreach ((array) $includes as $key => $value) {
                if (is_string($key) && is_array($value)) {
                    $with[$key] = function ($query) use ($value) {
                        if (! empty($value['fields'])) {
                            $query->select($value['fields']);
                        }
                    };
                } else {
                    $with[] = $value;
                }
            }
            $query->with($with);
        }

        return $query;
    }

    /**
     * @param  Builder|class-string<Model>  $queryOrModel
     */
    public static function paginateQuery(EloquentBuilder|string $queryOrModel, FormRequest|Request $request): LengthAwarePaginator
    {
        self::validateExtraParameters($request);

        $query = self::buildQuery($queryOrModel, $request);

        $validated = $request instanceof FormRequest ? $request->validated() : $request->all();
        $locale = app()->getLocale();
        $driver = QueryNormalizer::resolveDriver();

        $page = (array) ($validated[AssociatedIndex::PAGE->value] ?? []);
        $paginator = $query->paginate(
            (int) ($page[AssociatedIndex::LIMIT->value] ?? 10),
            ['*'],
            AssociatedIndex::PAGE->value,
            (int) ($page[AssociatedIndex::NUMBER->value] ?? 1)
        );

        if ($driver) {
            $paginator->through(fn ($item) => $driver->translateItem($item, $locale));
        }

        return $paginator;
    }

    /**
     * @param  Builder|class-string<Model>  $queryOrModel
     */
    public static function cursorPaginateQuery(EloquentBuilder|string $queryOrModel, FormRequest|Request $request): CursorPaginator
    {
        self::validateExtraParameters($request);

        $query = self::buildQuery($queryOrModel, $request);

        $validated = $request instanceof FormRequest ? $request->validated() : $request->all();
        $locale = app()->getLocale();
        $driver = QueryNormalizer::resolveDriver();

        $page = (array) ($validated[AssociatedIndex::PAGE->value] ?? []);

        $cursorValue = $page['cursor'] ?? null;
        $cursor = null;
        if (is_string($cursorValue)) {
            $cursor = Cursor::fromEncoded($cursorValue);
        }

        $cursorPaginator = $query->cursorPaginate(
            (int) ($page[AssociatedIndex::LIMIT->value] ?? 10),
            ['*'],
            'page[cursor]',
            $cursor
        );

        if ($driver) {
            $cursorPaginator->through(fn ($item) => $driver->translateItem($item, $locale));
        }

        return $cursorPaginator;
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

    private static function validateExtraParameters(FormRequest|Request $request): void
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
    }

    private static function castDataTypes(array $data, string $modelFQCN): array
    {
        $resources = Resource::generate($modelFQCN);
        $filters = $data[AssociatedIndex::FILTERS->value] ?? [];

        foreach ($filters as $field => $ops) {
            $type = $resources['filters'][$field]['type'] ?? 'string';
            $type = $type instanceof AbstractType ? $type->value : $type;

            foreach ($ops as $op => $val) {
                $data[AssociatedIndex::FILTERS->value][$field][$op] = self::castValue($val, $type);
            }
        }

        return $data;
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

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
}
