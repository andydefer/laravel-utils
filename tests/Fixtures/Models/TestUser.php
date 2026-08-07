<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Models;

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\LaravelUtils\Tests\Fixtures\Collection\TestLanguageCollection;
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
        'languages',
        'metadata',
    ];

    protected $casts = [
        'status' => TestUserStatus::class,
        'role' => TestUserRole::class,
        'grade' => TestUserGrade::class,
        'languages' => 'array',
        'metadata' => 'array',
    ];

    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(TestSlug::class);
    }

    protected function userRecord(): Attribute
    {
        return AttributeProxy::nullable(TestUserRecord::class, column: 'metadata');
    }

    protected function languages(): Attribute
    {
        return AttributeProxy::nullable(TestLanguageCollection::class, column: 'languages');
    }
}
