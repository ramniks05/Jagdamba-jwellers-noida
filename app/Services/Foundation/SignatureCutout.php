<?php

namespace App\Services\Foundation;

class SignatureCutout
{
    /**
     * PNG bytes of the ink only. Null when the picture has no dark signature to keep.
     */
    public function png(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagepng')) {
            return null;
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            return null;
        }

        $loaded = @imagecreatefromstring($binary);
        if ($loaded === false) {
            return null;
        }

        $source = $this->fit($loaded, 900);
        $width = imagesx($source);
        $height = imagesy($source);
        $background = $this->background($source, $width, $height);
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $clear);

        $minX = $width;
        $minY = $height;
        $maxX = 0;
        $maxY = 0;
        $ink = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($source, $x, $y);
                $red = ($pixel >> 16) & 255;
                $green = ($pixel >> 8) & 255;
                $blue = $pixel & 255;
                $existing = ($pixel >> 24) & 127;

                if ($existing > 110) {
                    continue;
                }

                $distance = $this->distance($red, $green, $blue, $background);
                if ($distance < 48 || ($red > 240 && $green > 240 && $blue > 240)) {
                    continue;
                }

                $alpha = $distance < 86
                    ? (int) round(127 - (($distance - 48) / 38) * 127)
                    : 0;
                $alpha = max(0, min(127, $alpha));
                imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, $red, $green, $blue, $alpha));

                if ($alpha < 70) {
                    $ink++;
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        imagedestroy($source);

        if ($ink < 20) {
            imagedestroy($canvas);

            return null;
        }

        $pad = 8;
        $cropX = max(0, $minX - $pad);
        $cropY = max(0, $minY - $pad);
        $cropW = min($width - $cropX, ($maxX - $minX + 1) + ($pad * 2));
        $cropH = min($height - $cropY, ($maxY - $minY + 1) + ($pad * 2));
        $cropped = imagecrop($canvas, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);
        imagedestroy($canvas);

        if ($cropped === false) {
            return null;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        ob_start();
        imagepng($cropped);
        imagedestroy($cropped);
        $png = ob_get_clean();

        return is_string($png) && $png !== '' ? $png : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function background(\GdImage $image, int $width, int $height): array
    {
        $red = 0;
        $green = 0;
        $blue = 0;
        $count = 0;

        foreach ([[1, 1], [$width - 2, 1], [1, $height - 2], [$width - 2, $height - 2]] as [$x, $y]) {
            $pixel = imagecolorat($image, max(0, min($width - 1, $x)), max(0, min($height - 1, $y)));
            $red += ($pixel >> 16) & 255;
            $green += ($pixel >> 8) & 255;
            $blue += $pixel & 255;
            $count++;
        }

        return [(int) round($red / $count), (int) round($green / $count), (int) round($blue / $count)];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $background
     */
    private function distance(int $red, int $green, int $blue, array $background): float
    {
        return sqrt(
            (($red - $background[0]) ** 2)
            + (($green - $background[1]) ** 2)
            + (($blue - $background[2]) ** 2)
        );
    }

    private function fit(\GdImage $image, int $max): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $max) {
            return $image;
        }

        $scale = $max / $longest;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $clear = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $clear);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }
}
