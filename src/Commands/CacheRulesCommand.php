<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Victormgomes\LaravelQueryEngine\Rules;

class CacheRulesCommand extends Command
{
    protected $signature = 'laravel-query-engine:cache';

    protected $description = 'Generate and cache all query parameter rules for your models';

    public function handle(): int
    {
        $this->info('Caching query parameter rules...');

        $models = $this->getModels();

        foreach ($models as $model) {
            $this->comment("Caching rules for: {$model}");
            Rules::generate($model);
        }

        $this->info('All rules cached successfully!');

        return self::SUCCESS;
    }

    protected function getModels(): array
    {
        $modelPath = app_path('Models');
        $models = [];

        if (is_dir($modelPath)) {
            $files = (new Finder)->in($modelPath)->files()->name('*.php');

            foreach ($files as $file) {
                $class = 'App\\Models\\'.Str::replaceLast('.php', '', $file->getRelativePathname());
                if (is_subclass_of($class, 'Illuminate\Database\Eloquent\Model')) {
                    $models[] = $class;
                }
            }
        }

        // Also check modules if they exist
        $modulesPath = base_path('modules');
        if (is_dir($modulesPath)) {
            $files = (new Finder)->in($modulesPath)->path('Models')->files()->name('*.php');
            foreach ($files as $file) {
                // Approximate module namespace
                $path = $file->getRelativePathname();
                $segments = explode(DIRECTORY_SEPARATOR, $path);
                $module = $segments[0];
                $className = Str::replaceLast('.php', '', end($segments));
                $class = "Modules\\{$module}\\Models\\{$className}";

                if (class_exists($class) && is_subclass_of($class, 'Illuminate\Database\Eloquent\Model')) {
                    $models[] = $class;
                }
            }
        }

        return array_unique($models);
    }
}
