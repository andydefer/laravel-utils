<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Proxies;

use AndyDefer\DomainStructures\Interfaces\Transformable;
use InvalidArgumentException;

final class TransformableProxy
{
    /**
     * @template T of Transformable
     *
     * @param  class-string<T>  $class
     * @return T|null
     *
     * @throws InvalidArgumentException
     */
    public static function make(string $class, mixed $value, bool $nullable = false): mixed
    {
        if ($value === null && $nullable) {
            return null;
        }

        if ($value === null && ! $nullable) {
            throw new InvalidArgumentException(sprintf(
                'Value cannot be null for %s. Use nullable=true if null is allowed.',
                $class
            ));
        }

        if (! is_subclass_of($class, Transformable::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement Transformable interface.',
                $class
            ));
        }

        if (is_string($value) && self::isJson($value)) {
            return $class::fromJson($value);
        }

        return $class::from($value);
    }

    private static function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
