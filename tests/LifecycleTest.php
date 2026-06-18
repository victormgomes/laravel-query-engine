<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Rules;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Tests\Models\Author;
use Victormgomes\QueryParams\Tests\Models\Post;

it('tests the complete lifecycle from URL to JSON response', function () {
    // 1. Fresh Database State
    Schema::disableForeignKeyConstraints();
    DB::table('posts')->truncate();
    DB::table('authors')->truncate();
    Schema::enableForeignKeyConstraints();

    $author = Author::create(['name' => 'Victor M. Gomes']);

    $post1 = Post::create([
        'author_id' => $author->id,
        'title' => 'UNIQUE_TITLE_FOR_FILTERING',
        'views' => 15000,
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);

    $post2 = Post::create([
        'author_id' => $author->id,
        'title' => 'OTHER_TITLE',
        'views' => 50,
        'is_published' => false,
    ]);

    // 2. Mock Request
    // Note: We use name:desc because the original code doesn't support -name
    $request = new Request;
    $request->merge([
        'filters' => [
            'views' => '15000',
        ],
        'sorts' => ['views' => 'desc'],
        'includes' => ['author'],
        'fields' => ['id', 'title', 'author_id'],
        'page' => [
            'number' => 1,
            'limit' => 1,
        ],
    ]);

    // 3. Rule Generation
    $rules = Rules::generate(Post::class);
    expect($rules)->toBeArray();

    // 4. Query Building
    QueryNormalizer::normalize($request);
    $paginator = QueryBuilder::paginateQuery(Post::class, $request);

    // 5. Assertions
    expect($paginator->total())->toBe(1);

    $item = $paginator->first();
    expect($item->toArray())->toHaveKeys(['id', 'title', 'author_id']);
});
