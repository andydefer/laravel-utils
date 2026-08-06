<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Enums;

enum VideoExtension: string
{
    case MP4 = 'mp4';
    case AVI = 'avi';
    case MOV = 'mov';
    case MKV = 'mkv';
    case WMV = 'wmv';
    case FLV = 'flv';
    case WEBM = 'webm';
    case M4V = 'm4v';
    case MPG = 'mpg';
    case MPEG = 'mpeg';

    /**
     * Get all extension values as an array of strings.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given extension is valid.
     */
    public static function isValid(string $extension): bool
    {
        return in_array($extension, self::values(), true);
    }

    /**
     * Get the MIME type for a video extension.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::MP4 => 'video/mp4',
            self::AVI => 'video/x-msvideo',
            self::MOV => 'video/quicktime',
            self::MKV => 'video/x-matroska',
            self::WMV => 'video/x-ms-wmv',
            self::FLV => 'video/x-flv',
            self::WEBM => 'video/webm',
            self::M4V => 'video/x-m4v',
            self::MPG, self::MPEG => 'video/mpeg',
        };
    }

    /**
     * Get the default extension.
     */
    public static function default(): self
    {
        return self::MP4;
    }

    /**
     * Get all supported extensions for ffmpeg.
     *
     * @return array<string>
     */
    public static function ffmpegSupported(): array
    {
        return self::values();
    }
}
