<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Victormgomes\LaravelQueryEngine\Support\Resource;
use Victormgomes\LaravelQueryEngine\Support\RuleGenerator;

class Rules
{
    public static function generate(string $modelFQCN): array
    {
        $rules = self::getRules($modelFQCN);

        if (Config::get('laravel-query-engine.debug', false)) {
            Log::info("Generated rules for {$modelFQCN}: ".json_encode($rules));
        }

        return $rules;
    }

    private static function getRules(string $modelFQCN): array
    {
        $enabled = Config::get('laravel-query-engine.caching.enabled', true);
        $force = Config::get('laravel-query-engine.force_cache', false);
        $isProduction = Config::get('app.env') === 'production';

        if (! ($enabled && ($isProduction || $force))) {
            return self::buildRules($modelFQCN);
        }

        $cacheKey = 'rules.'.md5($modelFQCN);
        $ttl = Config::get('laravel-query-engine.caching.ttl', 3600);

        // Try using tags for easier clearing if supported
        $cache = Cache::getFacadeRoot();
        if ($cache->supportsTags()) {
            return $cache->tags(['laravel-query-engine'])->remember($cacheKey, $ttl, fn () => self::buildRules($modelFQCN));
        }

        return $cache->remember('laravel-query-engine.'.$cacheKey, $ttl, fn () => self::buildRules($modelFQCN));
    }

    private static function buildRules(string $modelFQCN): array
    {
        $resources = Resource::generate($modelFQCN);

        return RuleGenerator::generate($resources);
    }
}
