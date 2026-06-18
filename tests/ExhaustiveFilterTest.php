<?php

declare(strict_types=1);

use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Tests\Models\Post;

it('applies all basic comparison operators', function (string $operator, string $expectedSql) {
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

it('applies null and not null operators', function (string $operator, string $expectedSql) {
    $query = Post::query();
    Filter::build($query, 'published_at', $operator, null);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::NULL->value, 'where "published_at" is null'],
    [Operators::NOTNULL->value, 'where "published_at" is not null'],
]);

it('applies in and not in operators', function (string $operator, string $expectedSql) {
    $query = Post::query();
    Filter::build($query, 'id', $operator, [1, 2, 3]);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::IN->value, 'where "id" in (?, ?, ?)'],
    [Operators::NIN->value, 'where "id" not in (?, ?, ?)'],
]);

it('applies between and not between operators', function (string $operator, string $expectedSql) {
    $query = Post::query();
    Filter::build($query, 'views', $operator, [10, 20]);

    expect($query->toSql())->toContain($expectedSql);
})->with([
    [Operators::BETWEEN->value, 'where "views" between ? and ?'],
    [Operators::NBETWEEN->value, 'where "views" not between ? and ?'],
]);

it('applies like and ilike operators', function (string $operator, string $expectedSubSql) {
    $query = Post::query();
    Filter::build($query, 'title', $operator, 'test');

    expect($query->toSql())->toContain($expectedSubSql);
})->with([
    [Operators::LIKE->value, 'where "title" like ?'],
    [Operators::NOTLIKE->value, 'where "title" not like ?'],
    [Operators::ILIKE->value, 'where "title" like ?'],
    [Operators::NOTILIKE->value, 'where "title" not like ?'],
]);

it('applies contains operator', function () {
    $query = Post::query();
    Filter::build($query, 'tags', Operators::CONTAINS->value, 'laravel');

    expect($query->toSql())->toContain('json_each("tags")');
});

it('aborts safely when using postgres json operators on sqlite', function (string $operator) {
    $query = Post::query();

    expect(fn () => Filter::build($query, 'tags', $operator, 'laravel'))
        ->toThrow(InvalidArgumentException::class, "The '{$operator}' operator is only supported on PostgreSQL databases.");
})->with([
    Operators::CONTAINEDBY->value,
    Operators::OVERLAP->value,
]);

it('aborts safely when using full-text search operator on sqlite', function () {
    $query = Post::query();

    expect(fn () => Filter::build($query, 'title', Operators::FTS->value, 'search term'))
        ->toThrow(InvalidArgumentException::class, "The 'fts' operator is only supported on PostgreSQL databases.");
});
