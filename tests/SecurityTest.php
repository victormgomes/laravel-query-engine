<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Victormgomes\LaravelQueryEngine\Enums\AssociatedIndex;
use Victormgomes\LaravelQueryEngine\QueryBuilder;
use Victormgomes\LaravelQueryEngine\Rules;
use Victormgomes\LaravelQueryEngine\Support\QueryNormalizer;
use Victormgomes\LaravelQueryEngine\Support\Resource;
use Victormgomes\LaravelQueryEngine\Tests\Models\Post;

beforeEach(function (): void {
    Resource::clearCache();
});

it('respects globally disabled features in resource generation', function (): void {
    Config::set('laravel-query-engine.features.includes', false);
    Config::set('laravel-query-engine.features.filters', false);

    $resource = Resource::generate(Post::class);

    expect($resource['includes'])->toBeEmpty();
    expect($resource['filters'])->toBeEmpty();
    expect($resource['sorts'])->not->toBeEmpty();
});

it('removes disabled features from the request during normalization', function (): void {
    Config::set('laravel-query-engine.features.includes', false);
    Config::set('laravel-query-engine.features.filters', false);

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

it('respects allowed operators whitelist in rule generation', function (): void {
    Config::set('laravel-query-engine.allowed_operators', ['eq', 'like']);

    $rules = Rules::generate(Post::class);

    expect($rules)->toHaveKey('filters.views.eq');
    expect($rules)->not->toHaveKey('filters.views.gt');
    expect($rules)->toHaveKey('filters.title.like');
    expect($rules)->not->toHaveKey('filters.title.fts');
});

it('strips non-whitelisted operators during normalization', function (): void {
    Config::set('laravel-query-engine.allowed_operators', ['eq', 'like']);

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

it('rejects top-level limit parameter under strict rule validation', function (): void {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }

        public function rules(): array
        {
            return ['page' => 'sometimes|array'];
        }
    };
    $request->initialize(['limit' => 25]);

    $this->app->instance('request', $request);

    // If we call paginateQuery, it will check the rules and find an unexpected 'limit' parameter
    expect(fn () => QueryBuilder::paginateQuery(Post::class, $request))
        ->toThrow(ValidationException::class);
});
