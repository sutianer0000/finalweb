<?php
// Server-side image preprocessing for ID card uploads.
//
// The normal path is client-side (assets/js/id-card-resize.js) — the browser
// crops to a centered square and shrinks to 1200x1200 before upload. This
// PHP helper is the fallback for clients without JS and the canonical
// "everything stored is 1200x1200 JPEG" guarantee — mirror the same behavior
// so the database never holds an unprocessed blob.

/**
 * Read an uploaded image, crop to a centered square, scale to $targetSide px,
 * and return recompressed JPEG bytes + mime.
 *
 * @throws RuntimeException on invalid/corrupt image
 */
function compressUploadedImage(string $tmpPath, int $targetSide = 1200, int $quality = 85): array {
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }
    [$origW, $origH] = $info;

    $src = @imagecreatefromstring(file_get_contents($tmpPath));
    if ($src === false) {
        throw new RuntimeException('Failed to decode image.');
    }

    // Center-square crop: the square's side is the shorter edge of the source.
    $side = min($origW, $origH);
    $srcX = (int) floor(($origW - $side) / 2);
    $srcY = (int) floor(($origH - $side) / 2);

    // Never upscale — if the source square is smaller than $targetSide,
    // keep its native resolution.
    $outSide = min($side, $targetSide);

    // Destination canvas is always square. White fill handles any edge bleed
    // and flattens PNG transparency to white (JPEG has no alpha).
    $dst = imagecreatetruecolor($outSide, $outSide);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $outSide, $outSide, $white);
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $outSide, $outSide, $side, $side);
    imagedestroy($src);

    ob_start();
    imagejpeg($dst, null, $quality);
    $bytes = ob_get_clean();
    imagedestroy($dst);

    return ['data' => $bytes, 'mime' => 'image/jpeg'];
}
