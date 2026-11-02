<?php

namespace Tests\Unit;

use App\Support\Photo;
use GdImage;
use PHPUnit\Framework\TestCase;

class PhotoTest extends TestCase
{
    protected function setUp(): void
    {
        if (! Photo::canResize()) {
            $this->markTestSkipped('PHP has no GD extension here');
        }
    }

    /** A wide PNG: red on the left half, blue on the right half */
    private function redLeftBlueRight(int $width = 400, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** 'red', 'blue' or 'other' at a point of a saved photo */
    private function colorAt(GdImage $image, int $x, int $y): string
    {
        $rgb = imagecolorat($image, $x, $y);
        [$r, $b] = [($rgb >> 16) & 255, $rgb & 255];

        return match (true) {
            $r > 200 && $b < 60 => 'red',
            $b > 200 && $r < 60 => 'blue',
            default => 'other',
        };
    }

    private function saved(int $orientation): GdImage
    {
        $jpeg = Photo::square($this->redLeftBlueRight(), $orientation);
        $this->assertNotNull($jpeg);
        $this->assertSame([Photo::SIZE, Photo::SIZE, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($jpeg), 0, 3));

        return imagecreatefromstring($jpeg);
    }

    public function test_a_wide_photo_becomes_its_middle_square(): void
    {
        $image = $this->saved(1);

        // The middle square of red|blue is still half red, half blue
        $this->assertSame('red', $this->colorAt($image, 40, 160));
        $this->assertSame('blue', $this->colorAt($image, 280, 160));
    }

    public function test_sideways_phone_photos_are_turned_upright(): void
    {
        $turnedRight = $this->saved(6);     // "turn 90 degrees clockwise": the left side ends up on top
        $this->assertSame('red', $this->colorAt($turnedRight, 160, 40));
        $this->assertSame('blue', $this->colorAt($turnedRight, 160, 280));

        $turnedLeft = $this->saved(8);      // "turn 90 degrees counter-clockwise": the left side ends up at the bottom
        $this->assertSame('blue', $this->colorAt($turnedLeft, 160, 40));
        $this->assertSame('red', $this->colorAt($turnedLeft, 160, 280));

        $upsideDown = $this->saved(3);
        $this->assertSame('blue', $this->colorAt($upsideDown, 40, 160));
        $this->assertSame('red', $this->colorAt($upsideDown, 280, 160));

        $mirrored = $this->saved(2);
        $this->assertSame('blue', $this->colorAt($mirrored, 40, 160));
    }

    public function test_files_that_are_not_images_are_refused(): void
    {
        $this->assertNull(Photo::square('this is not a picture'));
    }

    public function test_photos_without_an_orientation_note_count_as_upright(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($file, $this->redLeftBlueRight());

        $this->assertSame(1, Photo::orientation($file));     // a PNG has no note
        unlink($file);
    }
}
