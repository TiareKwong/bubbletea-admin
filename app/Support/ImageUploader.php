<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageUploader
{
    /**
     * Max pixel dimension (width or height) after resize.
     * Images smaller than this are not upscaled.
     */
    const MAX_DIMENSION = 800;

    /**
     * JPEG output quality (0–100). 80 gives good quality at ~60–80% smaller file size.
     */
    const JPEG_QUALITY = 80;

    /**
     * Resize, compress, and upload an image to the Droplet via SFTP.
     * Always outputs JPEG regardless of input format.
     * Returns the full public URL saved to the database.
     */
    public static function upload(TemporaryUploadedFile $file, string $directory): string
    {
        $tmpPath  = $file->getRealPath();
        $mimeType = $file->getMimeType() ?? '';

        // ── Create GD image from uploaded file ──────────────────────────────
        $source = match (true) {
            str_contains($mimeType, 'png')  => imagecreatefrompng($tmpPath),
            str_contains($mimeType, 'gif')  => imagecreatefromgif($tmpPath),
            str_contains($mimeType, 'webp') => imagecreatefromwebp($tmpPath),
            default                         => imagecreatefromjpeg($tmpPath),
        };

        [$origWidth, $origHeight] = getimagesize($tmpPath);

        // ── Calculate target dimensions (never upscale) ──────────────────────
        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            if ($origWidth >= $origHeight) {
                $newWidth  = self::MAX_DIMENSION;
                $newHeight = (int) round($origHeight * self::MAX_DIMENSION / $origWidth);
            } else {
                $newHeight = self::MAX_DIMENSION;
                $newWidth  = (int) round($origWidth * self::MAX_DIMENSION / $origHeight);
            }
        } else {
            $newWidth  = $origWidth;
            $newHeight = $origHeight;
        }

        // ── Resample onto white background (safe for PNG transparency) ───────
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        imagedestroy($source);

        // ── Capture JPEG output into a string ────────────────────────────────
        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $compressed = ob_get_clean();

        imagedestroy($canvas);

        // ── Upload to Droplet and return public URL ───────────────────────────
        $filename   = pathinfo($file->hashName(), PATHINFO_FILENAME) . '.jpg';
        $remotePath = trim($directory, '/') . '/' . $filename;

        Storage::disk('droplet')->put($remotePath, $compressed);

        return rtrim(env('SFTP_BASE_URL'), '/') . '/' . $remotePath;
    }

    /**
     * Delete an image from the Droplet given its full public URL.
     * Silently does nothing if the URL is empty or the file doesn't exist.
     */
    public static function delete(?string $url): void
    {
        if (empty($url)) {
            return;
        }

        $base     = rtrim(env('SFTP_BASE_URL'), '/') . '/';
        $remotePath = str_starts_with($url, $base)
            ? substr($url, strlen($base))
            : null;

        if ($remotePath) {
            Storage::disk('droplet')->delete($remotePath);
        }
    }
}
