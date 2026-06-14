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
        }
    }
}
