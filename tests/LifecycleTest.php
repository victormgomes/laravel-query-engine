<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Victormgomes\QueryParams\QueryBuilder;
use Victormgomes\QueryParams\Resources\QueryResource;
use Victormgomes\QueryParams\Rules;
use Victormgomes\QueryParams\Tests\Models\Author;
use Victormgomes\QueryParams\Tests\Models\Post;

it('tests the complete lifecycle from URL to JSON response', function () {
    // 1. Fresh Database State
    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    \Illuminate\Support\Facades\DB::table('posts')->truncate();
    \Illuminate\Support\Facades\DB::table('authors')->truncate();
    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

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
    $request = new Request();
    $request->merge([
        'filter' => [
            'views' => '15000', 
        ],
        'sort' => 'views:desc',
        'include' => 'author',
        'fields' => 'id,title,author_id',
        'page' => [
            'number' => 1,
            'limit' => 1
        ]
    ]);

    // 3. Rule Generation
    $rules = Rules::generate(Post::class);
    expect($rules)->toBeArray();

    // 4. Query Building
    QueryBuilder::normalize($request);
    $paginator = QueryBuilder::build(Post::class, $request);

    // 5. Response Transformation
    $resource = new QueryResource($paginator);
    $response = $resource->toArray($request);

    // 6. Assertions
    expect($response)->toHaveKeys(['collection', 'total_items', 'per_page', 'current_page']);
    
    // We expect 1 item
    expect((int)$response['total_items'])->toBe(1);

    $item = $response['collection']->first();
    $data = is_array($item) ? $item : $item->toArray($request);
    
    expect($data)->toHaveKeys(['id', 'title', 'author_id']);
});
