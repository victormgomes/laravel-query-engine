<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Support;

use Victormgomes\LaravelQueryEngine\Rules;

class QueryEngineRequestMixin
{
    public function rules(): \Closure
    {
        return function () {
            $fqcn = ModelRegistry::resolveFrom($this);

            return $fqcn !== null ? Rules::generate($fqcn) : [];
        };
    }

    public function queryParamRules(): \Closure
    {
        return function () {
            $fqcn = ModelRegistry::resolveFrom($this);

            return $fqcn !== null ? Rules::generate($fqcn) : [];
        };
    }
}
