<?php

namespace AbdelrahmanDwedar\Selective\Commands;

use AbdelrahmanDwedar\Selective\BloomFilterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class BloomClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:clear 
                            {table? : The database table name} 
                            {column? : The database column name} 
                            {--all : Clear all selective bloom filters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear bloom filter(s)';

    /**
     * Execute the console command.
     */
    public function handle(BloomFilterService $service)
    {
        if ($this->option('all')) {
            return $this->clearAllFilters();
        }

        $table = $this->argument('table');
        $column = $this->argument('column');

        if ($table && $column) {
            return $this->clearSingleFilter($service, "{$table}:{$column}");
        }

        $this->error('You must provide either a table and column, or use the --all option.');
        $this->line('Usage:');
        $this->line('  php artisan bloom:clear users email');
        $this->line('  php artisan bloom:clear --all');

        return 1;
    }

    /**
     * Clear a single filter.
     */
    protected function clearSingleFilter(BloomFilterService $service, string $key)
    {
        if (! $this->confirm("Are you sure you want to delete the bloom filter for '{$key}'?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        if ($service->delete($key)) {
            $this->info("Successfully deleted bloom filter for '{$key}'.");

            return 0;
        } else {
            $this->error("Failed to delete bloom filter for '{$key}'. It may not exist.");

            return 1;
        }
    }

    /**
     * Clear all filters.
     */
    protected function clearAllFilters()
    {
        $prefix = config('selective.key_prefix', 'selective:');

        $redis = Redis::connection(config('selective.redis_connection', 'default'));
        $keys = $redis->executeRaw(['KEYS', $prefix.'*']);

        if (empty($keys)) {
            $this->info("No bloom filters found with prefix '{$prefix}'.");

            return 0;
        }

        $this->warn('Found '.count($keys)." bloom filter(s) matching prefix '{$prefix}'.");

        if (! $this->confirm('Are you sure you want to delete ALL of them? This cannot be undone.')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $deleted = $redis->executeRaw(array_merge(['DEL'], $keys));

        $this->info("Successfully deleted {$deleted} bloom filter(s).");

        return 0;
    }
}
