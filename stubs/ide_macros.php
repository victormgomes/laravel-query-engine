<?php

// This file is strictly for IDE autocompletion. It is never executed by PHP.
// It merges these macro methods into Laravel's native classes so that
// developers receive perfect autocompletion without needing to publish any stubs.

namespace Illuminate\Database\Eloquent {
    class Builder {
        /**
         * Automatically paginate the Eloquent query based on URL parameters.
         * 
         * @param \Illuminate\Foundation\Http\FormRequest|\Illuminate\Http\Request|null $request
         * @return \Illuminate\Pagination\LengthAwarePaginator
         */
        public function paginateQuery($request = null) {}

        /**
         * Automatically cursor-paginate the Eloquent query based on URL parameters.
         * 
         * @param \Illuminate\Foundation\Http\FormRequest|\Illuminate\Http\Request|null $request
         * @return \Illuminate\Pagination\CursorPaginator
         */
        public function cursorPaginateQuery($request = null) {}

        /**
         * Apply URL filters to the Eloquent query without paginating.
         * 
         * @param \Illuminate\Foundation\Http\FormRequest|\Illuminate\Http\Request|null $request
         * @return \Illuminate\Database\Eloquent\Builder
         */
        public function buildQuery($request = null) {}
    }
}

namespace Illuminate\Foundation\Http {
    class FormRequest {
        /**
         * Automatically generate validation rules from the attached Model's schema.
         * 
         * @return array<string, mixed>
         */
        public function queryParamRules(): array {}
    }
}
