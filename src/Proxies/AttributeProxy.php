<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Proxies;

use AndyDefer\DomainStructures\Interfaces\Transformable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;

final class AttributeProxy
{
    /**
     * @template T of Transformable
     *
     * @param  class-string<T>  $class
     * @return Attribute<T|null, never>
     */
    public static function make(string $class, bool $nullable = false, ?string $column = null): Attribute
    {
        if (! is_subclass_of($class, Transformable::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement Transformable interface.',
                $class
            ));
        }

        return Attribute::make(
            get: function ($value, $attributes) use ($class, $nullable, $column) {
                $rawValue = $column ? ($attributes[$column] ?? null) : $value;

                if ($rawValue === null && $nullable) {
                    return null;
                }

                return TransformableProxy::make($class, $rawValue, $nullable);
            }
        );
    }

    /**
     * @template T of Transformable
     *
     * @param  class-string<T>  $class
     * @return Attribute<T|null, never>
     */
    public static function nullable(string $class, ?string $column = null): Attribute
    {
        return self::make($class, true, $column);
    }
}
