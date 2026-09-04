<?php
/**
 * Attachment list plus an upload dropzone.
 *
 * Variables: $attachments (list), $entityType, $entityId, $canUpload,
 *            $canDelete, $uploadUrl
 */

use App\Settings;
use App\Str;
use App\Uploader;

$attachments = $attachments ?? [];
$entityType  = $entityType  ?? '';
$entityId    = $entityId    ?? 0;
$canUpload   = $canUpload   ?? false;
$canDelete   = $canDelete   ?? false;
$uploadUrl   = $uploadUrl   ?? '';
$maxMb       = (int) round(Settings::maxUploadBytes() / 1048576);
?>
<?php if ($attachments !== []): ?>
    <div class="attachment-grid mb-4">
        <?php foreach ($attachments as $file): ?>
            <?php
            $viewUrl = url('file.php', ['id' => (int) $file['id']]);
            $isImage = (int) ($file['is_image'] ?? 0) === 1;
            ?>
            <figure class="attachment" style="margin:0">
                <a href="<?= e($viewUrl) ?>" target="_blank" rel="noopener">
                    <?php if ($isImage): ?>
                        <img class="attachment-thumb"
                             src="<?= e(url('file.php', ['id' => (int) $file['id'], 'thumb' => 1])) ?>"
                             alt="<?= attr((string) $file['original_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="attachment-file"><?= icon('file-text', '', 30) ?></span>
                    <?php endif; ?>
                </a>
                <figcaption class="attachment-meta">
                    <span class="attachment-name" title="<?= attr((string) $file['original_name']) ?>">
                        <?= e((string) $file['original_name']) ?>
                    </span>
                    <span class="attachment-size"><?= e(Str::formatBytes((int) $file['file_size'])) ?></span>
                </figcaption>

                <?php if ($canDelete && $uploadUrl !== ''): ?>
                    <form method="post" action="<?= e($uploadUrl) ?>" class="no-print">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_attachment">
                        <input type="hidden" name="attachment_id" value="<?= (int) $file['id'] ?>">
                        <button type="submit" class="attachment-remove"
                                data-confirm="Remove this attachment permanently?"
                                aria-label="Remove attachment">
                            <?= icon('x', '', 14) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canUpload && $uploadUrl !== ''): ?>
    <form method="post" action="<?= e($uploadUrl) ?>" enctype="multipart/form-data" class="no-print"
          data-upload-form>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_attachment">
        <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
        <input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">

        <label class="dropzone" data-dropzone data-max-mb="<?= (int) $maxMb ?>">
            <?= icon('upload', '', 24) ?>
            <strong>Add photos or documents</strong>
            <span class="text-sm">Drag files here, or click to choose. Up to <?= (int) $maxMb ?> MB each.</span>
            <span class="text-xs text-subtle"><?= e(implode(', ', Uploader::allowedExtensions())) ?></span>
            <input type="file" name="attachments[]" multiple
                   accept="<?= e(Uploader::acceptAttribute()) ?>" data-dropzone-input>
        </label>

        <div class="dropzone-preview mt-3" data-dropzone-preview hidden></div>

        <div class="mt-3" data-upload-actions hidden>
            <button type="submit" class="btn btn-primary btn-sm">
                <?= icon('upload', '', 15) ?> Upload
            </button>
        </div>
    </form>
<?php elseif ($attachments === []): ?>
    <p class="text-subtle">No attachments.</p>
<?php endif; ?>
