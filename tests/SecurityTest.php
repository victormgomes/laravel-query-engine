<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\Rules;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Support\Resource;
use Victormgomes\QueryParams\Tests\Models\Post;

it('respects globally disabled features in resource generation', function () {
    Config::set('query-params.features.includes', false);
    Config::set('query-params.features.filters', false);

    $resource = Resource::generate(Post::class);

    expect($resource['includes'])->toBeEmpty();
    expect($resource['filters'])->toBeEmpty();
    expect($resource['sorts'])->not->toBeEmpty();
});

it('removes disabled features from the request during normalization', function () {
    Config::set('query-params.features.includes', false);
    Config::set('query-params.features.filters', false);

    $request = new Request([
        'includes' => ['author'],
        'filters' => ['title' => 'test'],
        'sorts' => ['views' => 'desc'],
    ]);

    QueryNormalizer::normalize($request, Post::class);

    expect($request->has(AssociatedIndex::INCLUDES->value))->toBeFalse();
    expect($request->has(AssociatedIndex::FILTERS->value))->toBeFalse();
    expect($request->has(AssociatedIndex::SORTS->value))->toBeTrue();
});

it('respects allowed operators whitelist in rule generation', function () {
    Config::set('query-params.allowed_operators', ['eq', 'like']);

    $rules = Rules::generate(Post::class);

    expect($rules)->toHaveKey('filters.views.eq');
    expect($rules)->not->toHaveKey('filters.views.gt');
    expect($rules)->toHaveKey('filters.title.like');
    expect($rules)->not->toHaveKey('filters.title.fts');
});

it('strips non-whitelisted operators during normalization', function () {
    Config::set('query-params.allowed_operators', ['eq', 'like']);

    $request = new Request([
        'filters' => [
            'views' => [
                'eq' => 10,
                'gt' => 20, // Should be stripped
            ],
            'title' => [
                'fts' => 'search term', // Should be stripped entirely
            ],
        ],
    ]);

    QueryNormalizer::normalize($request, Post::class);

    $filters = $request->get(AssociatedIndex::FILTERS->value);

    expect($filters)->toHaveKey('views');
    expect($filters['views'])->toHaveKey('eq');
    expect($filters['views'])->not->toHaveKey('gt');
    expect($filters)->not->toHaveKey('title');
});
