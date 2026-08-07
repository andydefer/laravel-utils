<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Models;

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\LaravelUtils\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\LaravelUtils\Tests\Fixtures\ValueObjects\TestSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'status',
        'role',
        'grade',
        'slug',
        'metadata',
    ];

    protected $casts = [
        'status' => TestUserStatus::class,
        'role' => TestUserRole::class,
        'grade' => TestUserGrade::class,
        'metadata' => 'array',
    ];

    protected function slug(): Attribute
    {
        return AttributeProxy::required(TestSlug::class);
    }

    protected function userRecord(): Attribute
    {
        return AttributeProxy::nullable(TestUserRecord::class, column: 'metadata');
    }
}
