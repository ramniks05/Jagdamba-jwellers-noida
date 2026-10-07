<?php

namespace Tests\Unit\Foundation;

use App\Services\Foundation\SignatureCutout;
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
}
