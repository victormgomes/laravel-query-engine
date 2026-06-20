<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Tests\Models\Author;
use Victormgomes\QueryParams\Tests\Models\Post;
use Victormgomes\QueryParams\Tests\Models\SoftDeletablePost;

it('applies all basic comparison operators', function (string $operator, string $expectedSql): void {
    $query = Post::query();
    Filter::build($query, 'views', $operator, 10);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::EQ->value, 'where "views" = ?'],
    [Operators::NE->value, 'where "views" != ?'],
    [Operators::GT->value, 'where "views" > ?'],
    [Operators::GTE->value, 'where "views" >= ?'],
    [Operators::LT->value, 'where "views" < ?'],
    [Operators::LTE->value, 'where "views" <= ?'],
]);

it('applies null and not null operators', function (string $operator, string $expectedSql): void {
    $query = Post::query();
    Filter::build($query, 'published_at', $operator, null);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::NULL->value, 'where "published_at" is null'],
    [Operators::NOTNULL->value, 'where "published_at" is not null'],
]);

it('applies in and not in operators', function (string $operator, string $expectedSql): void {
    $query = Post::query();
    Filter::build($query, 'id', $operator, [1, 2, 3]);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::IN->value, 'where "id" in (?, ?, ?)'],
    [Operators::NIN->value, 'where "id" not in (?, ?, ?)'],
]);

it('applies between and not between operators', function (string $operator, string $expectedSql): void {
    $query = Post::query();
    Filter::build($query, 'views', $operator, [10, 20]);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::BETWEEN->value, 'where "views" between ? and ?'],
    [Operators::NBETWEEN->value, 'where "views" not between ? and ?'],
]);

it('applies like and ilike operators', function (string $operator, string $expectedSubSql): void {
    $query = Post::query();
    Filter::build($query, 'title', $operator, 'test');

    expect($query->toSql())->toContain($expectedSubSql);
})->with([
    [Operators::LIKE->value, 'where "title" like ?'],
    [Operators::NOTLIKE->value, 'where "title" not like ?'],
    [Operators::ILIKE->value, 'where "title" like ?'],
    [Operators::NOTILIKE->value, 'where "title" not like ?'],
]);

it('applies contains operator', function (): void {
    $query = Post::query();
    Filter::build($query, 'tags', Operators::CONTAINS->value, 'laravel');

    expect($query->toSql())->toContain('json_each("tags")');
});

it('aborts safely when using postgres json operators on sqlite', function (string $operator): void {
    $query = Post::query();

    expect(fn () => Filter::build($query, 'tags', $operator, 'laravel'))
        ->toThrow(InvalidArgumentException::class, "The '{$operator}' operator is only supported on PostgreSQL databases.");
})->with([
    Operators::CONTAINEDBY->value,
    Operators::OVERLAP->value,
]);

it('aborts safely when using full-text search operator on sqlite', function (): void {
    $query = Post::query();

    expect(function () use ($query): void {
        Filter::build($query, 'title', Operators::FTS->value, 'search term');
        $query->toSql();
    })->toThrow(RuntimeException::class, 'This database engine does not support fulltext search operations.');
});

it('applies date modifiers operators', function (string $operator, string $expectedSql): void {
    $query = Post::query();
    Filter::build($query, 'published_at', $operator, '2024');

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::YEAR->value, 'strftime(\'%Y\''],
    [Operators::MONTH->value, 'strftime(\'%m\''],
    [Operators::DAY->value, 'strftime(\'%d\''],
    [Operators::DATE->value, 'strftime(\'%Y-%m-%d\''],
    [Operators::TIME->value, 'strftime(\'%H:%M:%S\''],
]);

it('supports soft deletes via with_deleted and only_deleted filters', function (): void {
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

it('supports shorthand relationship existence check (exists)', function (): void {
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
