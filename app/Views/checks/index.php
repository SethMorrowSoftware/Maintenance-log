<?php
/**
 * Today's checks, and how they have been going.
 *
 * Today: one card per checklist expected on the day, in the order they fall
 * due, each with its machines (or its area) and what happened to them. Built
 * to be read on a phone by whoever is about to open up, and to be worked from
 * by whoever does the checking.
 *
 * History: how reliably each list, each area and each person gets it done.
 *
 * Variables (today): $date, $today, $board, $totals, $limited, $home
 * Variables (history): $range, $history, $limited
 */

use App\Checks;
use App\Dates;
use App\Status;
use App\View;

$home    = $home ?? false;
$limited = $limited ?? false;
$canRun  = can('inspections.perform');
$isToday = ($date ?? '') === ($today ?? '');
?>

<?php View::partial('tabs', [
    'tabs' => [
        'today'   => ['label' => $home ? 'Today' : "Today's checks", 'url' => url('checks.php'), 'icon' => 'checklist'],
        'history' => ['label' => 'History', 'url' => url('checks.php', ['tab' => 'history']), 'icon' => 'chart-bar'],
    ],
    'active' => $tab,
]); ?>

<?php if ($tab === 'today'): ?>

    <?php
    $prevDay = Dates::parseDate($date);
    $prev    = $prevDay === null ? $date : $prevDay->modify('-1 day')->format(Dates::DB_DATE);
    $next    = $prevDay === null ? $date : $prevDay->modify('+1 day')->format(Dates::DB_DATE);
    ?>

    <?php // ==================== Which day ==================== ?>
    <form method="get" action="<?= e(url('checks.php')) ?>" class="day-nav no-print">
        <a class="btn btn-secondary btn-sm btn-icon" href="<?= e(url('checks.php', ['date' => $prev])) ?>"
           aria-label="The day before" title="The day before"><?= icon('chevron-left', '', 16) ?></a>
        <label class="sr-only" for="check-date">Day</label>
        <input type="date" class="form-input" id="check-date" name="date" value="<?= attr($date) ?>" max="<?= attr($today) ?>" data-autosubmit>
        <?php if ($isToday): ?>
            <span class="btn btn-secondary btn-sm btn-icon" aria-disabled="true" style="opacity:.5"><?= icon('chevron-right', '', 16) ?></span>
        <?php else: ?>
            <a class="btn btn-secondary btn-sm btn-icon" href="<?= e(url('checks.php', ['date' => $next])) ?>"
               aria-label="The day after" title="The day after"><?= icon('chevron-right', '', 16) ?></a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('checks.php')) ?>">Today</a>
        <?php endif; ?>
    </form>

    <?php // ==================== The numbers ==================== ?>
    <?php if ($board !== []): ?>
        <div class="stat-grid stat-grid-4">
            <?php View::partial('stat-card', ['label' => 'Done', 'value' => (string) $totals['done'] . ' of ' . $totals['total'], 'icon' => 'check-circle', 'tone' => 'ok']); ?>
            <?php View::partial('stat-card', ['label' => $isToday ? 'Still to do' : 'Not done', 'value' => (string) $totals['missing'], 'icon' => 'clipboard', 'tone' => $totals['missing'] > 0 ? 'warn' : 'muted']); ?>
            <?php View::partial('stat-card', ['label' => 'Done late', 'value' => (string) $totals['late'], 'icon' => 'clock', 'tone' => $totals['late'] > 0 ? 'warn' : 'muted']); ?>
            <?php View::partial('stat-card', ['label' => $isToday ? 'Past due time' : 'Missed', 'value' => (string) $totals['overdue'], 'icon' => 'alert-triangle', 'tone' => $totals['overdue'] > 0 ? 'danger' : 'muted']); ?>
        </div>
    <?php endif; ?>

    <?php if ($board === []): ?>
        <?php View::partial('empty-state', [
            'icon'        => 'checklist',
            'title'       => $isToday ? 'Nothing is expected today' : 'Nothing was expected that day',
            'message'     => $limited
                ? 'No checklist in your area is due today. If that seems wrong, ask whoever looks after checklists.'
                : 'A checklist shows up here when it is daily, or when it has a due time on this day of the week. '
                  . 'Open a checklist and give it a time under "When it should be done".',
            'actionLabel' => can('checklists.manage') ? 'Checklists' : '',
            'actionUrl'   => can('checklists.manage') ? url('checklists.php') : '',
        ]); ?>
    <?php endif; ?>

    <?php // ==================== One card per checklist ==================== ?>
    <?php foreach ($board as $group): ?>
        <?php
        $checklist = $group['checklist'];
        $status    = (string) $group['status'];
        $isArea    = (string) $checklist['applies_to'] === 'location';
        $percent   = $group['total'] > 0 ? (int) round($group['done'] / $group['total'] * 100) : 0;
        $barTone   = $status === 'done' ? 'ok' : (in_array($status, ['overdue', 'missed'], true) ? 'danger' : ($status === 'late' ? 'warn' : ''));

        $covers = $isArea
            ? (string) ($checklist['location_name'] ?? 'Area')
            : ((string) $checklist['applies_to'] === 'all'
                ? 'Every ' . asset_word()
                : ($group['total'] . ' ' . ($group['total'] === 1 ? asset_word() : asset_word(true))));

        $when = !empty($checklist['due_time'])
            ? 'by ' . Checks::timeLabel((string) $checklist['due_time'])
            : 'any time today';
        ?>
        <div class="card check-group is-<?= e($status) ?>" id="checklist-<?= (int) $checklist['id'] ?>">
            <div class="card-header">
                <div>
                    <h2 class="card-title">
                        <?= icon($isArea ? 'map-pin' : 'clipboard-check', '', 18) ?>
                        <?= e((string) $checklist['name']) ?>
                    </h2>
                    <p class="card-subtitle">
                        <?= e($covers) ?> &middot; <strong><?= e($when) ?></strong>
                        <?php if (!empty($checklist['due_time']) && (string) $checklist['due_days'] !== '1234567'): ?>
                            &middot; <?= e(Checks::daysLabel((string) $checklist['due_days'])) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="check-summary">
                    <?= Status::badge($status, 'check') ?>
                    <?php if ($group['total'] > 1): ?>
                        <span class="check-count"><?= (int) $group['done'] ?> of <?= (int) $group['total'] ?> done</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($group['total'] > 1): ?>
                <div class="check-progress">
                    <div class="progress"><div class="progress-bar<?= $barTone !== '' ? ' tone-' . $barTone : '' ?>" style="width:<?= $percent ?>%"></div></div>
                </div>
            <?php endif; ?>

            <ul class="check-rows">
                <?php foreach ($group['rows'] as $row): ?>
                    <?php
                    $rowStatus  = (string) $row['status'];
                    $inspection = $row['inspection'];
                    $asset      = $row['asset'];
                    $doneBy     = $inspection === null ? '' : trim((string) ($inspection['first_name'] ?? '') . ' ' . (string) ($inspection['last_name'] ?? ''));

                    if ($doneBy === '' && $inspection !== null) {
                        $doneBy = (string) ($inspection['username'] ?? '');
                    }

                    // What to offer: start it, carry on with it, or look at it.
                    $action = '';

                    if (in_array($rowStatus, ['done', 'late'], true) && $inspection !== null) {
                        $action = '<a class="btn btn-ghost btn-sm" href="' . e(url('inspection-view.php', ['id' => (int) $inspection['id']])) . '">View</a>';
                    } elseif ($rowStatus === 'in_progress' && $inspection !== null && $canRun
                        && ((int) $inspection['user_id'] === (int) user_id() || can('inspections.delete'))) {
                        $action = '<a class="btn btn-secondary btn-sm" href="' . e(url('inspection-run.php', ['id' => (int) $inspection['id']])) . '">Continue</a>';
                    } elseif ($canRun && $isToday && !in_array($rowStatus, ['done', 'late', 'in_progress'], true)) {
                        $target = $asset === null
                            ? ['checklist_id' => (int) $checklist['id']]
                            : ['asset_id' => (int) $asset['id'], 'checklist_id' => (int) $checklist['id']];
                        $action = '<a class="btn btn-primary btn-sm" href="' . e(url('inspection-run.php', $target)) . '">'
                            . icon('play', '', 14) . ' Start</a>';
                    }
                    ?>
                    <li class="check-row is-<?= e($rowStatus) ?>">
                        <span class="check-mark" aria-hidden="true">
                            <?php if ($rowStatus === 'done'): ?>
                                <?= icon('check', '', 16) ?>
                            <?php elseif ($rowStatus === 'late'): ?>
                                <?= icon('clock', '', 16) ?>
                            <?php elseif (in_array($rowStatus, ['overdue', 'missed'], true)): ?>
                                <?= icon('x', '', 16) ?>
                            <?php elseif ($rowStatus === 'in_progress'): ?>
                                <?= icon('edit', '', 16) ?>
                            <?php else: ?>
                                &nbsp;
                            <?php endif; ?>
                        </span>
                        <span class="check-body">
                            <span class="check-name">
                                <?php if ($asset !== null): ?>
                                    <?= e((string) $asset['name']) ?>
                                    <?php if ((string) ($asset['location_name'] ?? '') !== '' && !$limited): ?>
                                        <small class="text-subtle"><?= e((string) $asset['location_name']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= e((string) ($checklist['location_name'] ?? 'Area')) ?>
                                <?php endif; ?>
                            </span>
                            <span class="check-when text-sm">
                                <?php if (in_array($rowStatus, ['done', 'late'], true) && $inspection !== null): ?>
                                    <?= $rowStatus === 'late' ? '<span class="text-warn">Done late</span>' : 'Done' ?>
                                    at <?= e(Dates::time((string) ($inspection['completed_at'] ?? $inspection['started_at']))) ?>
                                    <?php if ($doneBy !== ''): ?>by <?= e($doneBy) ?><?php endif; ?>
                                    <?php if ((int) ($inspection['failed_count'] ?? 0) > 0): ?>
                                        &middot; <span class="text-danger"><?= (int) $inspection['failed_count'] ?> failed<?= (int) ($inspection['critical_failed'] ?? 0) === 1 ? ', safety item' : '' ?></span>
                                    <?php endif; ?>
                                <?php elseif ($rowStatus === 'in_progress' && $inspection !== null): ?>
                                    Started <?= e(Dates::time((string) $inspection['started_at'])) ?><?php if ($doneBy !== ''): ?> by <?= e($doneBy) ?><?php endif; ?>, not finished
                                <?php elseif ($rowStatus === 'overdue'): ?>
                                    <span class="text-danger">Not finished by <?= e(Dates::time((string) $row['due_at'])) ?></span>
                                <?php elseif ($rowStatus === 'missed'): ?>
                                    <span class="text-danger">Not done</span>
                                <?php elseif ($rowStatus === 'due'): ?>
                                    Due by <?= e(Dates::time((string) $row['due_at'])) ?>
                                <?php else: ?>
                                    Any time today
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="check-action"><?= $action ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($group['alerts'] !== []): ?>
                <div class="card-footer check-alerts text-sm text-muted">
                    <?= icon('bell', '', 14) ?>
                    <?php
                    $said = [];

                    foreach ($group['alerts'] as $alert) {
                        $kindLabel = ['reminder' => 'Reminder', 'missed' => 'Not-finished alert', 'escalation' => 'Escalation'][(string) $alert['kind']] ?? (string) $alert['kind'];
                        $said[]    = $kindLabel . ' at ' . Dates::time((string) $alert['sent_at'])
                            . ((int) $alert['ok'] === 1
                                ? ((string) $alert['channel'] !== '' ? ' to ' . (string) $alert['channel'] : '')
                                : ' (Slack failed: ' . (string) $alert['detail'] . ')');
                    }
                    ?>
                    <?= e(implode(' · ', $said)) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php else: ?>

    <?php // ==================== History ==================== ?>
    <div class="table-toolbar no-print">
        <div class="btn-group">
            <?php foreach (['7' => 'Last 7 days', '14' => 'Last 14 days', '30' => 'Last 30 days', '90' => 'Last 90 days'] as $key => $label): ?>
                <a class="btn btn-secondary btn-sm<?= $range === $key ? ' is-active' : '' ?>"
                   href="<?= e(url('checks.php', ['tab' => 'history', 'range' => $key])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <span class="text-sm text-muted">
            <?= e(Dates::dateOnly((string) $history['from'])) ?> to <?= e(Dates::dateOnly((string) $history['to'])) ?>
            &middot; <?= (int) $history['days'] ?> day<?= (int) $history['days'] === 1 ? '' : 's' ?>
        </span>
    </div>

    <?php $t = $history['totals']; ?>
    <div class="stat-grid stat-grid-4">
        <?php View::partial('stat-card', ['label' => 'Checks expected', 'value' => (string) $t['expected'], 'icon' => 'checklist', 'tone' => 'brand']); ?>
        <?php View::partial('stat-card', ['label' => 'Done', 'value' => $t['done_rate'] === null ? '—' : $t['done_rate'] . '%', 'sub' => $t['done'] . ' of ' . ($t['expected'] - $t['open']), 'icon' => 'check-circle', 'tone' => 'ok']); ?>
        <?php View::partial('stat-card', ['label' => 'On time', 'value' => $t['on_time_rate'] === null ? '—' : $t['on_time_rate'] . '%', 'sub' => $t['late'] . ' done late', 'icon' => 'clock', 'tone' => $t['late'] > 0 ? 'warn' : 'ok']); ?>
        <?php View::partial('stat-card', ['label' => 'Missed', 'value' => (string) $t['missed'], 'sub' => $t['open'] > 0 ? $t['open'] . ' still open today' : '', 'icon' => 'alert-triangle', 'tone' => $t['missed'] > 0 ? 'danger' : 'muted']); ?>
    </div>

    <?php if ($history['checklists'] === []): ?>
        <?php View::partial('empty-state', [
            'icon'    => 'chart-bar',
            'title'   => 'Nothing to report yet',
            'message' => 'Once checklists with a due time (or daily ones) have had a few days to run, this shows how they went.',
        ]); ?>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('checklist', '', 18) ?> By checklist</h2>
                    <p class="card-subtitle">Most missed first. A check is late when it finished after its due time; missed when it was never finished that day.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table is-stacked">
                    <thead>
                        <tr>
                            <th>Checklist</th>
                            <th class="is-numeric">Expected</th>
                            <th class="is-numeric">Done</th>
                            <th class="is-numeric">On time</th>
                            <th class="is-numeric">Late</th>
                            <th class="is-numeric">Missed</th>
                            <th class="is-numeric">Done %</th>
                            <th class="is-numeric">On time %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history['checklists'] as $row): ?>
                            <tr class="<?= $row['missed'] > 0 ? 'is-warn' : '' ?>">
                                <td data-label="Checklist" class="is-row-title">
                                    <?php if (can('checklists.manage') && !empty($row['id'])): ?>
                                        <a class="cell-primary" href="<?= e(url('checklist-edit.php', ['id' => (int) $row['id']])) ?>"><?= e((string) $row['name']) ?></a>
                                    <?php else: ?>
                                        <span class="cell-primary"><?= e((string) $row['name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Expected" class="is-numeric"><?= (int) $row['expected'] ?></td>
                                <td data-label="Done" class="is-numeric"><?= (int) $row['done'] ?></td>
                                <td data-label="On time" class="is-numeric"><?= (int) $row['on_time'] ?></td>
                                <td data-label="Late" class="is-numeric"><?= $row['late'] > 0 ? '<span class="text-warn">' . (int) $row['late'] . '</span>' : '0' ?></td>
                                <td data-label="Missed" class="is-numeric"><?= $row['missed'] > 0 ? '<span class="text-danger">' . (int) $row['missed'] . '</span>' : '0' ?></td>
                                <td data-label="Done %" class="is-numeric"><?= $row['done_rate'] === null ? '—' : e((string) $row['done_rate']) . '%' ?></td>
                                <td data-label="On time %" class="is-numeric"><?= $row['on_time_rate'] === null ? '—' : e((string) $row['on_time_rate']) . '%' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('map-pin', '', 18) ?> By area</h2>
                </div>
                <div class="table-wrap">
                    <table class="table is-stacked">
                        <thead>
                            <tr><th>Area</th><th class="is-numeric">Expected</th><th class="is-numeric">Done</th><th class="is-numeric">Late</th><th class="is-numeric">Missed</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history['areas'] as $row): ?>
                                <tr>
                                    <td data-label="Area" class="is-row-title"><span class="cell-primary"><?= e((string) $row['name']) ?></span></td>
                                    <td data-label="Expected" class="is-numeric"><?= (int) $row['expected'] ?></td>
                                    <td data-label="Done" class="is-numeric"><?= (int) $row['done'] ?><?= $row['done_rate'] !== null ? ' <span class="text-subtle">(' . e((string) $row['done_rate']) . '%)</span>' : '' ?></td>
                                    <td data-label="Late" class="is-numeric"><?= (int) $row['late'] ?></td>
                                    <td data-label="Missed" class="is-numeric"><?= $row['missed'] > 0 ? '<span class="text-danger">' . (int) $row['missed'] . '</span>' : '0' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('users', '', 18) ?> By person</h2>
                        <p class="card-subtitle">Who finished the checks</p>
                    </div>
                </div>
                <?php if ($history['people'] === []): ?>
                    <div class="card-body"><p class="text-muted" style="margin:0">Nobody has finished a check in this period.</p></div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead>
                                <tr><th>Person</th><th class="is-numeric">Done</th><th class="is-numeric">Late</th><th class="is-numeric">With failures</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history['people'] as $person): ?>
                                    <tr>
                                        <td data-label="Person" class="is-row-title"><?php View::partial('user-chip', ['user' => $person, 'showRole' => false]); ?></td>
                                        <td data-label="Done" class="is-numeric"><?= (int) $person['done'] ?></td>
                                        <td data-label="Late" class="is-numeric"><?= (int) $person['late'] > 0 ? '<span class="text-warn">' . (int) $person['late'] . '</span>' : '0' ?></td>
                                        <td data-label="With failures" class="is-numeric"><?= (int) $person['failed'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>
