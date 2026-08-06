<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Enums;

enum ImageMimeType: string
{
    case JPEG = 'image/jpeg';
    case PNG = 'image/png';
    case WEBP = 'image/webp';
    case GIF = 'image/gif';
    case SVG = 'image/svg+xml';
    case BMP = 'image/bmp';
    case TIFF = 'image/tiff';
    case ICO = 'image/x-icon';

    public function getLabel(): string
    {
        return match ($this) {
            self::JPEG => 'JPEG',
            self::PNG => 'PNG',
            self::WEBP => 'WebP',
            self::GIF => 'GIF',
            self::SVG => 'SVG',
            self::BMP => 'BMP',
            self::TIFF => 'TIFF',
            self::ICO => 'ICO',
        };
    }

    public function getExtension(): ImageExtension
    {
        return match ($this) {
            self::JPEG => ImageExtension::JPEG,
            self::PNG => ImageExtension::PNG,
            self::WEBP => ImageExtension::WEBP,
            self::GIF => ImageExtension::GIF,
            self::SVG => ImageExtension::SVG,
            self::BMP => ImageExtension::BMP,
            self::TIFF => ImageExtension::TIFF,
            self::ICO => ImageExtension::ICO,
        };
    }

    public function isSupported(): bool
    {
        return in_array($this, self::getSupportedMimeTypes(), true);
    }

    public static function getSupportedMimeTypes(): array
    {
        return [
            self::JPEG,
            self::PNG,
            self::WEBP,
            self::GIF,
            self::SVG,
        ];
    }

    public static function tryFromExtension(ImageExtension $extension): ?self
    {
        return match ($extension) {
            ImageExtension::JPEG, ImageExtension::JPG => self::JPEG,
            ImageExtension::PNG => self::PNG,
            ImageExtension::WEBP => self::WEBP,
            ImageExtension::GIF => self::GIF,
            ImageExtension::SVG => self::SVG,
            ImageExtension::BMP => self::BMP,
            ImageExtension::TIFF => self::TIFF,
            ImageExtension::ICO => self::ICO,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
