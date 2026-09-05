<?php

declare(strict_types=1);

/**
 * Authenticated file delivery.
 *
 * Uploads live in storage/uploads, which is denied to the web server. Every
 * attachment and avatar is served through here instead, so three things are
 * guaranteed:
 *
 *   1. the visitor is signed in and may see the record the file belongs to,
 *   2. the file is sent with a safe content type and nosniff, so a crafted
 *      upload cannot execute as HTML or SVG in this origin,
 *   3. the path is resolved inside the uploads directory and nowhere else.
 *
 *   file.php?id=123            an attachment
 *   file.php?id=123&thumb=1    its thumbnail
 *   file.php?avatar=4          a user's profile picture
 */

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Request;
use App\Response;
use App\Uploader;

Auth::requireLogin();

// -----------------------------------------------------------------------------
// Avatars
// -----------------------------------------------------------------------------

$avatarUserId = Request::intOrNull('avatar');

if ($avatarUserId !== null) {
    $user = db()->one(
        'SELECT id, avatar_path FROM {users} WHERE id = ? AND deleted_at IS NULL LIMIT 1',
        [$avatarUserId]
    );

    if ($user === null || empty($user['avatar_path'])) {
        Response::abortPage(404, 'That picture is not available.');
    }

    $path = Uploader::absolutePath((string) $user['avatar_path']);

    if ($path === null) {
        Response::abortPage(404, 'That picture is no longer on the server.');
    }

    Response::file($path, 'avatar-' . (int) $user['id'] . '.jpg', mime_of($path), true);
}

// -----------------------------------------------------------------------------
// Machine photos
// -----------------------------------------------------------------------------

$assetPhotoId = Request::intOrNull('asset_photo');

if ($assetPhotoId !== null) {
    if (!Acl::can('assets.view')) {
        Response::abortPage(403, 'You do not have permission to view that.');
    }

    $asset = db()->one(
        'SELECT id, name, image_path FROM {assets} WHERE id = ? AND deleted_at IS NULL LIMIT 1',
        [$assetPhotoId]
    );

    if ($asset === null || empty($asset['image_path'])) {
        Response::abortPage(404, 'That ' . asset_word() . ' has no photo.');
    }

    $path = Uploader::absolutePath((string) $asset['image_path']);

    if ($path === null) {
        Response::abortPage(404, 'That photo is no longer on the server.');
    }

    Response::file($path, 'asset-' . (int) $asset['id'] . '.jpg', mime_of($path), true);
}

// -----------------------------------------------------------------------------
// Attachments
// -----------------------------------------------------------------------------

$id = Request::intOrNull('id');

if ($id === null) {
    Response::abortPage(400, 'No file was requested.');
}

$attachment = db()->one('SELECT * FROM {attachments} WHERE id = ? LIMIT 1', [$id]);

if ($attachment === null) {
    Response::abortPage(404, 'That file does not exist. It may have been deleted.');
}

// -----------------------------------------------------------------------------
// Authorisation: can this person see the record the file hangs off?
// -----------------------------------------------------------------------------

if (!may_view_attachment($attachment)) {
    Response::abortPage(403, 'You do not have permission to view that file.');
}

// -----------------------------------------------------------------------------
// Send it
// -----------------------------------------------------------------------------

$wantThumb = Request::bool('thumb') && !empty($attachment['thumb_path']);
$relative  = (string) ($wantThumb ? $attachment['thumb_path'] : $attachment['file_path']);
$path      = Uploader::absolutePath($relative);

if ($path === null) {
    log_error('Attachment row points at a missing file', [
        'attachment' => (int) $attachment['id'],
        'path'       => $relative,
    ]);

    Response::abortPage(404, 'That file is no longer on the server.');
}

// Images and PDFs are shown in the browser; everything else downloads.
$inline = (int) $attachment['is_image'] === 1
    || (string) $attachment['mime_type'] === 'application/pdf'
    || Request::bool('inline');

if (Request::bool('download')) {
    $inline = false;
}

Response::file(
    $path,
    (string) $attachment['original_name'],
    (string) $attachment['mime_type'],
    $inline
);

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

/**
 * Map an attachment's parent record onto the permission that guards it.
 *
 * A file is exactly as private as the thing it is attached to, so this asks
 * about the parent rather than inventing a separate rule for attachments.
 *
 * @param array<string, mixed> $attachment
 */
function may_view_attachment(array $attachment): bool
{
    $type = (string) $attachment['entity_type'];
    $id   = (int) $attachment['entity_id'];

    switch ($type) {
        case 'asset':
            return Acl::can('assets.view')
                && db()->exists('assets', ['id' => $id, 'deleted_at' => null]);

        case 'maintenance_log':
            return Acl::can('logs.view')
                && db()->exists('maintenance_logs', ['id' => $id, 'deleted_at' => null]);

        case 'work_order':
            return Acl::can('workorders.view')
                && db()->exists('work_orders', ['id' => $id, 'deleted_at' => null]);

        case 'inspection':
            return Acl::can('inspections.view')
                && db()->exists('inspections', ['id' => $id]);

        case 'part':
            return Acl::can('parts.view')
                && db()->exists('parts', ['id' => $id, 'deleted_at' => null]);

        case 'user':
            // Profile pictures are visible to anyone signed in — they appear
            // beside every log entry and comment.
            return true;

        default:
            return false;
    }
}

/** Best-effort content type for a file already on disk. */
function mime_of(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $path);
            finfo_close($finfo);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    return 'application/octet-stream';
}
