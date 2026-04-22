<?php
// ID card image upload helper.
//
// The real preprocessing (center-square crop + resize to 1200x1200 +
// JPEG recompression) happens client-side in assets/js/id-card-resize.js
// before the file is POSTed. That means the server only needs to:
//   1. Verify the upload is actually an image (cheap signature check).
//   2. Return the raw bytes + mime for storage as a BLOB.
//
// No GD / Imagick / any PHP extension required — local XAMPP works out
// of the box, and Fly.io's PHP image doesn't need extra packages either.
//
// Trade-off: users who disable JavaScript bypass the client resize and
// upload the raw file untouched. The 3 MB file-size cap in register.php
// and update_id_card.php keeps that bounded, and allowed mime types are
// validated there too — so even a raw pass-through stays safe.

/**
 * Validate an uploaded image and return its bytes + mime.
 *
 * @throws RuntimeException on non-image / corrupt input
 */
function compressUploadedImage(string $tmpPath, int $targetSide = 1200, int $quality = 85): array {
    // $targetSide and $quality are kept in the signature for compatibility
    // with callers, but the actual resize happens in the browser.

    $info = @getimagesize($tmpPath);
    if ($info === false) {
        throw new RuntimeException('Uploaded file is not a valid image.');
    }

    $bytes = file_get_contents($tmpPath);
    if ($bytes === false) {
        throw new RuntimeException('Could not read the uploaded file.');
    }

    // $info['mime'] is derived from actual file content (magic bytes), not
    // from the client-supplied $_FILES[...]['type'], so it's trustworthy.
    return ['data' => $bytes, 'mime' => $info['mime']];
}
