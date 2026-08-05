<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Proxies;

use AndyDefer\LaravelUtils\Tests\Fixtures\Models\TestModel;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class TransformableProxyTest extends IntegrationTestCase
{
    use RefreshDatabase;

    public function test_attribute_creates_slug_vo_from_string(): void
    {
        $model = TestModel::create([
            'slug' => 'my-awesome-article',
        ]);

        $this->assertInstanceOf(SlugVO::class, $model->slug);
        $this->assertSame('my-awesome-article', $model->slug->getValue());
    }

    public function test_attribute_creates_coordinates_vo_from_json(): void
    {
        $model = TestModel::create([
            'coordinates' => json_encode(['latitude' => 48.8566, 'longitude' => 2.3522]),
        ]);

        $result = $model->coordinates;
        $this->assertInstanceOf(CoordinatesVO::class, $result);
        $this->assertSame(48.8566, $result->getValue()->latitude);
        $this->assertSame(2.3522, $result->getValue()->longitude);
    }

    public function test_attribute_creates_coordinates_vo_from_array(): void
    {
        $model = TestModel::create([
            'coordinates' => ['latitude' => 48.8566, 'longitude' => 2.3522],
        ]);

        $result = $model->coordinates;
        $this->assertInstanceOf(CoordinatesVO::class, $result);
        $this->assertSame(48.8566, $result->getValue()->latitude);
        $this->assertSame(2.3522, $result->getValue()->longitude);
    }

    public function test_attribute_returns_null_when_nullable_and_value_null(): void
    {
        $model = new TestModel;
        $model->nullable_coordinates = null;
        $model->save();

        $result = $model->nullable_coordinates;
        $this->assertNull($result);
    }

    public function test_attribute_uses_custom_column_name(): void
    {
        $model = TestModel::create([
            'custom_coordinates' => json_encode(['latitude' => 48.8566, 'longitude' => 2.3522]),
        ]);

        $result = $model->custom_coordinates;
        $this->assertInstanceOf(CoordinatesVO::class, $result);
        $this->assertSame(48.8566, $result->getValue()->latitude);
        $this->assertSame(2.3522, $result->getValue()->longitude);
    }

    public function test_attribute_returns_null_when_value_null(): void
    {
        $model = new TestModel;
        $model->slug = null;
        $model->save();

        $this->assertNull($model->slug);
    }

    public function test_attribute_works_with_eloquent_find(): void
    {
        $created = TestModel::create([
            'slug' => 'test-article',
            'coordinates' => json_encode(['latitude' => 40.7128, 'longitude' => -74.0060]),
        ]);

        $found = TestModel::find($created->id);

        $this->assertInstanceOf(SlugVO::class, $found->slug);
        $this->assertSame('test-article', $found->slug->getValue());

        $this->assertInstanceOf(CoordinatesVO::class, $found->coordinates);
        $this->assertSame(40.7128, $found->coordinates->getValue()->latitude);
        $this->assertSame(-74.0060, $found->coordinates->getValue()->longitude);
    }

    public function test_attribute_works_with_eloquent_update(): void
    {
        $model = TestModel::create([
            'slug' => 'old-title',
        ]);

        $model->update([
            'slug' => 'new-title',
        ]);

        $this->assertSame('new-title', $model->slug->getValue());
    }

    public function test_attribute_throws_exception_for_invalid_coordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitude must be between -90.0 and 90.0, got 100.000000');

        TestModel::create([
            'coordinates' => ['latitude' => 100, 'longitude' => 0],
        ]);
    }
}
