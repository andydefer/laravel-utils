<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Models;

use AndyDefer\LaravelUtils\Proxies\AttributeProxy;
use AndyDefer\PhpVo\ValueObjects\CoordinatesVO;
use AndyDefer\PhpVo\ValueObjects\SlugVO;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property SlugVO|null $slug
 * @property CoordinatesVO|null $coordinates
 * @property CoordinatesVO|null $nullable_coordinates
 * @property CoordinatesVO|null $custom_coordinates
 */
class TestModel extends Model
{
    protected $table = 'test_models';

    protected $fillable = [
        'slug',
        'coordinates',
        'nullable_coordinates',
        'custom_coordinates',
    ];

    protected $casts = [
        'coordinates' => 'array',
        'nullable_coordinates' => 'array',
        'custom_coordinates' => 'array',
    ];

    protected function slug(): Attribute
    {
        return AttributeProxy::nullable(SlugVO::class, column: 'slug');
    }

    protected function coordinates(): Attribute
    {
        return AttributeProxy::required(CoordinatesVO::class, column: 'coordinates');
    }

    protected function nullableCoordinates(): Attribute
    {
        return AttributeProxy::nullable(CoordinatesVO::class, column: 'nullable_coordinates');
    }

    protected function customCoordinates(): Attribute
    {
        return AttributeProxy::required(CoordinatesVO::class, column: 'custom_coordinates');
    }
}
