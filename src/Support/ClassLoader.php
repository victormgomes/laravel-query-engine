<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ClassLoader
{
    protected static array $instances = [];

    public static function instantiateModel(string $modelFQCN): Model
    {
        if (isset(self::$instances[$modelFQCN])) {
            return self::$instances[$modelFQCN];
        }

        if (! is_subclass_of($modelFQCN, Model::class)) {
            throw new InvalidArgumentException("{$modelFQCN} must be a subclass of ".Model::class);
        }

        return self::$instances[$modelFQCN] = new $modelFQCN;
    }
}
