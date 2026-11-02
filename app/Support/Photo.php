<?php

namespace App\Support;

use GdImage;

/**
 * Phase 9: profile photos are saved as a small square JPEG: the middle of the photo, turned
 * upright, without the hidden details phones add (like where it was taken). Needs PHP's GD
 * extension; without it the photo is kept as it was uploaded.
 */
final class Photo
{
    /** Width and height of a saved photo, in pixels */
    public const SIZE = 320;

    /** Bigger photos are refused (opening one takes about 4 bytes of memory per pixel) */
    public const MAX_PIXELS = 50_000_000;

    public static function canResize(): bool
    {
        return function_exists('imagecreatefromstring') && function_exists('imagejpeg');
    }

    /**
     * The middle square of an image, SIZE x SIZE, as JPEG bytes. $orientation is the photo's
     * EXIF orientation note (1 = already upright). Null when the image can't be read.
     */
    public static function square(string $bytes, int $orientation = 1, int $size = self::SIZE): ?string
    {
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor($size, $size);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));     // white behind see-through PNGs
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);
        unset($source);     // frees the big image's memory now

        // The middle square of a turned photo is the turned middle square, so turning the small one is enough
        $square = self::orient($square, $orientation);

        ob_start();
        imagejpeg($square, null, 85);

        return (string) ob_get_clean();
    }

    /** Phones often save photos sideways with a note saying how to turn them: this does the turning */
    public static function orient(GdImage $image, int $orientation): GdImage
    {
        // For each note: degrees to turn (counter-clockwise), then flip left-right? flip upside down?
        [$degrees, $mirror, $upsideDown] = match ($orientation) {
            2 => [0, true, false],
            3 => [180, false, false],
            4 => [0, false, true],
            5 => [-90, true, false],
            6 => [-90, false, false],
            7 => [90, true, false],
            8 => [90, false, false],
            default => [0, false, false],
        };

        if ($degrees !== 0) {
            $image = imagerotate($image, $degrees, 0) ?: $image;
        }
        if ($mirror) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        if ($upsideDown) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }

    /** The orientation note of a JPEG file (1 when there is none or it can't be read) */
    public static function orientation(string $path): int
    {
        if (! function_exists('exif_read_data') || @exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return 1;
        }
        $exif = @exif_read_data($path);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }
}
