<?php

declare(strict_types=1);

namespace Victormgomes\QueryParams;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Victormgomes\QueryParams\Commands\CacheRulesCommand;
use Victormgomes\QueryParams\Commands\ClearRulesCacheCommand;
use Victormgomes\QueryParams\Support\ModelRegistry;
use Victormgomes\QueryParams\Support\QueryNormalizer;
use Victormgomes\QueryParams\Support\QueryParamsRequestMixin;
use Victormgomes\QueryParams\Support\Resource;

class QueryParamsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('query-params')
            ->hasConfigFile()
            ->hasCommands([
                CacheRulesCommand::class,
                ClearRulesCacheCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('query-builder', function () {
            return new QueryBuilder;
        });
    }

    public function packageBooted(): void
    {
        EloquentBuilder::macro('paginateQuery', function (?Request $request = null) {
            /** @var Builder $this */
            $request ??= request();

            return QueryBuilder::paginateQuery($this, $request);
        });

        EloquentBuilder::macro('cursorPaginateQuery', function (?Request $request = null) {
            /** @var Builder $this */
            $request ??= request();

            return QueryBuilder::cursorPaginateQuery($this, $request);
        });

        EloquentBuilder::macro('buildQuery', function (?Request $request = null) {
            /** @var Builder $this */
            $request ??= request();

            return QueryBuilder::buildQuery($this, $request);
        });

        EloquentBuilder::macro('getQueryRules', function () {
            /** @var Builder $this */
            return Rules::generate(get_class($this->getModel()));
        });

        EloquentBuilder::macro('getFilterSchema', function () {
            /** @var Builder $this */
            return Resource::getFilterSchema(get_class($this->getModel()));
        });

        $this->app->resolving(FormRequest::class, function (FormRequest $request) {
            $modelFQCN = ModelRegistry::resolveFrom($request);

            if ($modelFQCN !== null) {
                QueryNormalizer::normalize($request, $modelFQCN);
            }
        });

        FormRequest::mixin(new QueryParamsRequestMixin);
    }
}
