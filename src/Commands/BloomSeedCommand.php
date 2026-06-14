<?php

namespace AbdelrahmanDwedar\Selective\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AbdelrahmanDwedar\Selective\BloomFilterService;

class BloomSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bloom:seed 
                            {table : The database table name} 
                            {column : The database column name} 
                            {--fresh : Delete existing filter before seeding}
                            {--error-rate= : Custom error rate (overrides config)}
                            {--capacity= : Custom capacity (overrides config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a bloom filter from an existing database table column';

    /**
     * Execute the console command.
     */
    public function handle(BloomFilterService $service)
    {
        $table = $this->argument('table');
        $column = $this->argument('column');
        $key = "{$table}:{$column}";

        $this->info("Starting bloom filter seed for {$table}.{$column}...");

        if ($this->option('fresh')) {
            $this->warn("Deleting existing filter...");
            $service->delete($key);
        }

        $errorRate = $this->option('error-rate') ? (float) $this->option('error-rate') : null;
        $capacity = $this->option('capacity') ? (int) $this->option('capacity') : null;

        $service->reserve($key, $errorRate, $capacity);
        
        $total = DB::table($table)->count();
        
        if ($total === 0) {
            $this->info("No records found in {$table}.");
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $chunkSize = 1000;
        $added = 0;
        
        $startTime = microtime(true);

        DB::table($table)->select($column)->orderBy('id')->chunk($chunkSize, function ($records) use ($service, $key, $column, $bar, &$added) {
            $items = $records->pluck($column)->filter()->toArray();
            
            if (!empty($items)) {
                $service->addMultiple($key, $items);
                $added += count($items);
            }
            
            $bar->advance($records->count());
        });

        $bar->finish();
        $this->newLine(2);

        $endTime = microtime(true);
        $timeTaken = round($endTime - $startTime, 2);

        $this->info("Successfully seeded {$added} items into bloom filter in {$timeTaken} seconds.");

        return 0;
    }
}
