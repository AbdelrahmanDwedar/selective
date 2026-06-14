<?php

namespace AbdelrahmanDwedar\Selective\Traits;

use Illuminate\Support\Facades\Log;
use AbdelrahmanDwedar\Selective\BloomFilterService;
use Exception;

/**
 * @property array $bloomFilters
 */
trait HasBloomFilters
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasBloomFilters(): void
    {
        static::created(function ($model) {
            $model->syncToBloomFilter($model, 'created');
        });

        static::updating(function ($model) {
            $model->syncToBloomFilter($model, 'updating');
        });

        static::deleted(function ($model) {
            // Bloom filters do not support deletion of individual items.
            // We just log that the item was deleted from DB but remains in the filter.
            if (property_exists($model, 'bloomFilters') && !empty($model->bloomFilters)) {
                Log::debug("[Selective] Model deleted. Note that bloom filters do not support item removal. Items will remain in the filter: " . implode(', ', $model->bloomFilters));
            }
        });
    }

    /**
     * Sync the model's attributes to the bloom filter.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $event
     * @return void
     */
    protected function syncToBloomFilter($model, string $event): void
    {
        if (!property_exists($model, 'bloomFilters') || empty($model->bloomFilters)) {
            return;
        }

        try {
            $service = app(BloomFilterService::class);
            $table = $model->getTable();

            foreach ($model->bloomFilters as $column) {
                // If creating, add the value. If updating, only add if the value changed.
                if ($event === 'created' || ($event === 'updating' && $model->isDirty($column))) {
                    $value = $model->getAttribute($column);
                    
                    if (!empty($value)) {
                        $key = "{$table}:{$column}";
                        $service->add($key, (string) $value);
                    }
                }
            }
        } catch (Exception $e) {
            // We don't want a bloom filter failure to stop a database transaction
            Log::warning("[Selective] Failed to sync model to bloom filter during {$event}: " . $e->getMessage());
        }
    }
}
