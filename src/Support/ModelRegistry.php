<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support;

use ReflectionClass;
use Victormgomes\LaravelQueryEngine\Attributes\MapQueryEngine;

class ModelRegistry
{
    /** @var array<string, string> */
    private static array $requestModelRegistry = [];

    public static function register(string $requestClass, string $modelFQCN): void
    {
        self::$requestModelRegistry[$requestClass] = $modelFQCN;
    }

    public static function resolveRequest(string $requestClass): ?string
    {
        return self::$requestModelRegistry[$requestClass] ?? null;
    }

    public static function resolveFrom(object $request): ?string
    {
        $fqcn = self::resolveRequest($request::class);
        if ($fqcn !== null) {
            return $fqcn;
        }

        $foundFqcn = null;
        $reflection = new ReflectionClass($request);
        $attributes = $reflection->getAttributes(MapQueryEngine::class);

        if (! empty($attributes)) {
            /** @var MapQueryEngine $attribute */
            $attribute = $attributes[0]->newInstance();
            if ($attribute->model !== null) {
                $foundFqcn = $attribute->model;
            }
        }

        if ($foundFqcn === null && method_exists($request, 'model')) {
            $foundFqcn = $request->model();
        }

        if ($foundFqcn !== null) {
            self::register($request::class, $foundFqcn);
        }

        return $foundFqcn;
    }
}
