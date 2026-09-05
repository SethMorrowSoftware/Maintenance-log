<?php

use App\Dates;
use App\Status;
use App\View;
?>

<div class="card">
    <div class="table-toolbar">
        <div class="btn-group">
            <?php foreach (['' => 'All', 'passed' => 'Passed', 'failed' => 'Failed', 'in_progress' => 'Unfinished'] as $key => $label): ?>
                <a class="btn btn-secondary btn-sm<?= (string) $filters['status'] === (string) $key ? ' is-active' : '' ?>"
                   href="<?= e(url('inspections.php', array_merge($filters, ['status' => $key, 'page' => null]))) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php View::partial('filter-bar', [
        'action'  => 'inspections.php',
        'filters' => [
            'asset_id'     => ['label' => 'Machine', 'type' => 'select', 'value' => $filters['asset_id'], 'options' => $assets, 'empty' => 'All machines'],
            'checklist_id' => ['label' => 'Checklist', 'type' => 'select', 'value' => $filters['checklist_id'], 'options' => $checklists, 'empty' => 'All checklists'],
            'from'         => ['label' => 'From', 'type' => 'date', 'value' => $filters['from']],
            'to'           => ['label' => 'To', 'type' => 'date', 'value' => $filters['to']],
        ],
        'hidden'   => ['status' => $filters['status']],
        'resetUrl' => url('inspections.php', ['status' => $filters['status']]),
    ]); ?>

    <?php if ($rows === []): ?>
        <?php View::partial('empty-state', [
            'icon'        => 'clipboard-check',
            'title'       => 'No inspections recorded',
            'message'     => 'Daily checks before opening are what keep a ride safe and the paperwork straight. Run one and it appears here.',
            'actionLabel' => can('inspections.perform') ? 'Run an inspection' : '',
            'actionUrl'   => can('inspections.perform') ? url('inspection-run.php') : '',
            'actionIcon'  => 'clipboard-check',
        ]); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr><th>When</th><th>Machine</th><th>Checklist</th><th>Result</th><th>Inspector</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr class="<?= (int) $row['critical_failed'] === 1 ? 'is-danger' : ((int) $row['failed_count'] > 0 ? 'is-warn' : '') ?>"
                            data-row-href="<?= e(url('inspection-view.php', ['id' => (int) $row['id']])) ?>">
                            <td data-label="When" class="is-row-title">
                                <a href="<?= e(url((string) $row['status'] === 'in_progress' ? 'inspection-run.php' : 'inspection-view.php', ['id' => (int) $row['id']])) ?>">
                                    <?= e(Dates::date((string) $row['started_at'])) ?>
                                </a>
                                <span class="cell-secondary"><?= e(Dates::time((string) $row['started_at'])) ?></span>
                            </td>
                            <td data-label="Machine">
                                <a href="<?= e(url('asset-view.php', ['id' => (int) $row['asset_id']])) ?>">
                                    <?= e((string) $row['asset_name']) ?>
                                </a>
                                <span class="cell-secondary"><?= e((string) $row['asset_tag']) ?></span>
                            </td>
                            <td data-label="Checklist"><?= e((string) $row['checklist_name']) ?></td>
                            <td data-label="Result">
                                <?php View::partial('status-badge', ['value' => (string) $row['status'], 'vocabulary' => 'inspection']); ?>
                                <span class="cell-secondary">
                                    <?= (int) $row['passed_count'] ?> passed<?php if ((int) $row['failed_count'] > 0): ?>,
                                        <span style="color:var(--danger)"><?= (int) $row['failed_count'] ?> failed</span>
                                    <?php endif; ?>
                                    <?php if ((int) $row['critical_failed'] === 1): ?>
                                        &middot; <strong style="color:var(--danger)">safety item</strong>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Inspector"><?php View::partial('user-chip', ['user' => $row]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php View::partial('pagination', ['paginator' => $paginator, 'singular' => 'inspection']); ?>
</div>
