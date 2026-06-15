<?php

namespace AbdelrahmanDwedar\Selective\Tests;

use AbdelrahmanDwedar\Selective\SelectiveServiceProvider;
use Illuminate\Support\Facades\Redis;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            Redis::connection()->ping();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not running. Please start Redis to run feature tests.');
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            SelectiveServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.redis.client', 'predis');
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ]);
    }
}
