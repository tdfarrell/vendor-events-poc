<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Image uploads (ported from the Nutmeg app's store_uploaded_image).
 *
 * Every image is decoded, rotated upright, shrunk to fit, and re-encoded as
 * JPEG. Re-encoding also strips anything that isn't image data, so nothing
 * uploaded is ever served as-is. Files go in the web folder's uploads/ folder,
 * which has its own .htaccess that refuses to run scripts.
 */
class Uploads
{
    public const MAX_BYTES = 12 * 1024 * 1024;

    public static function dir(): string
    {
        return public_path('uploads');
    }

    public static function url(?string $path): string
    {
        return $path ? asset('uploads/'.$path) : '';
    }

    /**
     * Stores an uploaded image. Returns [relative path, null] or [null, error].
     */
    public static function storeImage(?UploadedFile $file, string $subdir, int $maxDim): array
    {
        if (! $file) {
            return [null, null];
        }
        if (! $file->isValid()) {
            return [null, 'The upload didn’t finish. Try again with a smaller photo.'];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return [null, 'That photo is over 12 MB. Try a smaller one.'];
        }
        $info = @getimagesize($file->getRealPath());
        $type = $info[2] ?? null;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($file->getRealPath()),
            default => false,
        };
        if (! $src) {
            return [null, 'That file isn’t a photo we can use. Upload a JPG, PNG or WebP.'];
        }

        // Phones store rotation separately; apply it so the photo is upright.
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($angle) {
                $src = imagerotate($src, $angle, 0) ?: $src;
            }
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $maxDim / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency onto white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $rel = trim($subdir, '/').'/'.date('Y/m').'/'.Str::lower(Str::random(20)).'.jpg';
        $abs = self::dir().'/'.$rel;
        if (! is_dir(dirname($abs)) && ! @mkdir(dirname($abs), 0755, true) && ! is_dir(dirname($abs))) {
            return [null, 'Couldn’t save the photo on the server. Check that the uploads folder is writable.'];
        }
        imagejpeg($dst, $abs, 84);
        imagedestroy($src);
        imagedestroy($dst);

        return [$rel, null];
    }

    public static function delete(?string $rel): void
    {
        if (! $rel || str_contains($rel, '..')) {
            return;
        }
        $abs = self::dir().'/'.$rel;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
