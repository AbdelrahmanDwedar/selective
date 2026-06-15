<?php

namespace AbdelrahmanDwedar\Selective\Facades;

use AbdelrahmanDwedar\Selective\BloomFilterService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void reserve(string $key, ?float $errorRate = null, ?int $capacity = null)
 * @method static bool add(string $key, string $item)
 * @method static bool exists(string $key, string $item)
 * @method static array addMultiple(string $key, array $items)
 * @method static array existsMultiple(string $key, array $items)
 * @method static array info(string $key)
 * @method static bool delete(string $key)
 * @method static string getKey(string $key)
 *
 * @see BloomFilterService
 */
class BloomFilter extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return BloomFilterService::class;
    }
}
