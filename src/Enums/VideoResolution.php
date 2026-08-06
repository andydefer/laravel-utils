<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Enums;

enum VideoResolution: string
{
    case R144 = '144';
    case R240 = '240';
    case R360 = '360';
    case R480 = '480';
    case R720 = '720';
    case R1080 = '1080';
    case R1440 = '1440';
    case R2160 = '2160';

    /**
     * Get the height in pixels.
     */
    public function height(): int
    {
        return (int) $this->value;
    }

    /**
     * Get a label for the resolution (e.g., "720p").
     */
    public function label(): string
    {
        return $this->value.'p';
    }

    /**
     * Get all resolution values as an array of strings.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get default resolutions (up to 720p).
     *
     * @return array<string>
     */
    public static function defaults(): array
    {
        return [
            self::R144->value,
            self::R240->value,
            self::R360->value,
            self::R480->value,
            self::R720->value,
        ];
    }

    /**
     * Get HD resolutions (720p and above).
     *
     * @return array<string>
     */
    public static function hd(): array
    {
        return [
            self::R720->value,
            self::R1080->value,
            self::R1440->value,
            self::R2160->value,
        ];
    }

    /**
     * Get SD resolutions (below 720p).
     *
     * @return array<string>
     */
    public static function sd(): array
    {
        return [
            self::R144->value,
            self::R240->value,
            self::R360->value,
            self::R480->value,
        ];
    }

    /**
     * Create from integer height.
     */
    public static function fromHeight(int $height): ?self
    {
        return match ($height) {
            144 => self::R144,
            240 => self::R240,
            360 => self::R360,
            480 => self::R480,
            720 => self::R720,
            1080 => self::R1080,
            1440 => self::R1440,
            2160 => self::R2160,
            default => null,
        };
    }
}
