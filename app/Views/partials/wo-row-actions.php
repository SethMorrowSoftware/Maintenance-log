<?php

/**
 * The one or two buttons a mechanic needs on a work order they are looking at
 * in a list: pick it up, or say it is done.
 *
 * The same markup serves the work order list and both dashboards, so a
 * mechanic never has to learn a second place to press Done. Every button is a
 * POST to the page it sits on; that page hands it to App\WorkOrderActions,
 * which re-checks the permission whatever this file chose to draw.
 *
 * A row cannot ask "shall I put the kart back in service?", so it answers the
 * question the same way the work order page pre-answers it: yes, unless this
 * was a safety issue. The server checks that answer again — if a second open
 * job still holds the machine out of service, it stays out of service.
 *
 * Variables:
 *   $workOrder  the row. Needs id, wo_number, status, assigned_to, reported_by,
 *               and (for the return-to-service default) asset_id,
 *               took_out_of_service, is_safety_issue.
 *   $showLog    also offer "Log the work" (default true)
 *   $class      wrapper class (default 'row-actions')
 */

use App\Acl;
use App\Status;

$workOrder = $workOrder ?? [];
$showLog   = $showLog   ?? true;
$class     = $class     ?? 'wo-row-actions';

if ($workOrder === [] || empty($workOrder['id'])) {
    return;
}

$woId     = (int) $workOrder['id'];
$woNumber = (string) ($workOrder['wo_number'] ?? '');
$isClosed = Status::isClosedWorkOrder((string) ($workOrder['status'] ?? ''));
$canWork  = !$isClosed && Acl::canWorkOnWorkOrder($workOrder);
$mine     = (int) ($workOrder['assigned_to'] ?? 0) === (int) (user()['id'] ?? 0);
$taken    = !empty($workOrder['assigned_to']);
$canClaim = !$isClosed && !$mine && Acl::canClaimWorkOrder($workOrder);

// Somebody else's job stays somebody else's job on a list: a mis-tap on a
// phone should not finish work you know nothing about. Open it to take it on.
if (!$canWork || ($taken && !$mine && !can('workorders.assign'))) {
    return;
}

$canClose = Acl::canCloseWorkOrder($workOrder);
$asset    = trim((string) ($workOrder['asset_name'] ?? ''));
$returns  = !empty($workOrder['asset_id'])
    && (int) ($workOrder['took_out_of_service'] ?? 0) === 1
    && (int) ($workOrder['is_safety_issue'] ?? 0) !== 1;

$confirm = $canClose
    ? 'Mark ' . $woNumber . ' as fixed?'
    : 'Mark ' . $woNumber . ' as finished and ask a manager to sign it off?';

if ($canClose && $returns) {
    $confirm .= ' ' . ($asset !== '' ? $asset : 'The ' . asset_word()) . ' goes back in service.';
}
?>
<div class="<?= e($class) ?>">
    <?php if ($canClaim): ?>
        <form method="post" action="" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="claim">
            <input type="hidden" name="id" value="<?= $woId ?>">
            <button type="submit" class="btn btn-secondary btn-sm row-action"
                    <?php if ($taken): ?>
                        data-confirm="<?= attr($woNumber . ' has somebody else\'s name on it. Take it over?') ?>"
                        data-confirm-title="Take it over"
                    <?php endif; ?>>
                <?= icon('user', '', 15) ?> <?= $taken ? 'Take it over' : 'I&rsquo;m on it' ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ($showLog && can('logs.create')): ?>
        <a class="btn btn-secondary btn-sm row-action"
           href="<?= e(url('log-edit.php', ['work_order_id' => $woId])) ?>">
            <?= icon('wrench', '', 15) ?> Log the work
        </a>
    <?php endif; ?>

    <form method="post" action="" class="inline-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="done">
        <input type="hidden" name="id" value="<?= $woId ?>">
        <?php if ($returns): ?>
            <input type="hidden" name="back_in_service" value="1">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm row-action"
                data-confirm="<?= attr($confirm) ?>"
                data-confirm-title="<?= attr($canClose ? 'Finished?' : 'Hand it over?') ?>"
                data-confirm-text="<?= attr($canClose ? 'It is fixed' : 'Hand it over') ?>">
            <?= icon('check-circle', '', 15) ?> Done
        </button>
    </form>
</div>
