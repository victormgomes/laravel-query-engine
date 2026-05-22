<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\QueryBuilder;

it('normalizes simple filters', function () {
    $request = new Request([
        'filter' => [
            'name' => 'Victor',
        ],
    ]);

    QueryBuilder::normalize($request);

    $filters = $request->get(AssociatedIndex::FILTERS);

    expect($filters)->toBe([
        'name' => [
            Operators::EQ => 'Victor',
        ],
    ]);
});

it('normalizes filters with operators', function () {
    $request = new Request([
        'filter' => [
            'age' => [
                'gt' => 20,
            ],
        ],
    ]);

    QueryBuilder::normalize($request);

    $filters = $request->get(AssociatedIndex::FILTERS);

    expect($filters)->toBe([
        'age' => [
            'gt' => 20,
        ],
    ]);
});

it('normalizes different sort styles', function ($input, $expected) {
    $request = new Request(['sort' => $input]);
    QueryBuilder::normalize($request);

    expect($request->get(AssociatedIndex::SORTS))->toBe($expected);
})->with([
    'string with colon' => ['name:desc', ['name' => 'desc']],
    'comma separated' => ['name,created_at:desc', ['name' => 'asc', 'created_at' => 'desc']],
    'array style' => [['name' => 'desc'], ['name' => 'desc']],
]);
