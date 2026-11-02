<?php

namespace App\Services;

use App\Models\User;
use App\Support\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 9: where profile photos are kept: storage/app/private/photos. That folder can't be
 * opened from the web; PhotoController shows a photo only to people allowed to see it.
 */
class Photos
{
    public const DISK = 'local';

    /** Saves an uploaded photo as a small square JPEG and returns where it is */
    public static function store(User $user, UploadedFile $file): string
    {
        $name = $user->id.'-'.Str::random(20);
        $path = $file->getRealPath();

        if (Photo::canResize()) {
            self::roomForBigPhotos();
            $jpeg = Photo::square((string) file_get_contents($path), Photo::orientation($path));
            if ($jpeg !== null) {
                Storage::disk(self::DISK)->put('photos/'.$name.'.jpg', $jpeg);

                return 'photos/'.$name.'.jpg';
            }
        }

        // PHP without the GD extension: keep the photo as it was uploaded
        return Storage::disk(self::DISK)->putFileAs('photos', $file, $name.'.'.($file->extension() ?: 'jpg'));
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Opening a 12-megapixel phone photo takes about 50 MB of memory: allow up to 512 MB for this request */
    private static function roomForBigPhotos(): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }
        $megabytes = match (strtoupper(substr($limit, -1))) {
            'G' => (int) $limit * 1024,
            'M' => (int) $limit,
            'K' => intdiv((int) $limit, 1024),
            default => intdiv((int) $limit, 1024 * 1024),
        };
        if ($megabytes < 512) {
            ini_set('memory_limit', '512M');
        }
    }
}
