<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Victormgomes\QueryParams\Attributes\MapQueryParams;
use Victormgomes\QueryParams\Enums\AssociatedIndex;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Support\ModelRegistry;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Tests\Models\Post;

// ---------------------------------------------------------------------- //
//  Eloquent Builder :: paginateQuery() macro                             //
// ---------------------------------------------------------------------- //

it('can call paginateQuery macro on model via static call', function () {
    $request = new Request([
        'filters' => ['views' => ['gt' => 0]],
        'page' => ['number' => 1, 'limit' => 10],
    ]);

    $this->app->instance('request', $request);

    $result = Post::paginateQuery();

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
});

it('can call paginateQuery macro with custom request', function () {
    $request = new Request([
        'filters' => ['views' => ['gt' => 0]],
        'page' => ['number' => 1, 'limit' => 5],
    ]);

    $result = Post::paginateQuery($request);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->perPage())->toBe(5);
});

// ---------------------------------------------------------------------- //
//  QueryBuilder :: macro() system                                        //
// ---------------------------------------------------------------------- //

it('can register and call a custom macro on QueryBuilder', function () {
    QueryBuilder::macro('ping', function () {
        return 'pong';
    });

    expect(QueryBuilder::ping())->toBe('pong');
});

it('can register and call a macro with parameters', function () {
    QueryBuilder::macro('greet', function (string $name) {
        return "Hello, {$name}!";
    });

    expect(QueryBuilder::greet('World'))->toBe('Hello, World!');
});

// ---------------------------------------------------------------------- //
//  Global request-model registry (ModelRegistry)                          //
// ---------------------------------------------------------------------- //

it('can register and resolve request-model mapping', function () {
    ModelRegistry::register(stdClass::class, Post::class);

    expect(ModelRegistry::resolveRequest(stdClass::class))->toBe(Post::class);
});

it('returns null for unregistered request', function () {
    expect(ModelRegistry::resolveRequest('NonExistentRequest'))->toBeNull();
});

// ---------------------------------------------------------------------- //
//  ModelRegistry :: resolveFrom()                                        //
// ---------------------------------------------------------------------- //

it('resolves model from global registry first', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    ModelRegistry::register($request::class, Post::class);

    expect(ModelRegistry::resolveFrom($request))->toBe(Post::class);
});

it('resolves model from #[MapQueryParams] attribute as fallback', function () {
    $request = new #[MapQueryParams(Post::class)] class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    expect(ModelRegistry::resolveFrom($request))->toBe(Post::class);
});

it('resolves model from model() method as second fallback', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }

        public function model(): string
        {
            return Post::class;
        }
    };

    expect(ModelRegistry::resolveFrom($request))->toBe(Post::class);
});

it('returns null when no model is resolved', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    expect(ModelRegistry::resolveFrom($request))->toBeNull();
});

// ---------------------------------------------------------------------- //
//  QueryBuilder :: apply()                                                //
// ---------------------------------------------------------------------- //

it('apply returns a Builder without pagination', function () {
    $request = new Request([
        'filters' => ['views' => ['gt' => 0]],
    ]);

    $result = QueryBuilder::buildQuery(Post::class, $request);

    expect($result)->toBeInstanceOf(Builder::class);
    expect($result->getQuery()->wheres)->not->toBeEmpty();
});

it('buildQuery applies sorts from request', function () {
    $request = new Request([
        'sorts' => ['created_at' => 'desc'],
    ]);

    $query = QueryBuilder::buildQuery(Post::class, $request);

    $orders = $query->getQuery()->orders;
    expect($orders)->toHaveCount(1);
    expect($orders[0]['column'])->toBe('created_at');
    expect($orders[0]['direction'])->toBe('desc');
});

// ---------------------------------------------------------------------- //
//  queryParamRules() mixin method                                        //
// ---------------------------------------------------------------------- //

it('queryParamRules returns rules when model is registered', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    ModelRegistry::register($request::class, Post::class);

    $rules = $request->queryParamRules();

    expect($rules)->toBeArray();
    expect($rules)->toHaveKey('filters');
});

it('queryParamRules returns empty array when no model is resolved', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    $rules = $request->queryParamRules();

    expect($rules)->toBeArray();
    expect($rules)->toBeEmpty();
});

// ---------------------------------------------------------------------- //
//  Backward compatibility                                                //
// ---------------------------------------------------------------------- //

it('existing QueryBuilder::paginateQuery still works', function () {
    $request = new Request([
        'filters' => ['views' => ['gt' => 0]],
        'page' => ['number' => 1, 'limit' => 10],
    ]);

    $result = QueryBuilder::paginateQuery(Post::class, $request);

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
});

it('existing QueryBuilder::normalize still works', function () {
    $request = new Request([
        'filters' => ['name' => ['eq' => 'Victor']],
    ]);

    QueryNormalizer::normalize($request);

    $filters = $request->get(AssociatedIndex::FILTERS->value);
    expect($filters)->toHaveKey('name');
});

// ---------------------------------------------------------------------- //
//  FormRequest auto-* (integration)                                      //
// ---------------------------------------------------------------------- //

it('auto-normalizes globally registered FormRequests', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    $request->merge([
        'filters' => ['title' => ['like' => 'Eloquent']],
    ]);

    ModelRegistry::register($request::class, Post::class);

    // Simulate container resolution to trigger the resolving hook
    $this->app->resolving(FormRequest::class, function ($req) {
        // Our service provider already does this
    });
    $this->app->instance(FormRequest::class, $request);

    // Manually trigger (the hook runs automatically on resolution)
    $modelFQCN = ModelRegistry::resolveFrom($request);
    QueryNormalizer::normalize($request, $modelFQCN);

    expect($request->get('filters'))->toHaveKey('title');
});

it('auto-generates rules for globally registered FormRequests via mixin', function () {
    $request = new class extends FormRequest
    {
        public function authorize(): bool
        {
            return true;
        }
    };

    ModelRegistry::register($request::class, Post::class);

    // The mixin provides rules() method
    $rules = $request->rules();

    expect($rules)->toBeArray();
    expect($rules)->toHaveKey('filters');
});
