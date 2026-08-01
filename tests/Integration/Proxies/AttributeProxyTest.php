<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Proxies;

use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserGrade;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\LaravelUtils\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\LaravelUtils\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelUtils\Tests\Fixtures\Records\TestUserRecord;
use AndyDefer\LaravelUtils\Tests\Fixtures\ValueObjects\TestSlug;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

final class AttributeProxyTest extends IntegrationTestCase
{
    use RefreshDatabase;

    // ============================================================
    // SLUG TESTS (Value Object)
    // ============================================================

    public function test_slug_attribute_returns_test_slug_vo(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => 'john-doe',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestSlug::class, $fresh->slug);
        $this->assertSame('john-doe', $fresh->slug->value);
    }

    public function test_slug_attribute_persists_to_database(): void
    {
        $slug = new TestSlug('john-doe');

        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => $slug,
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestSlug::class, $fresh->slug);
        $this->assertSame('john-doe', $fresh->slug->value);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'slug' => 'john-doe',
        ]);
    }

    public function test_update_slug_attribute(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => 'john-doe',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
        ]);

        $user->slug = new TestSlug('john-doe-updated');
        $user->save();

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestSlug::class, $fresh->slug);
        $this->assertSame('john-doe-updated', $fresh->slug->value);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'slug' => 'john-doe-updated',
        ]);
    }

    // ============================================================
    // RECORD TESTS
    // ============================================================

    public function test_user_record_attribute_returns_test_user_record(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
            'metadata' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'role' => 'admin',
                'grade' => 1,
            ],
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestUserRecord::class, $fresh->userRecord);
        $this->assertSame('John Doe', $fresh->userRecord->name);
        $this->assertSame('john@example.com', $fresh->userRecord->email);
        $this->assertEquals(TestUserStatus::ACTIVE, $fresh->userRecord->status);
        $this->assertEquals(TestUserRole::ADMIN, $fresh->userRecord->role);
        $this->assertEquals(TestUserGrade::BASIC, $fresh->userRecord->grade);
    }

    public function test_user_record_attribute_persists_from_record(): void
    {
        $record = TransformableProxy::make(
            TestUserRecord::class,
            [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'status' => 'active',
                'role' => 'doctor',
                'grade' => 2,
            ]
        );

        $user = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 'active',
            'role' => 'doctor',
            'grade' => 2,
            'metadata' => $record,
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestUserRecord::class, $fresh->userRecord);
        $this->assertSame('Jane Doe', $fresh->userRecord->name);
        $this->assertSame('jane@example.com', $fresh->userRecord->email);
        $this->assertEquals(TestUserStatus::ACTIVE, $fresh->userRecord->status);
        $this->assertEquals(TestUserRole::DOCTOR, $fresh->userRecord->role);
        $this->assertEquals(TestUserGrade::PREMIUM, $fresh->userRecord->grade);
        $this->assertDatabaseHas('test_users', [
            'id' => $user->id,
            'metadata' => '{"name":"Jane Doe","email":"jane@example.com","status":"active","role":"doctor","grade":2}',
        ]);
    }

    public function test_user_record_attribute_returns_null_when_metadata_null(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
            'metadata' => null,
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertNull($fresh->userRecord);
    }

    public function test_update_record_attribute(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
            'metadata' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'role' => 'admin',
                'grade' => 1,
            ],
        ]);

        $record = TransformableProxy::make(
            TestUserRecord::class,
            [
                'name' => 'John Smith',
                'email' => 'johnsmith@example.com',
                'status' => 'inactive',
                'role' => 'user',
                'grade' => 3,
            ]
        );

        $user->metadata = $record;
        $user->save();

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestUserRecord::class, $fresh->userRecord);
        $this->assertSame('John Smith', $fresh->userRecord->name);
        $this->assertSame('johnsmith@example.com', $fresh->userRecord->email);
        $this->assertEquals(TestUserStatus::INACTIVE, $fresh->userRecord->status);
        $this->assertEquals(TestUserRole::USER, $fresh->userRecord->role);
        $this->assertEquals(TestUserGrade::VIP, $fresh->userRecord->grade);
    }

    // ============================================================
    // EDGE CASES
    // ============================================================

    public function test_attribute_with_empty_string_handled_correctly(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'slug' => '',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestSlug::class, $fresh->slug);
        $this->assertSame('', $fresh->slug->value);
    }

    public function test_attribute_with_empty_array_returns_record_with_defaults(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'grade' => 1,
            'metadata' => [],
        ]);

        $fresh = TestUser::find($user->id);

        $this->assertInstanceOf(TestUserRecord::class, $fresh->userRecord);
        $this->assertNull($fresh->userRecord->name);
        $this->assertNull($fresh->userRecord->email);
        $this->assertNull($fresh->userRecord->status);
        $this->assertNull($fresh->userRecord->role);
        $this->assertNull($fresh->userRecord->grade);
    }

    public function test_attribute_with_invalid_json_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert array to string for parameter $value');

        TransformableProxy::make(TestSlug::class, [
            'value' => ['hello'],
        ]);
    }

    // ============================================================
    // TRANSFORMABLE PROXY TESTS
    // ============================================================

    public function test_transformable_proxy_make_with_string(): void
    {
        $slug = TransformableProxy::make(TestSlug::class, 'john-doe');

        $this->assertInstanceOf(TestSlug::class, $slug);
        $this->assertSame('john-doe', $slug->getValue());
    }

    public function test_transformable_proxy_make_with_array(): void
    {
        $record = TransformableProxy::make(
            TestUserRecord::class,
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'role' => 'admin',
                'grade' => 1,
            ]
        );

        $this->assertInstanceOf(TestUserRecord::class, $record);
        $this->assertSame('John Doe', $record->name);
        $this->assertSame('john@example.com', $record->email);
        $this->assertEquals(TestUserStatus::ACTIVE, $record->status);
        $this->assertEquals(TestUserRole::ADMIN, $record->role);
        $this->assertEquals(TestUserGrade::BASIC, $record->grade);
    }

    public function test_transformable_proxy_make_with_nullable(): void
    {
        $result = TransformableProxy::make(TestUserRecord::class, null, nullable: true);
        $this->assertNull($result);
    }

    public function test_transformable_proxy_make_with_nullable_false_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value cannot be null for AndyDefer\LaravelUtils\Tests\Fixtures\Records\TestUserRecord');

        TransformableProxy::make(TestUserRecord::class, null);
    }

    public function test_transformable_proxy_make_with_non_transformable_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass must implement Transformable interface');

        TransformableProxy::make(\stdClass::class, 'test');
    }
}
