<?php

namespace AbdelrahmanDwedar\Selective;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Exception;

class BloomFilterService
{
    /**
     * @var \Illuminate\Redis\Connections\Connection
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var bool
     */
    protected $fallbackOnError;

    /**
     * @var bool
     */
    protected $autoReserve;

    /**
     * @var float
     */
    protected $defaultErrorRate;

    /**
     * @var int
     */
    protected $defaultCapacity;

    public function __construct(ConfigRepository $config)
    {
        $connectionName = $config->get('selective.redis_connection', 'default');
        $this->redis = Redis::connection($connectionName);
        $this->prefix = $config->get('selective.key_prefix', 'selective:');
        $this->fallbackOnError = $config->get('selective.fallback_on_error', true);
        $this->autoReserve = $config->get('selective.auto_reserve', true);
        $this->defaultErrorRate = $config->get('selective.default_error_rate', 0.01);
        $this->defaultCapacity = $config->get('selective.default_capacity', 1000);
    }

    /**
     * Get the prefixed key.
     *
     * @param string $key
     * @return string
     */
    public function getKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Reserve a bloom filter.
     *
     * @param string $key
     * @param float|null $errorRate
     * @param int|null $capacity
     * @return void
     */
    public function reserve(string $key, ?float $errorRate = null, ?int $capacity = null): void
    {
        try {
            $this->redis->executeRaw([
                'BF.RESERVE',
                $this->getKey($key),
                $errorRate ?? $this->defaultErrorRate,
                $capacity ?? $this->defaultCapacity
            ]);
        } catch (Exception $e) {
            $this->handleException('Failed to reserve bloom filter', $e);
        }
    }

    /**
     * Add an item to the bloom filter.
     *
     * @param string $key
     * @param string $item
     * @return bool True if the item was newly added, false if it may already exist
     */
    public function add(string $key, string $item): bool
    {
        try {
            if ($this->autoReserve) {
                // BF.INSERT can automatically create the filter if it doesn't exist
                $result = $this->redis->executeRaw([
                    'BF.INSERT',
                    $this->getKey($key),
                    'CAPACITY', $this->defaultCapacity,
                    'ERROR', $this->defaultErrorRate,
                    'ITEMS', $item
                ]);
                return (bool) $result[0];
            } else {
                return (bool) $this->redis->executeRaw([
                    'BF.ADD',
                    $this->getKey($key),
                    $item
                ]);
            }
        } catch (Exception $e) {
            $this->handleException('Failed to add item to bloom filter', $e);
            return false;
        }
    }

    /**
     * Check if an item exists in the bloom filter.
     *
     * @param string $key
     * @param string $item
     * @return bool True if it might exist, false if it definitely doesn't
     */
    public function exists(string $key, string $item): bool
    {
        try {
            return (bool) $this->redis->executeRaw([
                'BF.EXISTS',
                $this->getKey($key),
                $item
            ]);
        } catch (Exception $e) {
            $this->handleException('Failed to check existence in bloom filter', $e);
            // If fallback is enabled, we return false so the caller falls back to DB check
            return false;
        }
    }

    /**
     * Add multiple items to the bloom filter.
     *
     * @param string $key
     * @param array $items
     * @return array Array of booleans corresponding to each item
     */
    public function addMultiple(string $key, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        try {
            if ($this->autoReserve) {
                $args = [
                    'BF.INSERT',
                    $this->getKey($key),
                    'CAPACITY', $this->defaultCapacity,
                    'ERROR', $this->defaultErrorRate,
                    'ITEMS'
                ];
                $result = $this->redis->executeRaw(array_merge($args, $items));
                return array_map(fn($r) => (bool) $r, $result);
            } else {
                $args = ['BF.MADD', $this->getKey($key)];
                $result = $this->redis->executeRaw(array_merge($args, $items));
                return array_map(fn($r) => (bool) $r, $result);
            }
        } catch (Exception $e) {
            $this->handleException('Failed to add multiple items to bloom filter', $e);
            return array_fill(0, count($items), false);
        }
    }

    /**
     * Check if multiple items exist in the bloom filter.
     *
     * @param string $key
     * @param array $items
     * @return array Array of booleans corresponding to each item
     */
    public function existsMultiple(string $key, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        try {
            $args = ['BF.MEXISTS', $this->getKey($key)];
            $result = $this->redis->executeRaw(array_merge($args, $items));
            return array_map(fn($r) => (bool) $r, $result);
        } catch (Exception $e) {
            $this->handleException('Failed to check multiple existence in bloom filter', $e);
            return array_fill(0, count($items), false);
        }
    }

    /**
     * Get information about a bloom filter.
     *
     * @param string $key
     * @return array Associative array of info, or empty array if it doesn't exist
     */
    public function info(string $key): array
    {
        try {
            $result = $this->redis->executeRaw(['BF.INFO', $this->getKey($key)]);
            
            // Result comes back as a flat list: [key1, val1, key2, val2, ...]
            $info = [];
            for ($i = 0; $i < count($result); $i += 2) {
                if (isset($result[$i + 1])) {
                    $info[$result[$i]] = $result[$i + 1];
                }
            }
            return $info;
        } catch (Exception $e) {
            $this->handleException('Failed to get bloom filter info', $e);
            return [];
        }
    }

    /**
     * Delete a bloom filter.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        try {
            return (bool) $this->redis->del($this->getKey($key));
        } catch (Exception $e) {
            $this->handleException('Failed to delete bloom filter', $e);
            return false;
        }
    }

    /**
     * Handle an exception thrown during a Redis operation.
     *
     * @param string $message
     * @param Exception $e
     * @return void
     * @throws Exception
     */
    protected function handleException(string $message, Exception $e): void
    {
        if ($this->fallbackOnError) {
            Log::warning("[Selective] {$message}: " . $e->getMessage());
        } else {
            throw $e;
        }
    }
}
