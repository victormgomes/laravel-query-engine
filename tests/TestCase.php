<?php

declare(strict_types=1);

namespace Victormgomes\LaravelQueryEngine\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Victormgomes\LaravelQueryEngine\LaravelQueryEngineServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Victormgomes\\LaravelQueryEngine\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelQueryEngineServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $schema = $app['db']->connection()->getSchemaBuilder();

        $schema->create('authors', function ($table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('posts', function ($table): void {
            $table->id();
            $table->foreignId('author_id');
            $table->string('title');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('tags')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
