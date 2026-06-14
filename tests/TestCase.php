<?php

namespace AbdelrahmanDwedar\Selective\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use AbdelrahmanDwedar\Selective\SelectiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            SelectiveServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Perform environment setup if needed
    }
}
