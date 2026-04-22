<?php
// ID card photos uploaded from phones can be 4-5 MB. Displayed at ~400px wide,
// that's 10-25x more data than needed. Resize to a sane max width and recompress
// as JPEG before storing — cuts blob size drastically, which speeds up every
// read and keeps the DB light.

/**
 * Read an uploaded image, resize to at most $maxWidth px wide (preserving
 * aspect ratio), and return recompressed JPEG bytes + mime.
 *
 * @throws RuntimeException on invalid/corrupt image
 */
function compressUploadedImage(string $tmpPath, int $maxWidth = 1200, int $quality = 85): array {
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }
    [$origW, $origH] = $info;

    $src = @imagecreatefromstring(file_get_contents($tmpPath));
    if ($src === false) {
        throw new RuntimeException('Failed to decode image.');
    }

    // Only shrink — never upscale. Originals smaller than $maxWidth stay as-is
    // (re-encoded to JPEG at chosen quality, which still usually shrinks them).
    if ($origW > $maxWidth) {
        $newH = (int) round($origH * ($maxWidth / $origW));
        $resized = imagescale($src, $maxWidth, $newH);
        imagedestroy($src);
        $src = $resized;
    }

    // JPEG has no alpha — flatten any PNG transparency onto white so ID cards
    // with transparent corners don't render with black bands.
    $w = imagesx($src); $h = imagesy($src);
    $flat = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($flat, 255, 255, 255);
    imagefilledrectangle($flat, 0, 0, $w, $h, $white);
    imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);

    ob_start();
    imagejpeg($flat, null, $quality);
    $bytes = ob_get_clean();
    imagedestroy($flat);

    return ['data' => $bytes, 'mime' => 'image/jpeg'];
}
