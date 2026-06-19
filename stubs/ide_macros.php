<?php

// This file is strictly for IDE autocompletion. It is never executed by PHP.
// It merges these macro methods into Laravel's native classes so that
// developers receive perfect autocompletion without needing to publish any stubs.

namespace Illuminate\Database\Eloquent {
    use Illuminate\Foundation\Http\FormRequest;
    use Illuminate\Http\Request;
    use Illuminate\Pagination\CursorPaginator;
    use Illuminate\Pagination\LengthAwarePaginator;

    class Builder
    {
        /**
         * Automatically paginate the Eloquent query based on URL parameters.
         *
         * @param  FormRequest|Request|null  $request
         * @return LengthAwarePaginator
         */
        public function paginateQuery($request = null) {}

        /**
         * Automatically cursor-paginate the Eloquent query based on URL parameters.
         *
         * @param  FormRequest|Request|null  $request
         * @return CursorPaginator
         */
        public function cursorPaginateQuery($request = null) {}

        /**
         * Apply URL filters to the Eloquent query without paginating.
         *
         * @param  FormRequest|Request|null  $request
         * @return Builder
         */
        public function buildQuery($request = null) {}

        /**
         * Retrieve the auto-generated validation rules from the model.
         *
         * @return array<string, mixed>
         */
        public function getQueryRules(): array {}

        /**
         * Retrieve a deduplicated schema representing allowed filters and includes for frontends.
         *
         * @return array<string, mixed>
         */
        public function getFilterSchema(): array {}
    }
}

namespace Illuminate\Foundation\Http {
    class FormRequest
    {
        /**
         * Automatically generate validation rules from the attached Model's schema.
         *
         * @return array<string, mixed>
         */
        public function queryParamRules(): array {}
    }
}
