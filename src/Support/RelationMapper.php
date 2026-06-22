<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

class RelationMapper
{
    /**
     * Discovery map for a model: [fancy_name => [real_name, type, foreign_key]]
     */
    protected static array $cache = [];

    public static function getMap(Model|string $model): array
    {
        $class = is_string($model) ? $model : get_class($model);

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $instance = is_string($model) ? new $model : $model;
        $reflection = new ReflectionClass($instance);
        $map = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // 1. Basic check: Relations never have required parameters
            if ($method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            // 2. Class check: Skip methods declared by the Laravel core or Eloquent internals
            $declaringClass = $method->getDeclaringClass()->getName();
            if (str_starts_with($declaringClass, 'Illuminate\Database\Eloquent') ||
                str_starts_with($declaringClass, 'Illuminate\Support\Traits')) {
                continue;
            }

            // 3. Type Hint check: Use Reflection to avoid execution if possible
            $returnType = $method->getReturnType();

            if ($returnType) {
                $types = [];
                if ($returnType instanceof ReflectionNamedType) {
                    $types[] = $returnType->getName();
                } elseif ($returnType instanceof ReflectionUnionType) {
                    foreach ($returnType->getTypes() as $type) {
                        if ($type instanceof ReflectionNamedType) {
                            $types[] = $type->getName();
                        }
                    }
                }

                $isRelation = false;
                $isExplicitlyNotRelation = false;

                foreach ($types as $typeName) {
                    if (is_a($typeName, Relation::class, true)) {
                        $isRelation = true;
                        break;
                    }

                    // If it returns a primitive or non-Relation class, it's likely not a relation
                    if (in_array($typeName, ['bool', 'void', 'int', 'string', 'array', 'object', 'float', 'mixed', 'self', 'static', 'parent'])) {
                        $isExplicitlyNotRelation = true;
                    }
                }

                // If it's a known non-relation type, skip it entirely without executing
                if ($isExplicitlyNotRelation && ! $isRelation) {
                    continue;
                }
            }

            // 4. Blacklist: Skip common non-relation methods that might not have type hints in older versions
            if (in_array($method->getName(), [
                'jsonSerialize', 'toArray', 'replicate', 'push', 'save',
                'delete', 'forceDelete', 'restore', 'touch', 'refresh',
                'getAttributes', 'getOriginal', 'getDirty', 'getChanges',
                'wasChanged', 'isDirty', 'isClean', 'getRelations',
            ])) {
                continue;
            }

            try {
                // 5. Execution: Now it is safe to call the method to confirm if it returns a Relation
                // We use @ to suppress potential errors from calling methods that might depend on state
                $return = @$instance->{$method->getName()}();

                if ($return instanceof Relation) {
                    $realName = $method->getName();
                    $snakeName = Str::snake($realName);

                    $relationData = [
                        'real_name' => $realName,
                        'type' => class_basename($return),
                        'related' => class_basename($return->getRelated()),
                        'foreign_key' => null,
                    ];

                    if ($return instanceof BelongsTo) {
                        $relationData['foreign_key'] = $return->getForeignKeyName();
                    }

                    // Map real name
                    $map[$realName] = $relationData;

                    // Map snake_case alias
                    if ($snakeName !== $realName) {
                        $map[$snakeName] = array_merge($relationData, ['is_alias' => true]);
                    }

                    // Map Foreign Key alias (if it's a BelongsTo)
                    if ($relationData['foreign_key'] && $relationData['foreign_key'] !== $realName && $relationData['foreign_key'] !== $snakeName) {
                        $map[$relationData['foreign_key']] = array_merge($relationData, ['is_alias' => true, 'is_fk' => true]);
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return self::$cache[$class] = $map;
    }

    public static function resolveRelation(Model|string $model, string $name): ?string
    {
        $map = self::getMap($model);

        return $map[$name]['real_name'] ?? null;
    }

    public static function resolveFilterField(Model|string $model, string $name): string
    {
        $map = self::getMap($model);

        // If the name is a relation alias that maps to a Foreign Key, use the FK directly for performance
        if (isset($map[$name]) && $map[$name]['foreign_key']) {
            return $map[$name]['foreign_key'];
        }

        return $name;
    }
}
