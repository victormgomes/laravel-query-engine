<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    protected $table = 'test_models';

    protected $guarded = [];
}
