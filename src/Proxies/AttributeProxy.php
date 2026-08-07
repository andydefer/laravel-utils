<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Proxies;

use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use Illuminate\Database\Eloquent\Casts\Attribute;
use InvalidArgumentException;

final class AttributeProxy
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  callable(mixed, array<string, mixed>): mixed|null  $get
     * @param  callable(mixed, mixed): array<string, mixed>|null  $set
     * @return Attribute<T, never>
     */
    public static function required(
        string $class,
        ?string $column = null,
        ?callable $get = null,
        ?callable $set = null
    ): Attribute {
        self::validateClass($class);

        $getCallback = self::buildGetCallback($class, $column, $get, false);
        $setCallback = self::buildSetCallback($class, $column, $set);

        return self::buildAttribute($getCallback, $setCallback);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  callable(mixed, array<string, mixed>): mixed|null  $get
     * @param  callable(mixed, mixed): array<string, mixed>|null  $set
     * @return Attribute<T|null, never>
     */
    public static function nullable(
        string $class,
        ?string $column = null,
        ?callable $get = null,
        ?callable $set = null
    ): Attribute {
        self::validateClass($class);

        $getCallback = self::buildGetCallback($class, $column, $get, true);
        $setCallback = self::buildSetCallback($class, $column, $set);

        return self::buildAttribute($getCallback, $setCallback);
    }

    /**
     * @deprecated Use required() or nullable() instead
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @param  callable(mixed, array<string, mixed>): mixed|null  $get
     * @param  callable(mixed, mixed): array<string, mixed>|null  $set
     * @return Attribute<T|null, never>
     */
    public static function make(
        string $class,
        bool $nullable = false,
        ?string $column = null,
        ?callable $get = null,
        ?callable $set = null
    ): Attribute {
        if ($nullable) {
            return self::nullable($class, $column, $get, $set);
        }

        return self::required($class, $column, $get, $set);
    }

    /**
     * Validate that the class is either Transformable or an Enum.
     *
     * @param  class-string  $class
     *
     * @throws InvalidArgumentException
     */
    private static function validateClass(string $class): void
    {
        $isTransformable = is_subclass_of($class, Transformable::class);
        $isEnum = is_subclass_of($class, \UnitEnum::class);

        if (! $isTransformable && ! $isEnum) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement Transformable interface or be an Enum.',
                $class
            ));
        }
    }

    /**
     * Build the get callback for the attribute.
     *
     * @param  class-string  $class
     * @param  callable(mixed, array<string, mixed>): mixed|null  $get
     * @return callable(mixed, array<string, mixed>): mixed
     */
    private static function buildGetCallback(
        string $class,
        ?string $column,
        ?callable $get,
        bool $nullable
    ): callable {
        return $get ?? function ($value, $attributes) use ($class, $column, $nullable) {
            $rawValue = $column ? ($attributes[$column] ?? null) : $value;

            if (is_string($rawValue) && self::isJson($rawValue)) {
                $rawValue = json_decode($rawValue, true);
            }

            if ($rawValue === null && $nullable) {
                return null;
            }

            if (is_subclass_of($class, \UnitEnum::class)) {
                return self::hydrateEnum($class, $rawValue);
            }

            return TransformableProxy::make($class, $rawValue, $nullable);
        };
    }

    /**
     * Build the set callback for the attribute.
     *
     * @param  class-string  $class
     * @param  callable(mixed, mixed): array<string, mixed>|null  $set
     * @return callable(mixed, mixed): array<string, mixed>|null
     */
    private static function buildSetCallback(
        string $class,
        ?string $column,
        ?callable $set
    ): ?callable {
        if ($column === null) {
            return null;
        }

        if ($set !== null) {
            return $set;
        }

        return function ($value) use ($class, $column) {
            if ($value === null) {
                return [$column => null];
            }

            if ($value instanceof \UnitEnum) {
                return [$column => $value->value];
            }

            if (is_subclass_of($class, Transformable::class)) {
                // ✅ D'abord, décoder le JSON si c'est une chaîne
                $rawValue = $value;
                if (is_string($rawValue) && self::isJson($rawValue)) {
                    $rawValue = json_decode($rawValue, true);
                }

                // ✅ Si c'est déjà un tableau ou une collection, on hydrate
                if (is_array($rawValue) || is_object($rawValue)) {
                    $transformed = $class::from($rawValue);
                    $normalized = NormalizerChain::get()->normalize($transformed);

                    if (is_array($normalized) || is_object($normalized)) {
                        return [$column => json_encode($normalized)];
                    }

                    return [$column => $normalized];
                }

                // ✅ Si c'est un scalaire, on le stocke tel quel
                return [$column => $rawValue];
            }

            if (is_array($value) || is_object($value)) {
                return [$column => json_encode($value)];
            }

            return [$column => $value];
        };
    }

    /**
     * Build the Attribute instance.
     *
     * @param  callable(mixed, array<string, mixed>): mixed  $getCallback
     * @param  callable(mixed, mixed): array<string, mixed>|null  $setCallback
     */
    private static function buildAttribute(callable $getCallback, ?callable $setCallback): Attribute
    {
        if ($setCallback === null) {
            return Attribute::make(get: $getCallback);
        }

        return Attribute::make(
            get: $getCallback,
            set: $setCallback
        );
    }

    /**
     * Check if a string is valid JSON.
     */
    private static function isJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Hydrate an enum from a value.
     *
     * @param  class-string<\UnitEnum>  $class
     *
     * @throws InvalidArgumentException
     */
    private static function hydrateEnum(string $class, mixed $value): \UnitEnum
    {
        if ($value instanceof $class) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            if (method_exists($class, 'from')) {
                try {
                    return $class::from($value);
                } catch (\ValueError) {
                }
            }

            if (method_exists($class, 'tryFrom')) {
                $enum = $class::tryFrom($value);
                if ($enum !== null) {
                    return $enum;
                }
            }
        }

        throw new InvalidArgumentException(
            sprintf('Invalid value "%s" for enum %s', $value, $class)
        );
    }
}
