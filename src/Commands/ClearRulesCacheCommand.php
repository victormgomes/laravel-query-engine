<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearRulesCacheCommand extends Command
{
    protected $signature = 'laravel-query-engine:clear {model? : The model class to clear cache for}';

    protected $description = 'Wipe all cached query parameter rules';

    public function handle(): int
    {
        $model = $this->argument('model');
        $cache = Cache::getFacadeRoot();

        if ($model) {
            $this->info("Clearing query parameter rules for: {$model}");
            $cacheKey = 'laravel-query-engine.rules.'.md5($model);
            Cache::forget($cacheKey);
            $this->info('Done!');

            return self::SUCCESS;
        }

        $this->info('Clearing all query parameter rules cache...');

        if ($cache->supportsTags()) {
            $cache->tags(['laravel-query-engine'])->flush();
            $this->info('Cache tags flushed successfully!');
        } else {
            $this->error('The current cache driver does not support tags.');
            $this->comment('Please use "php artisan cache:clear" to wipe all cache or provide a specific model.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
