<?php

function getAllowedIdCardMimeTypes(): array {
    return ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
}

function processUploadedIdCardImage(string $tmpPath, string $label, int $targetWidth = 900, int $quality = 85): array {
    $info = @getimagesize($tmpPath);
    if ($info === false) {
        throw new RuntimeException($label . ' is not a valid image.');
    }

    $mime = $info['mime'] ?? '';
    if (!in_array($mime, getAllowedIdCardMimeTypes(), true)) {
        throw new RuntimeException($label . ' must be JPEG, PNG, GIF, or WEBP.');
    }

    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width < 900 || $height < 600) {
        throw new RuntimeException($label . ' is too small. Please upload a clearer image at least 900x600 pixels.');
    }

    if ($width * $height > 40000000) {
        throw new RuntimeException($label . ' is too large to process safely.');
    }

    $bytes = file_get_contents($tmpPath);
    if ($bytes === false) {
        throw new RuntimeException('Could not read ' . $label . '.');
    }

    $processed = maybeServerProcessIdCardImage($bytes, $mime, $targetWidth, $quality);
    if ($processed !== null) {
        return $processed;
    }

    return [
        'data' => $bytes,
        'mime' => $mime,
        'width' => $width,
        'height' => $height,
        'size_bytes' => strlen($bytes),
    ];
}

function getIdCardAspectRatio(): float {
    return 900 / 600;
}

function maybeServerProcessIdCardImage(string $bytes, string $mime, int $targetWidth, int $quality): ?array {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return null;
    }

    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return null;
    }

    $srcWidth = imagesx($src);
    $srcHeight = imagesy($src);
    $aspect = getIdCardAspectRatio();
    $cropWidth = $srcWidth;
    $cropHeight = (int) floor($srcWidth / $aspect);

    if ($cropHeight > $srcHeight) {
        $cropHeight = $srcHeight;
        $cropWidth = (int) floor($srcHeight * $aspect);
    }

    $srcX = (int) floor(($srcWidth - $cropWidth) / 2);
    $srcY = (int) floor(($srcHeight - $cropHeight) / 2);
    $targetHeight = (int) round($targetWidth / $aspect);
    $scale = min(1, $targetWidth / $cropWidth, $targetHeight / $cropHeight);
    $outWidth = max(1, (int) round($cropWidth * $scale));
    $outHeight = max(1, (int) round($cropHeight * $scale));

    $dst = imagecreatetruecolor($outWidth, $outHeight);
    imageinterlace($dst, true);
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $outWidth, $outHeight, $cropWidth, $cropHeight);

    ob_start();
    imagejpeg($dst, null, max(30, min(95, $quality)));
    $outBytes = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    if ($outBytes === false) {
        return null;
    }

    return [
        'data' => $outBytes,
        'mime' => 'image/jpeg',
        'width' => $outWidth,
        'height' => $outHeight,
        'size_bytes' => strlen($outBytes),
    ];
}

function getPostedOriginalIdCardDimensions(string $field, array $processedImage): array {
    $width = isset($_POST[$field . '_orig_width']) ? (int) $_POST[$field . '_orig_width'] : 0;
    $height = isset($_POST[$field . '_orig_height']) ? (int) $_POST[$field . '_orig_height'] : 0;

    if ($width <= 0 || $height <= 0) {
        return [
            'orig_width' => null,
            'orig_height' => null,
        ];
    }

    if ($width < (int) $processedImage['width'] || $height < (int) $processedImage['height']) {
        return [
            'orig_width' => null,
            'orig_height' => null,
        ];
    }

    if ($width * $height > 40000000) {
        return [
            'orig_width' => null,
            'orig_height' => null,
        ];
    }

    return [
        'orig_width' => $width,
        'orig_height' => $height,
    ];
}

function storeUserIdCardImages(PDO $db, int $userId, array $front, array $back): void {
    $stmt = $db->prepare("
        INSERT INTO user_id_cards (
            user_id,
            front_data,
            back_data,
            front_width,
            front_height,
            front_size_bytes,
            back_width,
            back_height,
            back_size_bytes,
            front_orig_width,
            front_orig_height,
            back_orig_width,
            back_orig_height
        )
        VALUES (
            :uid,
            :front_data,
            :back_data,
            :front_width,
            :front_height,
            :front_size_bytes,
            :back_width,
            :back_height,
            :back_size_bytes,
            :front_orig_width,
            :front_orig_height,
            :back_orig_width,
            :back_orig_height
        )
        ON DUPLICATE KEY UPDATE
            front_data = VALUES(front_data),
            back_data = VALUES(back_data),
            front_width = VALUES(front_width),
            front_height = VALUES(front_height),
            front_size_bytes = VALUES(front_size_bytes),
            back_width = VALUES(back_width),
            back_height = VALUES(back_height),
            back_size_bytes = VALUES(back_size_bytes),
            front_orig_width = VALUES(front_orig_width),
            front_orig_height = VALUES(front_orig_height),
            back_orig_width = VALUES(back_orig_width),
            back_orig_height = VALUES(back_orig_height)
    ");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':front_data', $front['data'], PDO::PARAM_LOB);
    $stmt->bindParam(':back_data', $back['data'], PDO::PARAM_LOB);
    $stmt->bindValue(':front_width', (int) $front['width'], PDO::PARAM_INT);
    $stmt->bindValue(':front_height', (int) $front['height'], PDO::PARAM_INT);
    $stmt->bindValue(':front_size_bytes', (int) $front['size_bytes'], PDO::PARAM_INT);
    $stmt->bindValue(':back_width', (int) $back['width'], PDO::PARAM_INT);
    $stmt->bindValue(':back_height', (int) $back['height'], PDO::PARAM_INT);
    $stmt->bindValue(':back_size_bytes', (int) $back['size_bytes'], PDO::PARAM_INT);
    $stmt->bindValue(':front_orig_width', $front['orig_width'], $front['orig_width'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':front_orig_height', $front['orig_height'], $front['orig_height'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':back_orig_width', $back['orig_width'], $back['orig_width'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':back_orig_height', $back['orig_height'], $back['orig_height'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->execute();
}
