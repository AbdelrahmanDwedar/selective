<?php

use AbdelrahmanDwedar\Selective\BloomFilterService;
use AbdelrahmanDwedar\Selective\Traits\HasBloomFilters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class TestUserModel extends Model
{
    use HasBloomFilters;

    protected $table = 'test_users';

    protected $guarded = [];

    public $timestamps = false;

    protected array $bloomFilters = ['email', 'username'];
}

beforeEach(function () {
    Redis::flushall();

    Schema::create('test_users', function (Blueprint $table) {
        $table->id();
        $table->string('email');
        $table->string('username');
    });

    $this->service = app(BloomFilterService::class);
});

it('adds items to bloom filter on creation', function () {
    TestUserModel::create([
        'email' => 'test@example.com',
        'username' => 'testuser',
    ]);

    expect($this->service->exists('test_users:email', 'test@example.com'))->toBeTrue();
    expect($this->service->exists('test_users:username', 'testuser'))->toBeTrue();

    // Non-existent things shouldn't be there
    expect($this->service->exists('test_users:email', 'other@example.com'))->toBeFalse();
});

it('adds items to bloom filter on update only if changed', function () {
    $user = TestUserModel::create([
        'email' => 'test@example.com',
        'username' => 'testuser',
    ]);

    // Clear redis so we can track what happens during update
    Redis::flushall();

    // Update only username
    $user->update(['username' => 'newuser']);

    // New username should be added
    expect($this->service->exists('test_users:username', 'newuser'))->toBeTrue();

    // Email didn't change, so it shouldn't be added (redis was cleared, so it should be false)
    // Actually, exists might return false if the filter is completely empty or it might auto-reserve.
    // In our implementation, exists does NOT auto-reserve, it just checks. If the filter isn't there, it might fail.
    // Let's just check that it's false because it wasn't re-added.
    expect($this->service->exists('test_users:email', 'test@example.com'))->toBeFalse();
});

it('logs a debug message on deletion', function () {
    $user = TestUserModel::create([
        'email' => 'test@example.com',
        'username' => 'testuser',
    ]);

    Log::shouldReceive('debug')->once()->withArgs(function ($message) {
        return str_contains($message, 'bloom filters do not support item removal');
    });

    $user->delete();
});
