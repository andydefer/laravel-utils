<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Enums;

enum ImageExtension: string
{
    case JPEG = 'jpeg';
    case JPG = 'jpg';
    case PNG = 'png';
    case WEBP = 'webp';
    case GIF = 'gif';
    case SVG = 'svg';
    case BMP = 'bmp';
    case TIFF = 'tiff';
    case ICO = 'ico';

    public function getLabel(): string
    {
        return match ($this) {
            self::JPEG => 'JPEG',
            self::JPG => 'JPG',
            self::PNG => 'PNG',
            self::WEBP => 'WebP',
            self::GIF => 'GIF',
            self::SVG => 'SVG',
            self::BMP => 'BMP',
            self::TIFF => 'TIFF',
            self::ICO => 'ICO',
        };
    }

    public function getMimeType(): ImageMimeType
    {
        return match ($this) {
            self::JPEG, self::JPG => ImageMimeType::JPEG,
            self::PNG => ImageMimeType::PNG,
            self::WEBP => ImageMimeType::WEBP,
            self::GIF => ImageMimeType::GIF,
            self::SVG => ImageMimeType::SVG,
            self::BMP => ImageMimeType::BMP,
            self::TIFF => ImageMimeType::TIFF,
            self::ICO => ImageMimeType::ICO,
        };
    }

    public function isSupported(): bool
    {
        return in_array($this, self::getSupportedExtensions(), true);
    }

    public static function getSupportedExtensions(): array
    {
        return [
            self::JPEG,
            self::JPG,
            self::PNG,
            self::WEBP,
            self::GIF,
            self::SVG,
        ];
    }

    public static function tryFromMimeType(ImageMimeType $mimeType): ?self
    {
        return match ($mimeType) {
            ImageMimeType::JPEG => self::JPEG,
            ImageMimeType::PNG => self::PNG,
            ImageMimeType::WEBP => self::WEBP,
            ImageMimeType::GIF => self::GIF,
            ImageMimeType::SVG => self::SVG,
            ImageMimeType::BMP => self::BMP,
            ImageMimeType::TIFF => self::TIFF,
            ImageMimeType::ICO => self::ICO,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
