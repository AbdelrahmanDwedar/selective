# Changelog

All notable changes to `selective` will be documented in this file.

## 1.0.0 - 2026-06-15

### Added
- Core `BloomFilterService` to interact with Redis Bloom module
- `BloomUnique` validation rule as a drop-in replacement for Laravel's `unique`
- `BloomExists` validation rule as a drop-in replacement for Laravel's `exists`
- `HasBloomFilters` Eloquent trait for auto-syncing model events with bloom filters
- `bloom:seed` Artisan command to seed filters from existing database tables
- `bloom:status` Artisan command to inspect filter information
- `bloom:clear` Artisan command to delete filters
- Comprehensive configuration options for error rate, capacity, prefixes, and fail-safes
- Fallback mechanism to gracefully handle Redis downtime
