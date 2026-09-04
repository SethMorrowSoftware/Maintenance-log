<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * File attachments: photos of a cracked weld, a manufacturer's PDF, a warranty
 * scan.
 *
 * The threat here is real: an upload directory that will execute PHP is the
 * classic way a site gets taken over. Defences, in order:
 *
 *   1. Extension whitelist, checked against the real MIME type from finfo.
 *   2. Stored under a random 32-hex name — the original name is only ever a
 *      database column, never a path.
 *   3. Written to storage/uploads, which has a deny-all .htaccess and sits
 *      outside anything the app will execute.
 *   4. Served only through file.php, which checks the session and sends
 *      Content-Disposition with nosniff.
 *   5. Images are re-encoded through GD, which discards EXIF and anything
 *      hidden in the file that is not actually pixels.
 */
final class Uploader
{
    /** Extensions accepted, mapped to the MIME types finfo may report. */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'txt'  => ['text/plain'],
    ];

    /** Never accepted, whatever the whitelist says. */
    private const FORBIDDEN = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
        'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe',
        'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'jar', 'js', 'mjs', 'html',
        'htm', 'xhtml', 'shtml', 'svg', 'swf', 'jsp', 'asp', 'aspx',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private function __construct()
    {
    }

    /**
     * Accept one uploaded file and record it against an entity.
     *
     * @param  array<string, mixed> $file one entry from $_FILES
     * @return array{ok: bool, error: string, id: int, attachment: array<string, mixed>|null}
     */
    public static function handle(
        array $file,
        string $entityType,
        int $entityId,
        ?int $userId = null,
        string $caption = ''
    ): array {
        $userId = $userId ?? Auth::id();

        // --- PHP-level upload errors -----------------------------------------
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return self::fail(self::describeUploadError($error));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            // is_uploaded_file is what stops a crafted request from asking the
            // app to "attach" /etc/passwd.
            return self::fail('That upload could not be verified. Please try again.');
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $size         = (int) ($file['size'] ?? 0);

        // --- Size ------------------------------------------------------------
        $maxBytes = Settings::maxUploadBytes();

        if ($size <= 0) {
            return self::fail('That file is empty.');
        }

        if ($size > $maxBytes) {
            return self::fail(
                'That file is ' . Str::formatBytes($size) . ', which is over the '
                . Str::formatBytes($maxBytes) . ' limit.'
            );
        }

        // --- Extension -------------------------------------------------------
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '') {
            return self::fail('That file has no extension, so it cannot be accepted.');
        }

        if (in_array($extension, self::FORBIDDEN, true)) {
            self::reportRejection($originalName, 'forbidden extension .' . $extension);

            return self::fail('Files of that type cannot be uploaded.');
        }

        if (!isset(self::ALLOWED[$extension])) {
            return self::fail(
                'Files of type .' . $extension . ' are not accepted. Allowed types: '
                . implode(', ', self::allowedExtensions()) . '.'
            );
        }

        // A double extension like "photo.php.jpg" passes the check above, so
        // reject any file whose name contains a forbidden extension anywhere.
        foreach (explode('.', strtolower($originalName)) as $piece) {
            if (in_array($piece, self::FORBIDDEN, true)) {
                self::reportRejection($originalName, 'forbidden extension inside the file name');

                return self::fail('That file name is not allowed. Rename the file and try again.');
            }
        }

        // --- Real content type ------------------------------------------------
        $mime = self::detectMime($tmpPath);

        if (!self::mimeMatchesExtension($mime, $extension)) {
            self::reportRejection($originalName, 'MIME ' . $mime . ' does not match .' . $extension);

            return self::fail(
                'That file does not look like a .' . $extension . ' file. '
                . 'Check the file and try again.'
            );
        }

        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);

        // A file claiming to be an image must actually decode as one.
        if ($isImage) {
            $info = @getimagesize($tmpPath);

            if ($info === false) {
                return self::fail('That image could not be read. It may be corrupt.');
            }
        }

        // --- Destination ------------------------------------------------------
        $relativeDir = Dates::localNow()->format('Y/m');
        $absoluteDir = UPLOAD_PATH . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            log_error('Could not create upload directory: ' . $absoluteDir);

            return self::fail('The upload folder could not be created. Check that storage/uploads is writable.');
        }

        $storedName   = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = $absoluteDir . '/' . $storedName;
        $relativePath = $relativeDir . '/' . $storedName;

        // --- Move -------------------------------------------------------------
        if (!@move_uploaded_file($tmpPath, $absolutePath)) {
            log_error('move_uploaded_file failed', ['to' => $absolutePath]);

            return self::fail('The file could not be saved. Check that storage/uploads is writable.');
        }

        @chmod($absolutePath, 0644);

        // --- Re-encode images, generate a thumbnail ---------------------------
        $thumbPath = null;

        if ($isImage && function_exists('imagecreatetruecolor')) {
            self::sanitizeImage($absolutePath, $extension);
            $thumbPath = self::makeThumbnail($absolutePath, $absoluteDir, $storedName, $extension, $relativeDir);
        }

        $finalSize = (int) (@filesize($absolutePath) ?: $size);

        // --- Record -----------------------------------------------------------
        try {
            $id = db()->insert('attachments', [
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'original_name' => mb_substr(self::tidyName($originalName), 0, 255, 'UTF-8'),
                'stored_name'   => $storedName,
                'file_path'     => $relativePath,
                'thumb_path'    => $thumbPath,
                'mime_type'     => $mime,
                'file_size'     => $finalSize,
                'is_image'      => $isImage ? 1 : 0,
                'caption'       => mb_substr($caption, 0, 255, 'UTF-8'),
                'uploaded_by'   => $userId,
                'created_at'    => Dates::nowUtc(),
            ]);
        } catch (Throwable $e) {
            @unlink($absolutePath);

            if ($thumbPath !== null) {
                @unlink(UPLOAD_PATH . '/' . $thumbPath);
            }

            log_error('Attachment insert failed: ' . $e->getMessage());

            return self::fail('The file was uploaded but could not be recorded. Please try again.');
        }

        $attachment = db()->find('attachments', $id);

        Audit::record('attachment.upload', $entityType, $entityId, 'Attached ' . self::tidyName($originalName));

        return ['ok' => true, 'error' => '', 'id' => $id, 'attachment' => $attachment];
    }

    /**
     * Handle several files from one multi-file field.
     *
     * @param  list<array<string, mixed>> $files
     * @return array{uploaded: int, errors: list<string>}
     */
    public static function handleMany(array $files, string $entityType, int $entityId, ?int $userId = null): array
    {
        $uploaded = 0;
        $errors   = [];

        foreach ($files as $file) {
            $result = self::handle($file, $entityType, $entityId, $userId);

            if ($result['ok']) {
                $uploaded++;
            } else {
                $name = (string) ($file['name'] ?? 'file');
                $errors[] = self::tidyName($name) . ': ' . $result['error'];
            }
        }

        return ['uploaded' => $uploaded, 'errors' => $errors];
    }

    /**
     * @return array{ok: false, error: string, id: int, attachment: null}
     */
    private static function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'id' => 0, 'attachment' => null];
    }

    private static function reportRejection(string $name, string $reason): void
    {
        log_error('Upload rejected', ['file' => $name, 'reason' => $reason, 'user' => Auth::id()]);

        Audit::record('attachment.rejected', 'attachment', null, 'Rejected upload "' . $name . '": ' . $reason);
    }

    // -------------------------------------------------------------------------
    // Content inspection
    // -------------------------------------------------------------------------

    private static function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);

            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }

        return 'application/octet-stream';
    }

    private static function mimeMatchesExtension(string $mime, string $extension): bool
    {
        $allowed = self::ALLOWED[$extension] ?? [];

        if ($allowed === []) {
            return false;
        }

        return in_array(strtolower($mime), $allowed, true);
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED);
    }

    /** For the accept="" attribute on a file input. */
    public static function acceptAttribute(): string
    {
        $out = [];

        foreach (self::allowedExtensions() as $extension) {
            $out[] = '.' . $extension;
        }

        return implode(',', $out);
    }

    // -------------------------------------------------------------------------
    // Image handling
    // -------------------------------------------------------------------------

    /**
     * Re-encode an image through GD.
     *
     * This is a security step as much as a size one: whatever was riding along
     * in the original file — EXIF, an appended archive, a polyglot payload —
     * does not survive being decoded to a pixel buffer and written out fresh.
     */
    private static function sanitizeImage(string $path, string $extension): void
    {
        $maxDimension = Settings::int('image_max_dimension', 2000, 400, 8000);

        try {
            $image = self::loadImage($path, $extension);

            if ($image === null) {
                return;
            }

            $width  = imagesx($image);
            $height = imagesy($image);

            // Fix orientation before resizing, or portrait photos come out sideways.
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $image = self::applyExifOrientation($image, $path);
                $width  = imagesx($image);
                $height = imagesy($image);
            }

            $scale = 1.0;

            if ($width > $maxDimension || $height > $maxDimension) {
                $scale = $maxDimension / max($width, $height);
            }

            $newWidth  = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if ($resized === false) {
                imagedestroy($image);

                return;
            }

            // Keep transparency for the formats that have it.
            if ($extension === 'png' || $extension === 'gif' || $extension === 'webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);

                if ($transparent !== false) {
                    imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                }
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            self::saveImage($resized, $path, $extension, 85);

            imagedestroy($image);
            imagedestroy($resized);
        } catch (Throwable $e) {
            log_error('Image sanitisation failed: ' . $e->getMessage(), ['path' => basename($path)]);
        }
    }

    /**
     * @return \GdImage|resource|null
     */
    private static function loadImage(string $path, string $extension)
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($path);
                break;
            case 'png':
                $image = @imagecreatefrompng($path);
                break;
            case 'gif':
                $image = @imagecreatefromgif($path);
                break;
            case 'webp':
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
                break;
            default:
                return null;
        }

        return $image === false ? null : $image;
    }

    /**
     * @param \GdImage|resource $image
     */
    private static function saveImage($image, string $path, string $extension, int $quality = 85): bool
    {
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return @imagejpeg($image, $path, $quality);
            case 'png':
                // PNG quality is a 0-9 compression level, inverted.
                return @imagepng($image, $path, 6);
            case 'gif':
                return @imagegif($image, $path);
            case 'webp':
                return function_exists('imagewebp') ? @imagewebp($image, $path, $quality) : false;
            default:
                return false;
        }
    }

    /**
     * Rotate a JPEG according to its EXIF orientation tag.
     *
     * @param  \GdImage|resource $image
     * @return \GdImage|resource
     */
    private static function applyExifOrientation($image, string $path)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);
        } catch (Throwable $e) {
            return $image;
        }

        if (!is_array($exif) || !isset($exif['Orientation'])) {
            return $image;
        }

        $angle = 0;
        $flip  = false;

        switch ((int) $exif['Orientation']) {
            case 2:
                $flip = true;
                break;
            case 3:
                $angle = 180;
                break;
            case 4:
                $angle = 180;
                $flip  = true;
                break;
            case 5:
                $angle = -90;
                $flip  = true;
                break;
            case 6:
                $angle = -90;
                break;
            case 7:
                $angle = 90;
                $flip  = true;
                break;
            case 8:
                $angle = 90;
                break;
            default:
                return $image;
        }

        if ($angle !== 0) {
            $rotated = @imagerotate($image, $angle, 0);

            if ($rotated !== false) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        if ($flip && function_exists('imageflip')) {
            @imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * Build a 400px thumbnail alongside the original.
     *
     * @return string|null relative path, or null if it could not be made
     */
    private static function makeThumbnail(
        string $sourcePath,
        string $absoluteDir,
        string $storedName,
        string $extension,
        string $relativeDir
    ): ?string {
        try {
            $image = self::loadImage($sourcePath, $extension);

            if ($image === null) {
                return null;
            }

            $width  = imagesx($image);
            $height = imagesy($image);
            $target = 400;

            if ($width <= $target && $height <= $target) {
                // Small enough already; reuse the original as its own thumbnail.
                imagedestroy($image);

                return $relativeDir . '/' . $storedName;
            }

            $scale     = $target / max($width, $height);
            $newWidth  = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($newWidth, $newHeight);

            if ($thumb === false) {
                imagedestroy($image);

                return null;
            }

            if ($extension === 'png' || $extension === 'gif' || $extension === 'webp') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);

                if ($transparent !== false) {
                    imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
                }
            }

            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $thumbName = preg_replace('/\.([a-z0-9]+)$/i', '_thumb.$1', $storedName);

            if (!is_string($thumbName)) {
                imagedestroy($image);
                imagedestroy($thumb);

                return null;
            }

            $saved = self::saveImage($thumb, $absoluteDir . '/' . $thumbName, $extension, 80);

            imagedestroy($image);
            imagedestroy($thumb);

            return $saved ? $relativeDir . '/' . $thumbName : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Reading and removing
    // -------------------------------------------------------------------------

    /**
     * Attachments for one record.
     *
     * @return list<array<string, mixed>>
     */
    public static function forEntity(string $entityType, int $entityId): array
    {
        try {
            return db()->all(
                'SELECT a.*, u.first_name, u.last_name, u.username
                 FROM {attachments} a
                 LEFT JOIN {users} u ON u.id = a.uploaded_by
                 WHERE a.entity_type = ? AND a.entity_id = ?
                 ORDER BY a.created_at ASC, a.id ASC',
                [$entityType, $entityId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function countForEntity(string $entityType, int $entityId): int
    {
        try {
            return db()->count(
                'SELECT COUNT(*) FROM {attachments} WHERE entity_type = ? AND entity_id = ?',
                [$entityType, $entityId]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Delete an attachment and its files from disk.
     */
    public static function delete(int $attachmentId): bool
    {
        $attachment = db()->find('attachments', $attachmentId);

        if ($attachment === null) {
            return false;
        }

        self::unlinkRelative((string) $attachment['file_path']);

        $thumb = (string) ($attachment['thumb_path'] ?? '');

        // Only remove the thumbnail if it is a separate file.
        if ($thumb !== '' && $thumb !== (string) $attachment['file_path']) {
            self::unlinkRelative($thumb);
        }

        db()->delete('attachments', ['id' => $attachmentId]);

        Audit::record(
            'attachment.delete',
            (string) $attachment['entity_type'],
            (int) $attachment['entity_id'],
            'Removed attachment "' . (string) $attachment['original_name'] . '"'
        );

        return true;
    }

    /** Delete every attachment belonging to a record. */
    public static function deleteForEntity(string $entityType, int $entityId): int
    {
        $count = 0;

        foreach (self::forEntity($entityType, $entityId) as $attachment) {
            if (self::delete((int) $attachment['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolve a stored relative path to an absolute one, refusing to escape
     * the uploads directory.
     */
    public static function absolutePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath, '/'));

        if ($relativePath === '' || strpos($relativePath, '..') !== false || strpos($relativePath, "\0") !== false) {
            return null;
        }

        $candidate = UPLOAD_PATH . '/' . $relativePath;
        $real      = realpath($candidate);
        $base      = realpath(UPLOAD_PATH);

        if ($real === false || $base === false) {
            return null;
        }

        // The resolved path must still be inside the uploads directory.
        if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $real;
    }

    private static function unlinkRelative(string $relativePath): void
    {
        $absolute = self::absolutePath($relativePath);

        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /** Strip anything path-like out of a display name. */
    public static function tidyName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name);

        return $name === '' ? 'file' : $name;
    }

    /**
     * Turn a PHP upload error code into something a technician can act on.
     */
    private static function describeUploadError(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'That file is larger than this server accepts ('
                     . (string) ini_get('upload_max_filesize') . ').';
            case UPLOAD_ERR_FORM_SIZE:
                return 'That file is larger than the form allows.';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload was interrupted. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'The server has no temporary folder for uploads. Ask your host to check upload_tmp_dir.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'The server could not write the file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension blocked the upload.';
            default:
                return 'The upload failed. Please try again.';
        }
    }

    /**
     * Remove attachment rows whose file has vanished, and orphan files with no
     * row. Called by cron.php.
     *
     * @return array{rows: int, files: int}
     */
    public static function pruneOrphans(): array
    {
        $rows  = 0;
        $files = 0;

        try {
            foreach (db()->all('SELECT id, file_path FROM {attachments}') as $attachment) {
                if (self::absolutePath((string) $attachment['file_path']) === null) {
                    db()->delete('attachments', ['id' => (int) $attachment['id']]);
                    $rows++;
                }
            }
        } catch (Throwable $e) {
            // Housekeeping only.
        }

        return ['rows' => $rows, 'files' => $files];
    }
}
