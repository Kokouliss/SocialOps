<?php

namespace App\Media;

use RuntimeException;

/**
 * Burns the design text onto a photo with GD: first line as the headline, second line as the support line,
 * white on a dark band at the bottom, so a post can go out by hand without a Canva round trip.
 */
class TextOverlay
{
    private const FONT = 'resources/fonts/InterTight-Bold.ttf';

    /**
     * @return string JPEG bytes, same size as the source
     *
     * @throws RuntimeException when GD cannot read the image
     */
    public static function render(string $imageBytes, string $text): string
    {
        // shortcut: a 20+ MP photo can exceed memory_limit, downscale before rendering if that happens
        $img = @imagecreatefromstring($imageBytes) ?: throw new RuntimeException('Not an image GD can read');
        imagepalettetotruecolor($img);
        [$w, $h] = [imagesx($img), imagesy($img)];
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        $font = base_path(self::FONT);
        $margin = (int) ($w * 0.06);
        $size = $w * 0.045;
        $headline = self::wrap($lines[0] ?? '', $font, $size, $w - 2 * $margin);
        $support = isset($lines[1]) ? self::wrap($lines[1], $font, $size / 2, $w - 2 * $margin) : [];
        $block = count($headline) * $size * 1.6 + count($support) * $size * 0.85;

        // the band fades from transparent to 70% black over the text area
        $bandTop = max(0, (int) ($h - $block - 2 * $margin - $size * 2));
        for ($y = $bandTop; $y < $h; $y++) {
            $alpha = 127 - (int) (min(1, ($y - $bandTop) / max(1, $h - $bandTop - $margin)) * 90);
            imageline($img, 0, $y, $w, $y, imagecolorallocatealpha($img, 0, 0, 0, $alpha));
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $y = $h - $margin - $block + $size * 1.3;
        foreach ($headline as $line) {
            imagettftext($img, $size, 0, $margin, (int) $y, $white, $font, $line);
            $y += $size * 1.6;
        }
        $y -= $size * 0.45;
        foreach ($support as $line) {
            imagettftext($img, $size / 2, 0, $margin, (int) $y, $white, $font, $line);
            $y += $size * 0.85;
        }

        ob_start();
        imagejpeg($img, null, 90);

        return ob_get_clean();
    }

    /** @return list<string> */
    private static function wrap(string $text, string $font, float $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $line === '' ? $word : "{$line} {$word}";
            $box = imagettfbbox($size, 0, $font, $try);
            if ($line !== '' && $maxWidth < $box[2] - $box[0]) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }

        return [...$lines, $line];
    }
}
