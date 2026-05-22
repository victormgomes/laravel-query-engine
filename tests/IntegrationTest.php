<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Tests\Models\Post;

it('tests full end-to-end integration with relations and pagination', function () {
    // 1. Prepare Request
    $request = new Request([
        'filter' => [
            'title' => [
                'like' => 'Eloquent',
            ],
            'views' => [
                'gt' => 100,
            ],
        ],
        'sort' => 'published_at:desc,views',
        'include' => 'author',
        'fields' => 'id,title,author_id',
        'page' => [
            'number' => 1,
            'limit' => 25,
        ],
    ]);

    // 2. Run QueryBuilder
    $paginator = QueryBuilder::build(Post::class, $request);

    // 3. Assert Paginator state
    expect($paginator->perPage())->toBe(25);
    expect($paginator->currentPage())->toBe(1);

    // 4. Assert Query Logic (Empirical verification)
    $query = Post::query();
    QueryBuilder::normalize($request);

    // Apply Fields
    $fields = $request->get(AssociatedIndex::FIELDS, []);
    if (! empty($fields)) {
        $query->select($fields);
    }

    // Apply Includes
    $includes = $request->get(AssociatedIndex::INCLUDES, []);
    if (! empty($includes)) {
        $query->with($includes);
    }

    // Apply Filters
    $filters = $request->get(AssociatedIndex::FILTERS, []);
    foreach ($filters as $field => $operators) {
        foreach ($operators as $operator => $value) {
            Filter::build($query, $field, $operator, $value);
        }
    }

    // Apply Sorts
    $sorts = $request->get(AssociatedIndex::SORTS, []);
    foreach ($sorts as $field => $direction) {
        $query->orderBy($field, $direction);
    }

    $sql = $query->toSql();

    // Fields
    expect($sql)->toContain('select "id", "title", "author_id"');

    // Filters
    expect($sql)->toContain('"title" like ?');
    expect($sql)->toContain('"views" > ?');

    // Sorts
    expect($sql)->toContain('order by "published_at" desc, "views" asc');
});
