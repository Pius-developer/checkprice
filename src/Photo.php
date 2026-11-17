<?php
declare(strict_types=1);

/**
 * Saves an uploaded photo safely:
 *  - checks it really is an image (not just the file name)
 *  - turns it the right way up (phones store rotation separately)
 *  - shrinks it to at most 1200px and re-saves it as JPEG
 * Re-saving drops all hidden data in the original file, including the GPS location
 * that phones add to photos. That protects scouts' privacy.
 */
final class Photo
{
    public const MAX_BYTES = 6 * 1024 * 1024;

    /** @return array{ok: bool, name?: string, error?: string} */
    public static function store(array $upload, string $dir): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'name' => null];
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']) && !defined('PHOTO_TEST')) {
            return ['ok' => false, 'error' => 'The photo didn\'t upload. Try a smaller one.'];
        }
        if ($upload['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'That photo is too big (max 6 MB).'];
        }
        $info = @getimagesize($upload['tmp_name']);
        $type = $info[2] ?? null;
        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($upload['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($upload['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($upload['tmp_name']) : false,
            default        => false,
        };
        if (!$img) {
            return ['ok' => false, 'error' => 'Use a JPG, PNG or WebP photo.'];
        }

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($upload['tmp_name']);
            $img = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        }

        $w = imagesx($img); $h = imagesy($img);
        $scale = min(1, 1200 / max($w, $h));
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(12)) . '.jpg';
        imagejpeg($out, $dir . '/' . $name, 80);
        return ['ok' => true, 'name' => $name];
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}\.jpg$/', $name);
    }
}
