<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Enums;

enum TestLanguage: string
{
    case FR = 'fr';
    case EN = 'en';
    case LN = 'ln';
    case SW = 'sw';
    case KG = 'kg';
    case LU = 'lu';

    public function label(): string
    {
        return match ($this) {
            self::FR => 'Français',
            self::EN => 'English',
            self::LN => 'Lingala',
            self::SW => 'Swahili',
            self::KG => 'Kikongo',
            self::LU => 'Tshiluba',
        };
    }

    public function nativeLabel(): string
    {
        return match ($this) {
            self::FR => 'Français',
            self::EN => 'English',
            self::LN => 'Lingála',
            self::SW => 'Kiswahili',
            self::KG => 'Kikongo',
            self::LU => 'Tshiluba',
        };
    }

    public function isSupported(): bool
    {
        return in_array($this, self::getSupportedLanguages(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function getSupportedLanguages(): array
    {
        return [self::FR, self::EN];
    }

    public static function getFallbackLanguage(): self
    {
        return self::FR;
    }

    public static function isSupportedLanguage(self $language): bool
    {
        return $language->isSupported();
    }
}
