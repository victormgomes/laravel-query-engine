<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class QueryOptions
{
    /**
     * @param  bool|null  $filters  Disable or enable the filters feature.
     * @param  bool|null  $sorts  Disable or enable the sorts feature.
     * @param  bool|null  $includes  Disable or enable the includes feature.
     * @param  bool|null  $fields  Disable or enable the fields feature.
     * @param  bool|null  $page  Disable or enable the pagination feature.
     * @param  string[]|null  $allowedFilters  Array of specifically allowed fields/relations for filtering.
     * @param  string[]|null  $disableFilters  Array of specific fields/relations to disable filtering for.
     * @param  string[]|null  $allowedSorts  Array of specifically allowed fields/relations for sorting.
     * @param  string[]|null  $disableSorts  Array of specific fields/relations to disable sorting for.
     * @param  string[]|null  $allowedIncludes  Array of specifically allowed relations for including.
     * @param  string[]|null  $disableIncludes  Array of specific relations to disable including for.
     * @param  string[]|null  $allowedFields  Array of specifically allowed fields for selection.
     * @param  string[]|null  $disableFields  Array of specific fields to disable for selection.
     * @param  string[]|null  $allowedOperators  Array of allowed operators (Overrides global config).
     * @param  string[]|null  $disableOperators  Array of operators to disable.
     */
    public function __construct(
        public ?bool $filters = null,
        public ?bool $sorts = null,
        public ?bool $includes = null,
        public ?bool $fields = null,
        public ?bool $page = null,
        public ?array $allowedFilters = null,
        public ?array $disableFilters = null,
        public ?array $allowedSorts = null,
        public ?array $disableSorts = null,
        public ?array $allowedIncludes = null,
        public ?array $disableIncludes = null,
        public ?array $allowedFields = null,
        public ?array $disableFields = null,
        public array $allowedScopes = [],
        public array $allowedAggregations = [],
        public ?array $allowedOperators = null,
        public ?array $disableOperators = null
    ) {}
}
