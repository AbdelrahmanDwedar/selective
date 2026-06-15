<?php

namespace AbdelrahmanDwedar\Selective\Rules;

use AbdelrahmanDwedar\Selective\BloomFilterService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

class BloomUnique implements ValidationRule
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
     * @var mixed
     */
    protected $ignoreId = null;

    /**
     * @var string
     */
    protected $idColumn = 'id';

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
     * Ignore the given ID during the unique check.
     *
     * @param  mixed  $id
     * @return $this
     */
    public function ignore($id, string $idColumn = 'id'): self
    {
        $this->ignoreId = $id;
        $this->idColumn = $idColumn;

        return $this;
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

        // Check if it might exist in the bloom filter
        if ($service->exists($bloomKey, (string) $value)) {
            // It might exist, so we must check the database to be sure
            $query = DB::table($this->table)->where($column, $value);

            if ($this->ignoreId !== null) {
                $query->where($this->idColumn, '!=', $this->ignoreId);
            }

            if ($query->exists()) {
                $fail('The :attribute has already been taken.');
            }
        }
        // If it doesn't exist in the bloom filter, it definitely doesn't exist in the DB (assuming sync is working)
        // So validation passes automatically.
    }
}
