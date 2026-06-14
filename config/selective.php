<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Error Rate
    |--------------------------------------------------------------------------
    |
    | The default error rate for the bloom filter. This is the acceptable
    | probability of false positives. Lower error rates require more memory.
    | Valid values are between 0 and 1 exclusive (e.g. 0.01 for 1%).
    |
    */
    'default_error_rate' => env('BLOOM_FILTER_ERROR_RATE', 0.01),

    /*
    |--------------------------------------------------------------------------
    | Default Capacity
    |--------------------------------------------------------------------------
    |
    | The default expected capacity of the bloom filter. If you plan to add
    | more items, it's better to increase this to maintain the error rate.
    |
    */
    'default_capacity' => env('BLOOM_FILTER_CAPACITY', 1000),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    |
    | The Redis connection to use for the bloom filter operations. This should
    | match a connection defined in your database.php config file.
    |
    */
    'redis_connection' => env('BLOOM_FILTER_REDIS_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix applied to all bloom filter keys in Redis to prevent collisions
    | with other data.
    |
    */
    'key_prefix' => env('BLOOM_FILTER_KEY_PREFIX', 'selective:'),

    /*
    |--------------------------------------------------------------------------
    | Fallback on Error
    |--------------------------------------------------------------------------
    |
    | If true, the package will gracefully degrade to normal database queries
    | when Redis is unavailable or the bloom filter module is not installed.
    | It will also log a warning. If false, it will throw an exception.
    |
    */
    'fallback_on_error' => env('BLOOM_FILTER_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Reserve
    |--------------------------------------------------------------------------
    |
    | If true, the package will automatically create a new bloom filter with
    | default settings when attempting to add an item to a non-existent filter.
    |
    */
    'auto_reserve' => env('BLOOM_FILTER_AUTO_RESERVE', true),
];
