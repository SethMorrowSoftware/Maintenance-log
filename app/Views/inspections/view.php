<?php
/**
 * A completed inspection.
 *
 * This is the record a safety inspector, an insurer or a park manager asks to
 * see, so it prints as a document: who checked what, when, what they found and
 * what was signed. The screen version is the same page with the navigation and
 * the sidebar left in.
 */

use App\Dates;
use App\Status;
use App\View;

$printing     = $printing ?? false;
$inspectionId = (int) $inspection['id'];
$status       = (string) $inspection['status'];
$failed       = (int) $inspection['failed_count'];
$passed       = (int) $inspection['passed_count'];
$na           = (int) $inspection['na_count'];
$critical     = (int) $inspection['critical_failed'] === 1;
$inspector    = trim((string) $inspection['first_name'] . ' ' . (string) $inspection['last_name'])
    ?: (string) $inspection['username'];
?>

<?php // The headline verdict, big enough to read at a glance. ?>
<div class="verdict verdict-<?= e($critical ? 'critical' : ($failed > 0 ? 'fail' : 'pass')) ?>">
    <span class="verdict-icon">
        <?= icon($failed > 0 ? 'alert-triangle' : 'check-circle', '', 30) ?>
    </span>
    <div class="verdict-body">
        <p class="verdict-title">
            <?php if ($critical): ?>
                Failed &mdash; a safety-critical item
            <?php elseif ($failed > 0): ?>
                <?= (int) $failed ?> item<?= $failed === 1 ? '' : 's' ?> failed
            <?php else: ?>
                Everything passed
            <?php endif; ?>
        </p>
        <p class="verdict-meta">
            <?= (int) $passed ?> passed
            <?php if ($na > 0): ?>&middot; <?= (int) $na ?> not applicable<?php endif; ?>
            <?php if ($failed > 0): ?>&middot; <?= (int) $failed ?> failed<?php endif; ?>
            <?php if ((int) $inspection['took_out_of_service'] === 1): ?>
                &middot; taken out of service
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid grid-sidebar">
    <div>
        <?php // ==================== The answers ==================== ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e((string) $inspection['checklist_name']) ?></h2>
                    <p class="card-subtitle">
                        <?= e(App\Models\Inspection::subject($inspection)) ?>
                        &middot; <?= e(Dates::datetime((string) ($inspection['completed_at'] ?: $inspection['started_at']))) ?>
                        <?php if ((int) ($inspection['was_late'] ?? 0) === 1): ?>
                            &middot; <span class="text-warn">finished after its due time</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!$printing): ?>
                    <?php View::partial('status-badge', ['value' => $status, 'vocabulary' => 'inspection']); ?>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php $number = 0; ?>
                <?php foreach ($sections as $sectionName => $sectionItems): ?>
                    <?php if ((string) $sectionName !== ''): ?>
                        <h3 class="checklist-section-title"><?= e((string) $sectionName) ?></h3>
                    <?php endif; ?>

                    <ul class="result-list">
                        <?php foreach ($sectionItems as $item): ?>
                            <?php
                            $number++;
                            $response = (string) $item['response'];
                            $isFail   = in_array($response, ['fail', 'no'], true);
                            $isPass   = in_array($response, ['pass', 'yes'], true);
                            $tone     = $isFail ? 'fail' : ($isPass ? 'pass' : ($response === 'na' ? 'na' : 'none'));

                            // Text, number and meter items have no pass/fail; the value is the answer.
                            $value = '';

                            if ($item['value_number'] !== null) {
                                $value = decimal($item['value_number']);
                            } elseif ((string) $item['value_text'] !== '') {
                                $value = (string) $item['value_text'];
                            }
                            ?>
                            <li class="result-item is-<?= e($tone) ?>">
                                <span class="result-mark" aria-hidden="true">
                                    <?php if ($isPass): ?>
                                        <?= icon('check', '', 16) ?>
                                    <?php elseif ($isFail): ?>
                                        <?= icon('x', '', 16) ?>
                                    <?php elseif ($response === 'na'): ?>
                                        &ndash;
                                    <?php else: ?>
                                        &nbsp;
                                    <?php endif; ?>
                                </span>
                                <div class="result-text">
                                    <span class="text-subtle"><?= (int) $number ?>.</span>
                                    <?= e((string) $item['item_text']) ?>
                                    <?php if ((int) $item['is_critical'] === 1): ?>
                                        <span class="checklist-critical">
                                            <?= icon('alert-triangle', '', 12) ?> Safety
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($value !== ''): ?>
                                        <div class="result-value"><?= e($value) ?></div>
                                    <?php endif; ?>
                                    <?php if ((string) $item['notes'] !== ''): ?>
                                        <div class="result-note"><?= nl2br_e((string) $item['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="result-response">
                                    <?php if ($response !== '' || $value === ''): ?>
                                        <?= e(Status::label($response, 'response')) ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>

                <?php if ($items === []): ?>
                    <p class="text-muted">This inspection has no items recorded against it.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php // ==================== Notes and signature ==================== ?>
        <?php if ((string) $inspection['notes'] !== '' || (string) $inspection['signature_name'] !== ''): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('file-text', '', 18) ?> Notes and sign-off</h2>
                </div>
                <div class="card-body">
                    <?php if ((string) $inspection['notes'] !== ''): ?>
                        <p><?= nl2br_e((string) $inspection['notes']) ?></p>
                    <?php endif; ?>

                    <?php if ((string) $inspection['signature_name'] !== ''): ?>
                        <div class="signature-block">
                            <span class="signature-name"><?= e((string) $inspection['signature_name']) ?></span>
                            <span class="signature-meta">
                                Signed <?= e(Dates::datetime((string) ($inspection['completed_at'] ?: $inspection['started_at']))) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$printing && $attachments !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('paperclip', '', 18) ?> Photos</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('attachments', [
                        'attachments' => $attachments,
                        'entityType'  => 'inspection',
                        'entityId'    => $inspectionId,
                        'canUpload'   => false,
                        'canDelete'   => false,
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($printing): ?>
            <div class="print-signature">
                <div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Inspector signature</div>
                </div>
                <div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Reviewed by / date</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php // ==================== Sidebar ==================== ?>
    <div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Details</h3></div>
            <div class="card-body">
                <dl class="detail-list">
                    <?php if ($inspection['asset_id'] === null): ?>
                        <dt>Area</dt>
                        <dd><?= e((string) ($inspection['location_name'] ?? 'Area')) ?></dd>
                    <?php else: ?>
                        <dt><?= e(asset_word(false, true)) ?></dt>
                        <dd>
                            <?php if ($printing || !can('assets.view')): ?>
                                <?= e((string) $inspection['asset_name']) ?>
                                <span class="text-subtle">(<?= e((string) $inspection['asset_tag']) ?>)</span>
                            <?php else: ?>
                                <a href="<?= e(url('asset-view.php', ['id' => (int) $inspection['asset_id']])) ?>">
                                    <?= e((string) $inspection['asset_name']) ?>
                                </a>
                                <span class="text-subtle"><?= e((string) $inspection['asset_tag']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($inspection['location_name'])): ?>
                                <div class="text-sm text-muted"><?= e((string) $inspection['location_name']) ?></div>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <?php if (!empty($inspection['due_at'])): ?>
                        <dt>Was due by</dt>
                        <dd>
                            <?= e(Dates::time((string) $inspection['due_at'])) ?>
                            <?php if ((int) ($inspection['was_late'] ?? 0) === 1): ?>
                                <span class="badge badge-warn">Late</span>
                            <?php elseif (!empty($inspection['completed_at'])): ?>
                                <span class="badge badge-ok">On time</span>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <dt>Checked by</dt>
                    <dd>
                        <?php if ($printing): ?>
                            <?= e($inspector) ?>
                        <?php else: ?>
                            <?php View::partial('user-chip', ['user' => $inspection, 'showRole' => false]); ?>
                        <?php endif; ?>
                    </dd>

                    <dt>Started</dt>
                    <dd><?= e(Dates::datetime((string) $inspection['started_at'])) ?></dd>

                    <?php if (!empty($inspection['completed_at'])): ?>
                        <dt>Finished</dt>
                        <dd><?= e(Dates::datetime((string) $inspection['completed_at'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($inspection['duration_minutes'] !== null): ?>
                        <dt>Took</dt>
                        <dd><?= e(Dates::humanDuration((int) $inspection['duration_minutes'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($inspection['meter_reading'] !== null): ?>
                        <dt>Meter</dt>
                        <dd>
                            <?= e(decimal($inspection['meter_reading'])) ?>
                            <?= e((string) $inspection['meter_type']) ?>
                        </dd>
                    <?php endif; ?>

                    <dt>Result</dt>
                    <dd><?php View::partial('status-badge', ['value' => $status, 'vocabulary' => 'inspection']); ?></dd>

                    <dt>Reference</dt>
                    <dd><code>#<?= $inspectionId ?></code></dd>
                </dl>
            </div>
        </div>

        <?php if (!$printing && !empty($inspection['work_order_id'])): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">What happened next</h3></div>
                <div class="card-body">
                    <p class="text-sm text-muted mb-3">
                        The failures on this inspection were raised as a work order so somebody owns them.
                    </p>
                    <a class="btn btn-secondary btn-block"
                       href="<?= e(url('workorder-view.php', ['id' => (int) $inspection['work_order_id']])) ?>">
                        <?= icon('clipboard', '', 17) ?> Open the work order
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$printing && $history !== []): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">History</h3></div>
                <div class="card-body">
                    <?php
                    $events = [];

                    foreach ($history as $entry) {
                        $events[] = [
                            'title' => (string) $entry['description'],
                            'meta'  => trim((string) ($entry['first_name'] ?? '') . ' ' . (string) ($entry['last_name'] ?? ''))
                                . ' · ' . Dates::ago((string) $entry['created_at']),
                            'tone'  => 'muted',
                        ];
                    }

                    View::partial('timeline', ['events' => $events]);
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$printing && can('inspections.delete')): ?>
            <div class="card is-danger">
                <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
                <div class="card-body">
                    <p class="text-sm text-muted mb-3">
                        Inspection records are safety history. Delete one only if it was
                        recorded against the wrong <?= e(asset_word()) ?>.
                    </p>
                    <?php View::partial('confirm-delete', [
                        'url'         => url('inspection-view.php', ['id' => $inspectionId]),
                        'label'       => 'Delete this inspection',
                        'message'     => 'Delete this inspection record? This cannot be undone.',
                        'buttonClass' => 'btn btn-danger-outline btn-block',
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
