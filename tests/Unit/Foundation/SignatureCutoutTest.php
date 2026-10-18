<?php

namespace Tests\Unit\Foundation;

use App\Services\Foundation\SignatureCutout;
use App\Services\Foundation\SignaturePictureRejected;
use Tests\TestCase;

class SignatureCutoutTest extends TestCase
{
    public function test_white_paper_is_removed_and_the_ink_remains(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to cut a signature out of its paper.');
        }

        $source = imagecreatetruecolor(160, 80);
        $paper = imagecolorallocate($source, 255, 255, 255);
        $ink = imagecolorallocate($source, 18, 16, 20);
        imagefill($source, 0, 0, $paper);
        imagefilledellipse($source, 80, 40, 70, 16, $ink);
        $file = tempnam(sys_get_temp_dir(), 'sign');
        imagepng($source, $file);
        imagedestroy($source);

        $png = (new SignatureCutout)->png($file);
        @unlink($file);

        $this->assertNotNull($png);
        $result = imagecreatefromstring($png);
        $this->assertNotFalse($result);
        $corner = imagecolorat($result, 0, 0);
        $this->assertGreaterThan(100, ($corner >> 24) & 127);

        $dark = false;
        $width = imagesx($result);
        $height = imagesy($result);

        for ($y = 0; $y < $height && ! $dark; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($result, $x, $y);
                $alpha = ($pixel >> 24) & 127;
                $red = ($pixel >> 16) & 255;

                if ($alpha < 40 && $red < 80) {
                    $dark = true;
                    break;
                }
            }
        }

        imagedestroy($result);
        $this->assertTrue($dark);
    }

    public function test_a_cropped_or_transparent_picture_is_kept(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to cut a signature out of its paper.');
        }

        $source = imagecreatetruecolor(90, 36);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $clear = imagecolorallocatealpha($source, 0, 0, 0, 127);
        $ink = imagecolorallocatealpha($source, 12, 10, 14, 0);
        imagefilledrectangle($source, 0, 0, 89, 35, $clear);
        imagefilledellipse($source, 45, 18, 60, 14, $ink);
        $file = tempnam(sys_get_temp_dir(), 'crop');
        imagepng($source, $file);
        imagedestroy($source);

        $png = (new SignatureCutout)->png($file);
        @unlink($file);

        $this->assertNotNull($png);
        $result = imagecreatefromstring($png);
        $this->assertNotFalse($result);
        $dark = false;
        for ($y = 0; $y < imagesy($result) && ! $dark; $y++) {
            for ($x = 0; $x < imagesx($result); $x++) {
                $pixel = imagecolorat($result, $x, $y);
                if ((($pixel >> 24) & 127) < 40 && (($pixel >> 16) & 255) < 80) {
                    $dark = true;
                    break;
                }
            }
        }
        imagedestroy($result);
        $this->assertTrue($dark);
    }

    public function test_a_blank_photo_is_refused_instead_of_being_saved(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is required to cut a signature out of its paper.');
        }

        $source = imagecreatetruecolor(80, 40);
        imagefill($source, 0, 0, imagecolorallocate($source, 255, 255, 255));
        $file = tempnam(sys_get_temp_dir(), 'blank');
        imagepng($source, $file);
        imagedestroy($source);

        try {
            (new SignatureCutout)->png($file);
            $this->fail('A blank photo was accepted.');
        } catch (SignaturePictureRejected $exception) {
            $this->assertStringContainsString('No signature was found', $exception->getMessage());
        } finally {
            @unlink($file);
        }
    }
}
