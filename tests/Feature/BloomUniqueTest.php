<?php

use AbdelrahmanDwedar\Selective\Rules\BloomUnique;
use AbdelrahmanDwedar\Selective\BloomFilterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::flushall();
    
    // Create a users table for testing
    DB::statement('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT)');
    DB::table('users')->delete();
    
    $this->service = app(BloomFilterService::class);
});

it('passes when item is not in bloom filter', function () {
    $rule = new BloomUnique('users', 'email');
    
    $passed = true;
    $rule->validate('email', 'test@example.com', function ($message) use (&$passed) {
        $passed = false;
    });
    
    expect($passed)->toBeTrue();
});

it('fails when item is in bloom filter AND in database', function () {
    // Add to DB
    DB::table('users')->insert(['id' => 1, 'email' => 'test@example.com']);
    
    // Add to Bloom filter
    $this->service->add('users:email', 'test@example.com');
    
    $rule = new BloomUnique('users', 'email');
    
    $passed = true;
    $message = '';
    $rule->validate('email', 'test@example.com', function ($msg) use (&$passed, &$message) {
        $passed = false;
        $message = $msg;
    });
    
    expect($passed)->toBeFalse();
    expect($message)->toBe('The :attribute has already been taken.');
});

it('passes when item is in bloom filter but NOT in database (false positive)', function () {
    // Add to Bloom filter ONLY (simulating a false positive)
    $this->service->add('users:email', 'test@example.com');
    
    $rule = new BloomUnique('users', 'email');
    
    $passed = true;
    $rule->validate('email', 'test@example.com', function ($msg) use (&$passed) {
        $passed = false;
    });
    
    expect($passed)->toBeTrue();
});

it('can ignore a specific ID for updates', function () {
    // Add to DB
    DB::table('users')->insert(['id' => 1, 'email' => 'test@example.com']);
    
    // Add to Bloom filter
    $this->service->add('users:email', 'test@example.com');
    
    $rule = (new BloomUnique('users', 'email'))->ignore(1);
    
    $passed = true;
    $rule->validate('email', 'test@example.com', function ($msg) use (&$passed) {
        $passed = false;
    });
    
    // Should pass because we're ignoring ID 1
    expect($passed)->toBeTrue();
});
