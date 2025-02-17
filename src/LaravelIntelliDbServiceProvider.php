<?php

namespace Amoza3\LaravelIntelliDb;

use Illuminate\Support\ServiceProvider;
use Amoza3\LaravelIntelliDb\Console\AiRuleCommand;
use Amoza3\LaravelIntelliDb\Console\AiModelCommand;
use Amoza3\LaravelIntelliDb\Console\AiFactoryCommand;
use Amoza3\LaravelIntelliDb\Console\AiMigrationCommand;
use Amoza3\LaravelIntelliDb\Console\AiMiddlewareCommand;
use Amoza3\LaravelIntelliDb\Console\AiRepositoryCommand;
use Amoza3\LaravelIntelliDb\Console\AiRepositoryServiceCommand;

class LaravelIntelliDbServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/intelli-db.php' => config_path('intelli-db.php'),
            ], 'config');

            $this->commands([
                AiRuleCommand::class,
                AiMigrationCommand::class,
                AiFactoryCommand::class,
                AiModelCommand::class,
                AiRepositoryCommand::class,
                AiRepositoryServiceCommand::class,
                AiMiddlewareCommand::class,
            ]);
        }
    }
}
