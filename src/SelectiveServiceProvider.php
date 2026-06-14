<?php

namespace AbdelrahmanDwedar\Selective;

use Illuminate\Support\ServiceProvider;

class SelectiveServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/selective.php', 'selective'
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
                __DIR__ . '/../config/selective.php' => config_path('selective.php'),
            ], 'selective-config');

            $this->commands([
                \AbdelrahmanDwedar\Selective\Commands\BloomSeedCommand::class,
                \AbdelrahmanDwedar\Selective\Commands\BloomStatusCommand::class,
                \AbdelrahmanDwedar\Selective\Commands\BloomClearCommand::class,
            ]);
        }
    }
}
