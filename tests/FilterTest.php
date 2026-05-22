<?php

declare(strict_types=1);

use Victormgomes\QueryParams\Enums\Operators;
use Victormgomes\QueryParams\Support\Builder\Operations\Filter;
use Victormgomes\QueryParams\Tests\Models\TestModel;

it('applies equal filter', function () {
    $query = TestModel::query();

    Filter::build($query, 'name', Operators::EQ, 'Victor');

    expect($query->toSql())->toContain('where "name" = ?');
    expect($query->getBindings())->toContain('Victor');
});

it('applies greater than filter', function () {
    $query = TestModel::query();

    Filter::build($query, 'age', Operators::GT, 20);

    expect($query->toSql())->toContain('where "age" > ?');
    expect($query->getBindings())->toContain(20);
});

it('applies in filter', function () {
    $query = TestModel::query();

    Filter::build($query, 'id', Operators::IN, [1, 2, 3]);

    expect($query->toSql())->toContain('where "id" in (?, ?, ?)');
    expect($query->getBindings())->toBe([1, 2, 3]);
});

it('applies like filter', function () {
    $query = TestModel::query();

    Filter::build($query, 'name', Operators::LIKE, 'Vic');

    expect($query->toSql())->toContain('where "name" like ?');
    expect($query->getBindings())->toContain('%Vic%');
});
