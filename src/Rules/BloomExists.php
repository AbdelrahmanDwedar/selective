<?php

namespace AbdelrahmanDwedar\Selective\Rules;

use AbdelrahmanDwedar\Selective\BloomFilterService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

class BloomExists implements ValidationRule
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var string|null
     */
    protected $column;

    /**
     * Create a new rule instance.
     *
     * @param  string  $table  The database table name
     * @param  string|null  $column  The database column name (defaults to attribute name)
     */
    public function __construct(string $table, ?string $column = null)
    {
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $column = $this->column ?? $attribute;
        $bloomKey = "{$this->table}:{$column}";

        $service = app(BloomFilterService::class);

        // Check if it exists in the bloom filter
        if (! $service->exists($bloomKey, (string) $value)) {
            // It definitely does not exist
            $fail('The selected :attribute is invalid.');

            return;
        }

        // It might exist, so we must verify in the database
        if (! DB::table($this->table)->where($column, $value)->exists()) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
