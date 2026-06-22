<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Tests\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeletablePost extends Post
{
    use SoftDeletes;
}
