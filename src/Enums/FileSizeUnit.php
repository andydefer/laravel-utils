<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Enums;

enum FileSizeUnit: string
{
    case BYTE = 'B';
    case KILOBYTE = 'KB';
    case MEGABYTE = 'MB';
    case GIGABYTE = 'GB';
    case TERABYTE = 'TB';
    case PETABYTE = 'PB';

    /**
     * Get the multiplier for this unit (bytes).
     */
    public function multiplier(): int
    {
        return match ($this) {
            self::BYTE => 1,
            self::KILOBYTE => 1024,
            self::MEGABYTE => 1024 ** 2,
            self::GIGABYTE => 1024 ** 3,
            self::TERABYTE => 1024 ** 4,
            self::PETABYTE => 1024 ** 5,
        };
    }

    /**
     * Format a size in bytes to the most appropriate unit.
     */
    public static function format(int $bytes, int $decimals = 2): string
    {
        $units = self::cases();

        foreach ($units as $index => $unit) {
            $threshold = $unit->multiplier();

            if ($bytes < $threshold * 1024 || $index === count($units) - 1) {
                $value = $bytes / $threshold;

                return number_format($value, $decimals).' '.$unit->value;
            }
        }

        return $bytes.' '.self::BYTE->value;
    }

    /**
     * Parse a formatted size string (e.g., "1.5 MB") to bytes.
     */
    public static function parse(string $formatted): int
    {
        $formatted = trim($formatted);
        $parts = preg_split('/\s+/', $formatted);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid size format: '.$formatted);
        }

        $value = (float) $parts[0];
        $unit = self::tryFrom(strtoupper($parts[1]));

        if ($unit === null) {
            throw new \InvalidArgumentException('Unknown unit: '.$parts[1]);
        }

        return (int) ($value * $unit->multiplier());
    }

    /**
     * Get all unit labels as an array.
     *
     * @return array<string>
     */
    public static function labels(): array
    {
        return array_column(self::cases(), 'value');
    }
}
