<?php

namespace App\Services\Drawing\Concerns;

/**
 * Shrinks the selfie before it is billed for.
 *
 * Every image API charges input tokens on the resolution SENT, so a 4,000px
 * phone photograph is paid for in full and then discarded - the model only ever
 * needed the face. Shared because that is true of Gemini and OpenAI alike.
 */
trait ScalesInputImages
{
    /**
     * Scale `$contents` down to `provider.max_input_edge` on its long side.
     *
     * Best-effort by design: every failure path returns the original bytes. A
     * saving on input tokens is not worth refusing to render over, and this runs
     * on a photograph of somebody's face that they are waiting for.
     */
    private function downscaled(string $contents): string
    {
        $cap = (int) config('drawing.provider.max_input_edge', 0);

        if ($cap <= 0 || ! function_exists('imagecreatefromstring')) {
            return $contents;
        }

        $info = @getimagesizefromstring($contents);

        if ($info === false) {
            return $contents;
        }

        [$width, $height] = $info;

        if ($width <= $cap && $height <= $cap) {
            return $contents;
        }

        $scale = $cap / max($width, $height);
        $target = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return $contents;
        }

        try {
            $resized = imagescale($source, $target[0], $target[1]);

            if ($resized === false) {
                return $contents;
            }

            ob_start();
            $written = imagejpeg($resized, null, 90);
            $encoded = (string) ob_get_clean();

            imagedestroy($resized);

            return $written && $encoded !== '' ? $encoded : $contents;
        } finally {
            imagedestroy($source);
        }
    }
}
