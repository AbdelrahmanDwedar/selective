<?php

namespace AbdelrahmanDwedar\Selective;

use Exception;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BloomFilterService
{
    /**
     * @var ConfigRepository
     */
    protected $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    /**
     * Get the Redis connection.
     *
     * @return Connection
     */
    protected function getConnection()
    {
        return Redis::connection($this->config->get('selective.redis_connection', 'default'));
    }

    /**
     * Get the prefixed key.
     */
    public function getKey(string $key): string
    {
        $prefix = $this->config->get('selective.key_prefix', 'selective:');

        return $prefix.$key;
    }

    /**
     * Reserve a bloom filter.
     */
    public function reserve(string $key, ?float $errorRate = null, ?int $capacity = null): void
    {
        try {
            $this->getConnection()->executeRaw([
                'BF.RESERVE',
                $this->getKey($key),
                $errorRate ?? $this->config->get('selective.default_error_rate', 0.01),
                $capacity ?? $this->config->get('selective.default_capacity', 1000),
            ]);
        } catch (Exception $e) {
            $this->handleException('Failed to reserve bloom filter', $e);
        }
    }

    /**
     * Add an item to the bloom filter.
     *
     * @return bool True if the item was newly added, false if it may already exist
     */
    public function add(string $key, string $item): bool
    {
        try {
            if ($this->config->get('selective.auto_reserve', true)) {
                // BF.INSERT can automatically create the filter if it doesn't exist
                $result = $this->getConnection()->executeRaw([
                    'BF.INSERT',
                    $this->getKey($key),
                    'CAPACITY', $this->config->get('selective.default_capacity', 1000),
                    'ERROR', $this->config->get('selective.default_error_rate', 0.01),
                    'ITEMS', $item,
                ]);

                return (bool) $result[0];
            } else {
                return (bool) $this->getConnection()->executeRaw([
                    'BF.ADD',
                    $this->getKey($key),
                    $item,
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
     * @return bool True if it might exist, false if it definitely doesn't
     */
    public function exists(string $key, string $item): bool
    {
        try {
            return (bool) $this->getConnection()->executeRaw([
                'BF.EXISTS',
                $this->getKey($key),
                $item,
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
     * @return array Array of booleans corresponding to each item
     */
    public function addMultiple(string $key, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        try {
            if ($this->config->get('selective.auto_reserve', true)) {
                $args = [
                    'BF.INSERT',
                    $this->getKey($key),
                    'CAPACITY', $this->config->get('selective.default_capacity', 1000),
                    'ERROR', $this->config->get('selective.default_error_rate', 0.01),
                    'ITEMS',
                ];
                $result = $this->getConnection()->executeRaw(array_merge($args, $items));

                return array_map(fn ($r) => (bool) $r, $result);
            } else {
                $args = ['BF.MADD', $this->getKey($key)];
                $result = $this->getConnection()->executeRaw(array_merge($args, $items));

                return array_map(fn ($r) => (bool) $r, $result);
            }
        } catch (Exception $e) {
            $this->handleException('Failed to add multiple items to bloom filter', $e);

            return array_fill(0, count($items), false);
        }
    }

    /**
     * Check if multiple items exist in the bloom filter.
     *
     * @return array Array of booleans corresponding to each item
     */
    public function existsMultiple(string $key, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        try {
            $args = ['BF.MEXISTS', $this->getKey($key)];
            $result = $this->getConnection()->executeRaw(array_merge($args, $items));

            return array_map(fn ($r) => (bool) $r, $result);
        } catch (Exception $e) {
            $this->handleException('Failed to check multiple existence in bloom filter', $e);

            return array_fill(0, count($items), false);
        }
    }

    /**
     * Get information about a bloom filter.
     *
     * @return array Associative array of info, or empty array if it doesn't exist
     */
    public function info(string $key): array
    {
        try {
            $result = $this->getConnection()->executeRaw(['BF.INFO', $this->getKey($key)]);

            if (! is_array($result)) {
                return [];
            }

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
     */
    public function delete(string $key): bool
    {
        try {
            return (bool) $this->getConnection()->executeRaw(['DEL', $this->getKey($key)]);
        } catch (Exception $e) {
            $this->handleException('Failed to delete bloom filter', $e);

            return false;
        }
    }

    /**
     * Handle an exception thrown during a Redis operation.
     *
     * @throws Exception
     */
    protected function handleException(string $message, Exception $e): void
    {
        if ($this->config->get('selective.fallback_on_error', true)) {
            Log::warning("[Selective] {$message}: ".$e->getMessage());
        } else {
            throw $e;
        }
    }
}
