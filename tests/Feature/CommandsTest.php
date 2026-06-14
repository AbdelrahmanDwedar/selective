<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Redis;
use AbdelrahmanDwedar\Selective\BloomFilterService;

beforeEach(function () {
    Redis::flushall();
    
    Schema::create('test_users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
    });
    
    $this->service = app(BloomFilterService::class);
});

it('can seed a bloom filter from a database table', function () {
    DB::table('test_users')->insert([
        ['email' => 'one@example.com'],
        ['email' => 'two@example.com'],
        ['email' => 'three@example.com'],
    ]);
    
    $this->artisan('bloom:seed', [
        'table' => 'test_users',
        'column' => 'email'
    ])->assertSuccessful();
    
    expect($this->service->exists('test_users:email', 'one@example.com'))->toBeTrue();
    expect($this->service->exists('test_users:email', 'two@example.com'))->toBeTrue();
    expect($this->service->exists('test_users:email', 'three@example.com'))->toBeTrue();
    expect($this->service->exists('test_users:email', 'four@example.com'))->toBeFalse();
});

it('can clear a specific bloom filter', function () {
    $this->service->add('test_users:email', 'test@example.com');
    expect($this->service->exists('test_users:email', 'test@example.com'))->toBeTrue();
    
    $this->artisan('bloom:clear', [
        'table' => 'test_users',
        'column' => 'email'
    ])->expectsConfirmation("Are you sure you want to delete the bloom filter for 'test_users:email'?", 'yes')
      ->assertSuccessful();
      
    expect($this->service->info('test_users:email'))->toBeEmpty();
});

it('can clear all bloom filters', function () {
    $this->service->add('test_users:email', 'test@example.com');
    $this->service->add('test_users:username', 'testuser');
    
    $this->artisan('bloom:clear', ['--all' => true])
      ->expectsConfirmation("Are you sure you want to delete ALL of them? This cannot be undone.", 'yes')
      ->assertSuccessful();
      
    expect($this->service->info('test_users:email'))->toBeEmpty();
    expect($this->service->info('test_users:username'))->toBeEmpty();
});

it('can show status for a specific filter', function () {
    $this->service->reserve('test_users:email', 0.01, 100);
    
    $this->artisan('bloom:status', [
        'table' => 'test_users',
        'column' => 'email'
    ])->expectsOutput("Bloom filter info for: test_users:email")
      ->assertSuccessful();
});

it('can show status for all filters', function () {
    $this->service->reserve('test_users:email', 0.01, 100);
    $this->service->reserve('test_users:username', 0.01, 100);
    
    $this->artisan('bloom:status')
      ->expectsOutput("Found 2 bloom filter(s):")
      ->assertSuccessful();
});
