<?php

use AbdelrahmanDwedar\Selective\BloomFilterService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Clear any existing redis data before tests
    Redis::flushall();
    
    // We instantiate the service fresh before each test
    $this->service = app(BloomFilterService::class);
    $this->testKey = 'test_filter';
});

it('can reserve a bloom filter', function () {
    $this->service->reserve($this->testKey, 0.01, 100);
    
    $info = $this->service->info($this->testKey);
    
    expect($info)->not->toBeEmpty();
    expect($info['Capacity'])->toBe(100);
});

it('can add an item to the bloom filter', function () {
    // With auto_reserve enabled by default, this should work without reserve
    $added = $this->service->add($this->testKey, 'test@example.com');
    
    expect($added)->toBeTrue();
    
    // Adding again should return false (already exists)
    $addedAgain = $this->service->add($this->testKey, 'test@example.com');
    expect($addedAgain)->toBeFalse();
});

it('can check if an item exists', function () {
    $this->service->add($this->testKey, 'test@example.com');
    
    expect($this->service->exists($this->testKey, 'test@example.com'))->toBeTrue();
    expect($this->service->exists($this->testKey, 'notfound@example.com'))->toBeFalse();
});

it('can add multiple items', function () {
    $results = $this->service->addMultiple($this->testKey, ['one@example.com', 'two@example.com']);
    
    expect($results)->toBe([true, true]);
    
    expect($this->service->exists($this->testKey, 'one@example.com'))->toBeTrue();
    expect($this->service->exists($this->testKey, 'two@example.com'))->toBeTrue();
});

it('can check multiple items existence', function () {
    $this->service->addMultiple($this->testKey, ['one@example.com', 'two@example.com']);
    
    $results = $this->service->existsMultiple($this->testKey, [
        'one@example.com', 
        'two@example.com', 
        'three@example.com'
    ]);
    
    expect($results)->toBe([true, true, false]);
});

it('can delete a bloom filter', function () {
    $this->service->add($this->testKey, 'test@example.com');
    
    // Check if key exists first using info()
    expect($this->service->info($this->testKey))->not->toBeEmpty();
    
    $deleted = $this->service->delete($this->testKey);
    expect($deleted)->toBeTrue();
    
    $info = $this->service->info($this->testKey);
    expect($info)->toBeEmpty();
});

it('handles redis exceptions gracefully when fallback is enabled', function () {
    // Mock the connection and the command
    $mockConnection = Mockery::mock(\Illuminate\Redis\Connections\Connection::class);
    $mockConnection->shouldReceive('executeRaw')->andThrow(new Exception('Redis is down'));
    
    Redis::shouldReceive('connection')->andReturn($mockConnection);
    Log::shouldReceive('warning')->once();
    
    // We can use the service from app() as it will now call Redis dynamically
    $service = app(BloomFilterService::class);
    
    // Shouldn't throw, should return false
    expect($service->exists($this->testKey, 'test@example.com'))->toBeFalse();
});
