<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Tests\Models\TestModel;

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

it('normalizes sorts', function () {
    $request = new Request([
        'sort' => 'name,-age',
    ]);

    QueryBuilder::normalize($request);

    $sorts = $request->get(AssociatedIndex::SORTS);

    expect($sorts)->toBe([
        'name' => 'asc',
        'age' => 'desc',
    ]);
});
