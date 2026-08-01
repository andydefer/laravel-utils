<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Unit\Proxies;

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\LaravelUtils\Proxies\TransformableProxy;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransformableProxyTest extends TestCase
{
    // ============================================================
    // TransformableProxy Tests
    // ============================================================

    public function test_make_with_string(): void
    {
        $result = TransformableProxy::make(SlugVO::class, 'mon-slug');
        $this->assertInstanceOf(SlugVO::class, $result);
        $this->assertSame('mon-slug', $result->value);
    }

    public function test_make_with_json(): void
    {
        $result = TransformableProxy::make(
            CoordinatesVO::class,
            '{"latitude": 48.8566, "longitude": 2.3522}'
        );
        $this->assertInstanceOf(CoordinatesVO::class, $result);
        $this->assertSame(48.8566, $result->latitude);
        $this->assertSame(2.3522, $result->longitude);
    }

    public function test_make_with_array(): void
    {
        $result = TransformableProxy::make(
            CoordinatesVO::class,
            ['latitude' => 48.8566, 'longitude' => 2.3522]
        );
        $this->assertInstanceOf(CoordinatesVO::class, $result);
        $this->assertSame(48.8566, $result->latitude);
        $this->assertSame(2.3522, $result->longitude);
    }

    public function test_make_with_nullable(): void
    {
        $result = TransformableProxy::make(CoordinatesVO::class, null, nullable: true);
        $this->assertNull($result);
    }

    public function test_make_with_nullable_false_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value cannot be null for CoordinatesVO');
        TransformableProxy::make(CoordinatesVO::class, null);
    }

    public function test_make_with_non_transformable_class_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass must implement Transformable interface');
        TransformableProxy::make(\stdClass::class, 'test');
    }

    // ============================================================
    // AttributeProxy Tests
    // ============================================================

    public function test_attribute_make(): void
    {
        $attribute = AttributeProxy::make(SlugVO::class);
        $this->assertInstanceOf(Attribute::class, $attribute);

        // Test get
        $result = $attribute->get(null, ['slug' => 'mon-slug']);
        $this->assertInstanceOf(SlugVO::class, $result);
        $this->assertSame('mon-slug', $result->value);
    }

    public function test_attribute_make_with_nullable(): void
    {
        $attribute = AttributeProxy::nullable(CoordinatesVO::class);
        $this->assertInstanceOf(Attribute::class, $attribute);

        $result = $attribute->get(null, ['coordinates' => null]);
        $this->assertNull($result);
    }

    public function test_attribute_make_with_custom_column(): void
    {
        $attribute = AttributeProxy::make(SlugVO::class, column: 'slug_column');
        $this->assertInstanceOf(Attribute::class, $attribute);

        $result = $attribute->get(null, ['slug_column' => 'mon-slug']);
        $this->assertInstanceOf(SlugVO::class, $result);
        $this->assertSame('mon-slug', $result->value);
    }

    public function test_attribute_make_with_non_transformable_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class stdClass must implement Transformable interface');
        AttributeProxy::make(\stdClass::class);
    }
}
