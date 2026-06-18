<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Validation\ValidationException;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Tests\Models\Author;
use Victormgomes\QueryParams\Tests\Models\Post;
use Victormgomes\QueryParams\Tests\Models\SoftDeletablePost;

it('supports cursor pagination via cursorPaginateQuery macro', function () {
    $author = Author::create(['name' => 'Victor']);

    for ($i = 1; $i <= 5; $i++) {
        Post::create([
            'author_id' => $author->id,
            'title' => "Post {$i}",
            'views' => $i * 10,
        ]);
    }

    // First page request
    $request = new Request([
        'page' => ['limit' => 2],
    ]);

    $paginator1 = Post::cursorPaginateQuery($request);

    expect($paginator1)->toBeInstanceOf(CursorPaginator::class);
    expect($paginator1->count())->toBe(2);
    expect($paginator1->hasMorePages())->toBeTrue();

    $nextCursor = $paginator1->nextCursor()->encode();

    // Second page request with cursor
    $request2 = new Request([
        'page' => [
            'limit' => 2,
            'cursor' => $nextCursor,
        ],
    ]);

    $paginator2 = Post::cursorPaginateQuery($request2);

    expect($paginator2->count())->toBe(2);
    expect($paginator2->first()->title)->toBe('Post 3');
});

it('supports soft deletes via with_deleted and only_deleted filters', function () {
    $author = Author::create(['name' => 'Victor']);

    $post1 = SoftDeletablePost::create([
        'author_id' => $author->id,
        'title' => 'Active Post',
    ]);

    $post2 = SoftDeletablePost::create([
        'author_id' => $author->id,
        'title' => 'Deleted Post',
    ]);
    $post2->delete();

    // 1. Default (no filter): should only return active posts
    $request = new Request([]);
    $results = QueryBuilder::buildQuery(SoftDeletablePost::class, $request)->get();
    expect($results->pluck('title')->toArray())->toBe(['Active Post']);

    // 2. filters[with_deleted]=true: should return both
    $requestWith = new Request([
        'filters' => ['with_deleted' => true],
    ]);
    $resultsWith = QueryBuilder::buildQuery(SoftDeletablePost::class, $requestWith)->get();
    expect($resultsWith->pluck('title')->toArray())->toContain('Active Post', 'Deleted Post');

    // 3. filters[only_deleted]=true: should only return deleted
    $requestOnly = new Request([
        'filters' => ['only_deleted' => true],
    ]);
    $resultsOnly = QueryBuilder::buildQuery(SoftDeletablePost::class, $requestOnly)->get();
    expect($resultsOnly->pluck('title')->toArray())->toBe(['Deleted Post']);
});

it('supports shorthand relationship existence check (exists)', function () {
    $authorWith = Author::create(['name' => 'Author With Posts']);
    $authorWithout = Author::create(['name' => 'Author Without Posts']);

    Post::create([
        'author_id' => $authorWith->id,
        'title' => 'A post',
    ]);

    // 1. Filter authors that have posts (exists = true)
    $requestExists = new Request([
        'filters' => ['posts' => ['exists' => true]],
    ]);
    $authorsExists = QueryBuilder::buildQuery(Author::class, $requestExists)->get();
    expect($authorsExists->pluck('name')->toArray())->toContain('Author With Posts');
    expect($authorsExists->pluck('name')->toArray())->not->toContain('Author Without Posts');

    // 2. Filter authors that do not have posts (exists = false)
    $requestNotExists = new Request([
        'filters' => ['posts' => ['exists' => false]],
    ]);
    $authorsNotExists = QueryBuilder::buildQuery(Author::class, $requestNotExists)->get();
    expect($authorsNotExists->pluck('name')->toArray())->toContain('Author Without Posts');
    expect($authorsNotExists->pluck('name')->toArray())->not->toContain('Author With Posts');
});

it('rejects top-level limit parameter under strict rule validation', function () {
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
