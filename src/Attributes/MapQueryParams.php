<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class MapQueryParams
{
    /**
     * @param  string|null  $model  The FQCN of the model to generate rules for.
     */
    public function __construct(
        public ?string $model = null
    ) {}
}
