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
     * @template T of Transformable
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
     * @template T of Transformable
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
     * @template T of Transformable
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
     * Validate that the class implements Transformable interface.
     *
     * @param  class-string  $class
     *
     * @throws InvalidArgumentException
     */
    private static function validateClass(string $class): void
    {
        if (! is_subclass_of($class, Transformable::class)) {
            throw new InvalidArgumentException(sprintf(
                'Class %s must implement Transformable interface.',
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
        // Si pas de colonne, pas de set
        if ($column === null) {
            return null;
        }

        // Si l'utilisateur a défini un set personnalisé
        if ($set !== null) {
            return $set;
        }

        // Set par défaut
        return function ($value) use ($class, $column) {
            if ($value === null) {
                return [$column => null];
            }

            $transformed = $class::from($value);
            $normalized = NormalizerChain::get()->normalize($transformed);

            if (is_array($normalized) || is_object($normalized)) {
                return [$column => json_encode($normalized)];
            }

            return [$column => $normalized];
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
}
