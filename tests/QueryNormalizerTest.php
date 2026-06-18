<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Tests\Models\Post;

it('normalizes simple filters', function () {
    $request = new Request([
        'filters' => [
            'name' => 'Victor',
        ],
    ]);

    QueryNormalizer::normalize($request);

    $filters = $request->get(AssociatedIndex::FILTERS->value);

    expect($filters)->toBe([
        'name' => [
            Operators::EQ->value => 'Victor',
        ],
    ]);
});

it('normalizes filters with operators', function () {
    $request = new Request([
        'filters' => [
            'age' => [
                'gt' => 20,
            ],
        ],
    ]);

    QueryNormalizer::normalize($request);

    $filters = $request->get(AssociatedIndex::FILTERS->value);

    expect($filters)->toBe([
        'age' => [
            'gt' => 20,
        ],
    ]);
});

it('normalizes sorts as array', function () {
    $request = new Request(['sorts' => ['name' => 'desc']]);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::SORTS->value))->toBe(['name' => 'desc']);
});

it('normalizes sorts from JSON', function () {
    $request = new Request(['sorts' => '{"name":"desc"}']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::SORTS->value))->toBe(['name' => 'desc']);
});

it('normalizes page as array', function () {
    $request = new Request(['page' => ['number' => 3, 'limit' => 25]]);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::PAGE->value))->toBe([
        'number' => 3,
        'limit' => 25,
    ]);
});

it('decodes JSON filters with in operator', function () {
    $request = new Request(['filters' => '{"id":{"in":[1,2,3]}}']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::FILTERS->value))->toBe([
        'id' => ['in' => [1, 2, 3]],
    ]);
});

it('decodes JSON filters with multiple operators', function () {
    $request = new Request(['filters' => '{"name":{"eq":"Victor"},"views":{"gt":5}}']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::FILTERS->value))->toBe([
        'name' => ['eq' => 'Victor'],
        'views' => ['gt' => 5],
    ]);
});

it('decodes JSON includes with shorthand list', function () {
    $request = new Request(['includes' => '{"author":["name","email"]}']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::INCLUDES->value))->toBe([
        'author' => ['fields' => ['name', 'email']],
    ]);
});

it('decodes JSON includes as array', function () {
    $request = new Request(['includes' => '["author","comments"]']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::INCLUDES->value))->toBe(['author', 'comments']);
});

it('decodes JSON page', function () {
    $request = new Request(['page' => '{"number":1,"limit":10}']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::PAGE->value))->toBe([
        'number' => 1,
        'limit' => 10,
    ]);
});

it('decodes JSON fields', function () {
    $request = new Request(['fields' => '["id","title","content"]']);
    QueryNormalizer::normalize($request);

    expect($request->get(AssociatedIndex::FIELDS->value))->toBe(['id', 'title', 'content']);
});

it('wraps empty or null strings in eq operator during normalization', function () {
    $request = new Request([
        'filters' => [
            'views' => '',
            'published_at' => 'null',
            'is_published' => null,
        ],
    ]);

    QueryNormalizer::normalize($request, Post::class);

    $filters = $request->get(AssociatedIndex::FILTERS->value);

    expect($filters)->toBe([
        'views' => [
            Operators::EQ->value => '',
        ],
        'published_at' => [
            Operators::EQ->value => 'null',
        ],
        'is_published' => [
            Operators::EQ->value => null,
        ],
    ]);
});
