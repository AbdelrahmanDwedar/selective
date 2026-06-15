<?php

namespace AbdelrahmanDwedar\Selective;

use AbdelrahmanDwedar\Selective\Commands\BloomClearCommand;
use AbdelrahmanDwedar\Selective\Commands\BloomSeedCommand;
use AbdelrahmanDwedar\Selective\Commands\BloomStatusCommand;
use Illuminate\Support\ServiceProvider;

class SelectiveServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/selective.php', 'selective'
        );

        $this->app->singleton(BloomFilterService::class, function ($app) {
            return new BloomFilterService($app->make('config'));
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/selective.php' => config_path('selective.php'),
            ], 'selective-config');

            $this->commands([
                BloomSeedCommand::class,
                BloomStatusCommand::class,
                BloomClearCommand::class,
            ]);
        }
    }
}
