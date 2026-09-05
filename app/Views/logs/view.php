<?php
/**
 * One maintenance log: the record of a single job.
 *
 * Doubles as the printable maintenance record, so it is laid out to read as a
 * document as well as a screen.
 */

use App\Acl;
use App\Dates;
use App\Settings;
use App\Status;
use App\View;

$printing    = $printing ?? false;
$logId       = (int) $log['id'];
$canSeeCosts = costs_visible();
?>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e((string) $log['title']) ?></h2>
                    <p class="card-subtitle">
                        <?php View::partial('status-badge', ['value' => (string) $log['log_type'], 'vocabulary' => 'log_type']); ?>
                        on <?= e(Dates::datetime((string) $log['performed_at'])) ?>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Machine</dt>
                    <dd>
                        <?php if ($printing): ?>
                            <?= e((string) $log['asset_name']) ?> (<?= e((string) $log['asset_tag']) ?>)
                        <?php else: ?>
                            <a href="<?= e(url('asset-view.php', ['id' => (int) $log['asset_id']])) ?>">
                                <?= e((string) $log['asset_name']) ?>
                            </a>
                            <span class="text-subtle"><?= e((string) $log['asset_tag']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($log['category_name'])): ?>
                            <div class="text-sm text-muted"><?= e((string) $log['category_name']) ?>
                                <?php if (!empty($log['location_name'])): ?>
                                    &middot; <?= e((string) $log['location_name']) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </dd>

                    <dt>Carried out by</dt>
                    <dd>
                        <?php if ($printing): ?>
                            <?= e(trim((string) $log['first_name'] . ' ' . (string) $log['last_name']) ?: (string) $log['username']) ?>
                        <?php else: ?>
                            <?php View::partial('user-chip', ['user' => $log, 'showRole' => false]); ?>
                        <?php endif; ?>
                    </dd>

                    <dt>Date and time</dt>
                    <dd>
                        <?= e(Dates::datetime((string) $log['performed_at'])) ?>
                        <?php if (!$printing): ?>
                            <span class="text-subtle">(<?= e(Dates::ago((string) $log['performed_at'])) ?>)</span>
                        <?php endif; ?>
                    </dd>

                    <?php if ((float) $log['labor_hours'] > 0): ?>
                        <dt>Time taken</dt>
                        <dd><?= e(Dates::humanHours((float) $log['labor_hours'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($log['downtime_minutes'] !== null && (int) $log['downtime_minutes'] > 0): ?>
                        <dt>Out of service for</dt>
                        <dd><?= e(Dates::humanDuration((int) $log['downtime_minutes'])) ?></dd>
                    <?php endif; ?>

                    <?php if ($log['meter_reading'] !== null): ?>
                        <dt>Meter at the time</dt>
                        <dd><?= e(decimal($log['meter_reading'])) ?> <?= e((string) $log['meter_type']) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($log['status_after'])): ?>
                        <dt>Status afterwards</dt>
                        <dd><?php View::partial('status-badge', ['value' => (string) $log['status_after'], 'vocabulary' => 'asset']); ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($log['schedule_name'])): ?>
                        <dt>Scheduled job</dt>
                        <dd><?= e((string) $log['schedule_name']) ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($log['wo_number'])): ?>
                        <dt>Work order</dt>
                        <dd>
                            <?php if ($printing): ?>
                                <?= e((string) $log['wo_number']) ?> — <?= e((string) $log['work_order_title']) ?>
                            <?php else: ?>
                                <a href="<?= e(url('workorder-view.php', ['id' => (int) $log['work_order_id']])) ?>">
                                    <?= e((string) $log['wo_number']) ?>
                                </a> — <?= e((string) $log['work_order_title']) ?>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($log['description'])): ?>
                    <hr>
                    <h3>Why</h3>
                    <p><?= nl2br_e((string) $log['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($log['work_performed'])): ?>
                    <hr>
                    <h3>What was done</h3>
                    <p><?= nl2br_e((string) $log['work_performed']) ?></p>
                <?php endif; ?>

                <?php if ((int) $log['is_completed'] === 0): ?>
                    <div class="alert alert-info mt-4">
                        <?= icon('clock', '', 18) ?>
                        <div class="alert-body">
                            <strong class="alert-title">This job is not finished</strong>
                            <p style="margin:4px 0 0">It was recorded as work in progress.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ((int) $log['requires_followup'] === 1): ?>
                    <div class="alert alert-warning mt-4">
                        <?= icon('alert-circle', '', 18) ?>
                        <div class="alert-body">
                            <strong class="alert-title">Needs a follow-up</strong>
                            <?php if (!empty($log['followup_notes'])): ?>
                                <p style="margin:4px 0 0"><?= nl2br_e((string) $log['followup_notes']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($parts !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('package', '', 18) ?> Parts used</h2>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Part</th><th class="is-numeric">Qty</th>
                                <?php if ($canSeeCosts): ?>
                                    <th class="is-numeric">Each</th><th class="is-numeric">Total</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td>
                                        <?php if (!$printing && !empty($part['part_id'])): ?>
                                            <a href="<?= e(url('part-view.php', ['id' => (int) $part['part_id']])) ?>">
                                                <?= e((string) $part['part_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= e((string) $part['part_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($part['part_number'])): ?>
                                            <span class="cell-secondary"><?= e((string) $part['part_number']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($part['notes'])): ?>
                                            <span class="cell-secondary"><?= e((string) $part['notes']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="is-numeric"><?= e(decimal($part['quantity'])) ?></td>
                                    <?php if ($canSeeCosts): ?>
                                        <td class="is-numeric"><?= e(money($part['unit_cost'])) ?></td>
                                        <td class="is-numeric"><?= e(money($part['total_cost'])) ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$printing): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('paperclip', '', 18) ?> Photos and documents</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('attachments', [
                        'attachments' => $attachments,
                        'entityType'  => 'maintenance_log',
                        'entityId'    => $logId,
                        'canUpload'   => Acl::canEditLog($log),
                        'canDelete'   => Acl::canEditLog($log),
                        'uploadUrl'   => url('log-view.php', ['id' => $logId]),
                    ]); ?>
                </div>
            </div>
        <?php elseif ($attachments !== []): ?>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Photos</h2></div>
                <div class="card-body">
                    <div class="attachment-grid">
                        <?php foreach ($attachments as $file): ?>
                            <?php if ((int) $file['is_image'] === 1): ?>
                                <img class="attachment-thumb"
                                     src="<?= e(url('file.php', ['id' => (int) $file['id']])) ?>"
                                     alt="<?= attr((string) $file['original_name']) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($printing): ?>
            <div class="print-signature">
                <div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Technician signature</div>
                </div>
                <div>
                    <div class="sig-line"></div>
                    <div class="sig-label">Reviewed by / date</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$printing): ?>
        <div>
            <?php if ($canSeeCosts): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cost</h3>
                </div>
                <div class="card-body">
                    <dl class="detail-list">
                        <dt>Labour</dt>
                        <dd class="tabular"><?= e(money($log['labor_cost'])) ?></dd>
                        <dt>Parts</dt>
                        <dd class="tabular"><?= e(money($log['parts_cost'])) ?></dd>
                        <?php if ((float) $log['other_cost'] > 0): ?>
                            <dt>Other</dt>
                            <dd class="tabular"><?= e(money($log['other_cost'])) ?></dd>
                        <?php endif; ?>
                    </dl>
                    <hr>
                    <div class="flex items-center justify-between">
                        <strong>Total</strong>
                        <strong class="text-lg tabular"><?= e(money($log['total_cost'])) ?></strong>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Record</h3></div>
                <div class="card-body">
                    <dl class="detail-list">
                        <dt>Logged</dt>
                        <dd><?= e(Dates::datetime((string) $log['created_at'])) ?></dd>
                        <?php if ((string) $log['updated_at'] !== (string) $log['created_at']): ?>
                            <dt>Last edited</dt>
                            <dd>
                                <?= e(Dates::datetime((string) $log['updated_at'])) ?>
                                <?php if (!empty($log['editor_first'])): ?>
                                    <div class="text-sm text-subtle">
                                        by <?= e(trim((string) $log['editor_first'] . ' ' . (string) $log['editor_last'])) ?>
                                    </div>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>
                        <dt>Reference</dt>
                        <dd><code>#<?= $logId ?></code></dd>
                    </dl>
                </div>
            </div>

            <?php if (can('logs.delete')): ?>
                <div class="card is-danger">
                    <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
                    <div class="card-body">
                        <p class="text-sm text-muted mb-3">
                            Deleting removes this from the history and puts any parts it used back into stock.
                        </p>
                        <?php View::partial('confirm-delete', [
                            'url'         => url('log-view.php', ['id' => $logId]),
                            'label'       => 'Delete this log',
                            'message'     => 'Delete this maintenance record? Reports and totals will change to match.',
                            'buttonClass' => 'btn btn-danger-outline btn-block',
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
