<?php

use App\Acl;
use App\Dates;
use App\Status;
use App\View;

use App\Models\WorkOrder;

$woId     = (int) $workOrder['id'];
$isClosed = Status::isClosedWorkOrder((string) $workOrder['status']);
$overdue  = !empty($workOrder['due_date']) && Dates::isPast((string) $workOrder['due_date']) && !$isClosed;

$canWork  = Acl::canWorkOnWorkOrder($workOrder);
$canClose = Acl::canCloseWorkOrder($workOrder);
$mine     = (int) ($workOrder['assigned_to'] ?? 0) === (int) (user()['id'] ?? 0);

// Offer to put the machine back on the track only when this job is what took
// it off and nothing else is holding it there. Never pre-ticked on a safety
// issue: that one gets a deliberate decision.
$mayReturn   = !$isClosed && WorkOrder::mayReturnToService($workOrder);
$returnTicked = $mayReturn && (int) ($workOrder['is_safety_issue'] ?? 0) !== 1;
?>

<?php if ((int) $workOrder['is_safety_issue'] === 1 && !$isClosed): ?>
    <div class="alert alert-error">
        <?= icon('shield', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">Reported as a safety issue</strong>
            <p style="margin:4px 0 0">Deal with this before the <?= e(asset_word()) ?> carries guests again.</p>
        </div>
    </div>
<?php endif; ?>

<?php // ==================== Is it done? ====================
      // The one question a mechanic standing at the machine needs to answer,
      // at the top of the page, with the answer as a button and not a form. ?>
<?php if ($canWork && !$isClosed): ?>
    <form method="post" action="<?= e(url('workorder-view.php', ['id' => $woId])) ?>" class="card is-accent wo-finish">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $woId ?>">
        <div class="card-body">
            <?php View::partial('form-field', [
                'name'        => 'resolution',
                'label'       => 'What did you do?',
                'type'        => 'text',
                'value'       => (string) ($workOrder['resolution'] ?? ''),
                'placeholder' => 'Fitted a new set of front pads',
                'hint'        => 'Optional. One line is plenty.',
                'noOld'       => true,
                'attrs'       => ['maxlength' => 191],
            ]); ?>

            <?php if ($mayReturn): ?>
                <label class="form-check" for="f_back_in_service">
                    <input type="checkbox" id="f_back_in_service" name="back_in_service" value="1"
                           <?= $returnTicked ? 'checked' : '' ?>>
                    <span class="form-check-label">
                        Put <?= e((string) $workOrder['asset_name']) ?> back in service
                        <small>
                            <?php if ((int) ($workOrder['is_safety_issue'] ?? 0) === 1): ?>
                                This was reported as a safety issue, so tick it only if it is genuinely safe to run.
                            <?php else: ?>
                                It went out of service when this was reported.
                            <?php endif; ?>
                        </small>
                    </span>
                </label>
            <?php endif; ?>

            <button type="submit" name="action" value="done" class="btn btn-primary btn-block btn-lg">
                <?= icon('check-circle', '', 20) ?>
                <?= $canClose ? 'Done — it is fixed' : 'Done — hand it over for sign-off' ?>
            </button>

            <?php if (!$canClose): ?>
                <p class="form-hint">
                    This site asks a manager to sign work off. They are told as soon as you tap it.
                </p>
            <?php endif; ?>

            <div class="wo-finish-more">
                <?php if (Acl::canClaimWorkOrder($workOrder)): ?>
                    <button type="submit" name="action" value="claim" class="btn btn-secondary">
                        <?= icon('user', '', 16) ?>
                        <?= empty($workOrder['assigned_to']) ? "I'm on it" : 'Take it over' ?>
                    </button>
                <?php elseif ($mine): ?>
                    <button type="submit" name="action" value="hand_back" class="btn btn-ghost">
                        <?= icon('refresh', '', 16) ?> Hand it back
                    </button>
                <?php endif; ?>

                <?php if (can('logs.create')): ?>
                    <a class="btn btn-secondary" data-no-guard
                       href="<?= e(url('log-edit.php', ['work_order_id' => $woId, 'asset_id' => (int) ($workOrder['asset_id'] ?? 0)])) ?>">
                        <?= icon('wrench', '', 16) ?> Log parts and time
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
<?php elseif ($isClosed && $canWork): ?>
    <form method="post" action="<?= e(url('workorder-view.php', ['id' => $woId])) ?>" class="card wo-finish">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $woId ?>">
        <div class="card-body flex items-center justify-between gap-3 flex-wrap">
            <span>
                <strong><?= e(Status::label((string) $workOrder['status'], 'workorder')) ?></strong>
                <?php if (!empty($workOrder['completed_at'])): ?>
                    <span class="text-muted"><?= e(Dates::ago((string) $workOrder['completed_at'])) ?></span>
                <?php endif; ?>
            </span>
            <button type="submit" name="action" value="reopen" class="btn btn-secondary">
                <?= icon('refresh', '', 16) ?> Not fixed after all — reopen it
            </button>
        </div>
    </form>
<?php endif; ?>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e((string) $workOrder['title']) ?></h2>
                    <p class="card-subtitle">
                        <?php View::partial('status-badge', ['value' => (string) $workOrder['status'], 'vocabulary' => 'workorder']); ?>
                        <?php View::partial('status-badge', ['value' => (string) $workOrder['priority'], 'vocabulary' => 'priority']); ?>
                        raised <?= e(Dates::ago((string) $workOrder['created_at'])) ?>
                    </p>
                </div>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt><?= e(asset_word(false, true)) ?></dt>
                    <dd>
                        <?php if (!empty($workOrder['asset_id'])): ?>
                            <a href="<?= e(url('asset-view.php', ['id' => (int) $workOrder['asset_id']])) ?>">
                                <?= e((string) $workOrder['asset_name']) ?>
                            </a>
                            <span class="text-subtle"><?= e((string) $workOrder['asset_tag']) ?></span>
                            <?php if (!empty($workOrder['asset_status'])): ?>
                                <div class="mt-1">
                                    <?php View::partial('status-badge', ['value' => (string) $workOrder['asset_status'], 'vocabulary' => 'asset']); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-subtle">Not linked to <?= e(an_asset()) ?></span>
                        <?php endif; ?>
                    </dd>

                    <dt>Reported by</dt>
                    <dd><?php View::partial('user-chip', ['user' => [
                        'id'          => $workOrder['reporter_id'],
                        'first_name'  => $workOrder['reporter_first'],
                        'last_name'   => $workOrder['reporter_last'],
                        'username'    => $workOrder['reporter_username'],
                        'avatar_path' => $workOrder['reporter_avatar'],
                    ]]); ?></dd>

                    <dt>How it was spotted</dt>
                    <dd><?= e(Status::label((string) $workOrder['source'], 'wo_source')) ?></dd>

                    <?php if (!empty($workOrder['due_date'])): ?>
                        <dt>Needed by</dt>
                        <dd>
                            <span class="<?= $overdue ? 'text-danger font-semi' : '' ?>">
                                <?= e(Dates::dateOnly((string) $workOrder['due_date'])) ?>
                            </span>
                            <?php if ($overdue): ?><span class="badge badge-danger">Overdue</span><?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <?php if (!empty($workOrder['completed_at'])): ?>
                        <dt>Completed</dt>
                        <dd>
                            <?= e(Dates::datetime((string) $workOrder['completed_at'])) ?>
                            <?php if (!empty($workOrder['closer_first'])): ?>
                                <div class="text-sm text-subtle">
                                    by <?= e(trim((string) $workOrder['closer_first'] . ' ' . (string) $workOrder['closer_last'])) ?>
                                </div>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>

                    <?php if (feature_on('downtime') && $workOrder['downtime_minutes'] !== null): ?>
                        <dt>Out of service for</dt>
                        <dd><?= e(Dates::humanDuration((int) $workOrder['downtime_minutes'])) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($workOrder['description'])): ?>
                    <hr>
                    <h3>Details</h3>
                    <p><?= nl2br_e((string) $workOrder['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($workOrder['resolution'])): ?>
                    <hr>
                    <h3>How it was resolved</h3>
                    <p><?= nl2br_e((string) $workOrder['resolution']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($logs !== []): ?>
            <div class="card">
                <div class="card-header"><h2 class="card-title"><?= icon('wrench', '', 18) ?> Work logged against this</h2></div>
                <ul class="activity-list">
                    <?php foreach ($logs as $log): ?>
                        <li class="activity-item">
                            <span class="activity-body">
                                <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>"><?= e((string) $log['title']) ?></a>
                                <div class="text-sm text-muted"><?= e(Dates::datetime((string) $log['performed_at'])) ?></div>
                            </span>
                            <?php if (costs_visible()): ?>
                                <span class="tabular"><?= e(money($log['total_cost'], true)) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (feature_on('photos')): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('paperclip', '', 18) ?> Photos and documents</h2>
            </div>
            <div class="card-body">
                <?php View::partial('attachments', [
                    'attachments' => $attachments,
                    'entityType'  => 'work_order',
                    'entityId'    => $woId,
                    'canUpload'   => Acl::canWorkOnWorkOrder($workOrder),
                    'canDelete'   => Acl::canWorkOnWorkOrder($workOrder),
                    'uploadUrl'   => url('workorder-view.php', ['id' => $woId]),
                ]); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('mail', '', 18) ?> Updates</h2>
            </div>
            <div class="card-body">
                <?php if ($comments === []): ?>
                    <p class="text-subtle">No updates yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment<?= (int) $comment['is_status_change'] === 1 ? ' is-system' : '' ?>">
                            <?php View::partial('avatar', ['user' => $comment, 'size' => 'sm']); ?>
                            <div class="comment-body">
                                <div class="comment-head">
                                    <span class="comment-author"><?= e(user_name($comment)) ?></span>
                                    <span class="comment-time"><?= e(Dates::datetime((string) $comment['created_at'])) ?></span>
                                </div>
                                <div class="comment-text"><?= nl2br_e((string) $comment['comment']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (Acl::canWorkOnWorkOrder($workOrder)): ?>
                <form method="post" action="<?= e(url('workorder-view.php', ['id' => $woId])) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="comment">
                    <?php View::partial('form-field', [
                        'name'        => 'comment',
                        'label'       => 'Add an update',
                        'type'        => 'textarea',
                        'value'       => '',
                        'rows'        => 3,
                        'placeholder' => 'Parts are on order, due Wednesday.',
                        'noOld'       => true,
                        'attrs'       => ['maxlength' => 5000, 'data-autogrow' => true],
                    ]); ?>
                    <button type="submit" class="btn btn-primary btn-sm">Post update</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <?php if ($canWork): ?>
            <details class="card">
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h3 class="card-title">Change something else</h3>
                    <span class="text-sm text-muted"><?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <form method="post" action="<?= e(url('workorder-view.php', ['id' => $woId])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">

                        <?php
                        $transitions = Status::workOrderTransitions((string) $workOrder['status']);

                        // Do not offer what the server would refuse: closing
                        // and cancelling each need their own permission.
                        foreach (array_keys($transitions) as $candidate) {
                            if ((string) $candidate === 'completed' && !Acl::canCloseWorkOrder($workOrder)) {
                                unset($transitions[$candidate]);
                            }

                            if ((string) $candidate === 'cancelled' && !Acl::canCancelWorkOrder($workOrder)) {
                                unset($transitions[$candidate]);
                            }
                        }
                        ?>

                        <?php if ($transitions === []): ?>
                            <p class="text-subtle">No further status changes are available.</p>
                        <?php else: ?>
                            <?php View::partial('form-field', [
                                'name'    => 'status',
                                'label'   => 'Change status to',
                                'type'    => 'select',
                                'value'   => (string) $workOrder['status'],
                                'options' => $transitions,
                                'empty'   => 'Leave it as it is (' . Status::label((string) $workOrder['status'], 'workorder') . ')',
                                'noOld'   => true,
                            ]); ?>

                            <?php View::partial('form-field', [
                                'name'        => 'resolution',
                                'label'       => 'What was done?',
                                'type'        => 'textarea',
                                'value'       => (string) ($workOrder['resolution'] ?? ''),
                                'rows'        => 3,
                                'hint'        => 'Clearing this box removes what is written there.',
                                'noOld'       => true,
                                'attrs'       => ['maxlength' => 5000],
                            ]); ?>

                            <?php if (feature_on('downtime')): ?>
                                <?php View::partial('form-field', [
                                    'name'   => 'downtime_minutes',
                                    'label'  => 'Out of service for',
                                    'type'   => 'number',
                                    'value'  => $workOrder['downtime_minutes'],
                                    'suffix' => 'minutes',
                                    'noOld'  => true,
                                    'attrs'  => ['min' => 0],
                                ]); ?>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-secondary btn-block">Save this change</button>
                        <?php endif; ?>
                    </form>
                </div>
            </details>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Assignment</h3></div>
            <div class="card-body">
                <?php if (can('workorders.assign')): ?>
                    <form method="post" action="<?= e(url('workorder-view.php', ['id' => $woId])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign">
                        <?php View::partial('form-field', [
                            'name'    => 'assigned_to',
                            'label'   => 'Assigned to',
                            'type'    => 'select',
                            'value'   => $workOrder['assigned_to'],
                            'options' => $assignees,
                            'empty'   => 'Nobody',
                            'noOld'   => true,
                        ]); ?>
                        <button type="submit" class="btn btn-secondary btn-block">Save</button>
                    </form>
                <?php elseif (!empty($workOrder['assignee_id'])): ?>
                    <?php View::partial('user-chip', ['user' => $workOrder, 'showRole' => true, 'size' => '']); ?>
                <?php else: ?>
                    <p class="text-subtle">Nobody assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (can('workorders.delete')): ?>
            <div class="card is-danger">
                <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
                <div class="card-body">
                    <?php View::partial('confirm-delete', [
                        'url'         => url('workorder-view.php', ['id' => $woId]),
                        'label'       => 'Delete this work order',
                        'message'     => 'Delete ' . (string) $workOrder['wo_number'] . '? Its comments go with it.',
                        'buttonClass' => 'btn btn-danger-outline btn-block',
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
