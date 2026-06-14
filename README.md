# Selective

Selective is a Laravel package that provides **super-fast, Redis bloom filter-backed validation rules** for `unique` and `exists` checks.

When you have millions of rows, running standard `unique:users` or `exists:users` validation on every request can be expensive, causing database strain and slowing down your application.

Selective solves this by adding a probabilistic data structure (a Bloom Filter) in front of your database. When validating, Selective checks the bloom filter in Redis first:
- If the bloom filter says the record **doesn't exist**, validation passes (or fails for `exists`) instantly without touching the database.
- If the bloom filter says the record **might exist**, Selective automatically falls back to standard database validation to confirm.

## Requirements

- PHP 8.2+
- Laravel 10.0+ or 11.0+
- **Redis with the RedisBloom module installed** (e.g. `redis-stack`)
- Predis or PhpRedis extension

## Installation

You can install the package via composer:

```bash
composer require abdelrahmandwedar/selective
```

Publish the config file:

```bash
php artisan vendor:publish --tag="selective-config"
```

## Usage

### Validation Rules

Use the rules exactly like you would use Laravel's standard rules.

#### BloomUnique

Validates that a given attribute does not exist in a database table.

```php
use AbdelrahmanDwedar\Selective\Rules\BloomUnique;

$request->validate([
    'email' => ['required', 'email', new BloomUnique('users', 'email')],
]);
```

Ignoring a given ID (e.g. during updates):
```php
new BloomUnique('users', 'email')->ignore($user->id)
```

#### BloomExists

Validates that a given attribute exists in a database table.

```php
use AbdelrahmanDwedar\Selective\Rules\BloomExists;

$request->validate([
    'email' => ['required', 'email', new BloomExists('users', 'email')],
]);
```

### Auto-Syncing with Eloquent

To keep your bloom filters automatically updated when you create or update records, use the `HasBloomFilters` trait on your Eloquent models:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AbdelrahmanDwedar\Selective\Traits\HasBloomFilters;

class User extends Model
{
    use HasBloomFilters;

    // Define which columns should be synced to bloom filters
    protected array $bloomFilters = ['email', 'username'];
}
```

*Note: Bloom filters do not support deletion. When a model is deleted, the item remains in the filter. This is fine because the fallback database check will handle the "false positive" when checking the deleted item.*

### Artisan Commands

#### Seed a Bloom Filter
If you are adding this package to an existing project, you need to populate the bloom filters with your existing data:

```bash
php artisan bloom:seed users email
```

You can use the `--fresh` option to delete any existing filter and recreate it, or `--error-rate` and `--capacity` to override defaults.

#### View Filter Status
View information about your bloom filters:

```bash
# Show info for a specific filter
php artisan bloom:status users email

# Show info for all selective filters
php artisan bloom:status
```

#### Clear Filters
Delete bloom filters:

```bash
# Clear a specific filter
php artisan bloom:clear users email

# Clear all selective filters
php artisan bloom:clear --all
```

## Configuration

You can configure the package in `config/selective.php`:

- `default_error_rate`: The acceptable probability of false positives (default: `0.01` or 1%).
- `default_capacity`: The default expected capacity of the bloom filter (default: `1000`).
- `redis_connection`: The Redis connection to use from `config/database.php` (default: `'default'`).
- `key_prefix`: Prefix for Redis keys (default: `'selective:'`).
- `fallback_on_error`: If true, gracefully falls back to DB validation if Redis is down (default: `true`).
- `auto_reserve`: Automatically create filters if they don't exist (default: `true`).

## How it works

1. You define a rule: `new BloomUnique('users', 'email')`.
2. A user submits an email.
3. Selective checks the Redis bloom filter `selective:users:email`.
4. If the filter returns `0` (definitely not in filter): validation passes instantly.
5. If the filter returns `1` (might be in filter): Selective queries the `users` table to confirm if it's a real hit or a false positive.
6. Validation passes or fails based on the DB result.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
