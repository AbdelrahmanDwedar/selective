<?php

namespace AbdelrahmanDwedar\Selective\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use AbdelrahmanDwedar\Selective\BloomFilterService;

class BloomStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:status {table? : The database table name} {column? : The database column name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show bloom filter status and information';

    /**
     * Execute the console command.
     */
    public function handle(BloomFilterService $service)
    {
        $table = $this->argument('table');
        $column = $this->argument('column');

        if ($table && $column) {
            $this->showSingleFilterInfo($service, "{$table}:{$column}");
            return 0;
        }

        $this->showAllFiltersInfo($service);
        return 0;
    }

    /**
     * Show detailed info for a single filter.
     */
    protected function showSingleFilterInfo(BloomFilterService $service, string $key)
    {
        $info = $service->info($key);

        if (empty($info)) {
            $this->error("Bloom filter for '{$key}' does not exist.");
            return;
        }

        $this->info("Bloom filter info for: {$key}");
        
        $table = [];
        foreach ($info as $k => $v) {
            $table[] = [$k, $v];
        }

        $this->table(['Property', 'Value'], $table);
    }

    /**
     * Show basic info for all filters.
     */
    protected function showAllFiltersInfo(BloomFilterService $service)
    {
        // To get all filters, we need to scan Redis for keys matching the prefix
        $prefix = config('selective.key_prefix', 'selective:');
        
        // This gets the raw Redis connection which depends on the driver (predis/phpredis)
        $redis = Redis::connection(config('selective.redis_connection', 'default'));
        
        // Note: KEYS is generally not recommended in production for large datasets, 
        // but it's acceptable for a status command.
        $keys = $redis->executeRaw(['KEYS', $prefix . '*']);

        if (empty($keys)) {
            $this->info("No bloom filters found with prefix '{$prefix}'.");
            return;
        }

        $rows = [];
        foreach ($keys as $fullKey) {
            // Strip the prefix for the display and info command
            $key = str_replace($prefix, '', $fullKey);
            $info = $service->info($key);
            
            $rows[] = [
                $fullKey,
                $info['Capacity'] ?? 'N/A',
                $info['Size'] ?? 'N/A',
                $info['Number of items inserted'] ?? 'N/A',
                $info['Expansion rate'] ?? 'N/A',
            ];
        }

        $this->info("Found " . count($keys) . " bloom filter(s):");
        $this->table(['Key', 'Capacity', 'Size (Bytes)', 'Items Inserted', 'Expansion Rate'], $rows);
    }
}
