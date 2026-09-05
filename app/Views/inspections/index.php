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
                   href="<?= e(url('inspections.php', array_merge($_GET, ['status' => $key, 'page' => null]))) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php View::partial('filter-bar', [
        'action'  => 'inspections.php',
        'filters' => [
            'asset_id'     => ['label' => asset_word(false, true), 'type' => 'select', 'value' => $filters['asset_id'], 'options' => $assets, 'empty' => 'All ' . asset_word(true)],
            'location_id'  => ['label' => 'Area', 'type' => 'select', 'value' => $filters['location_id'], 'options' => $locations, 'empty' => 'All areas'],
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
                    <tr><th>When</th><th><?= e(asset_word(false, true)) ?></th><th>Checklist</th><th>Result</th><th>Inspector</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        // An unfinished check opens in the runner, which only the
                        // person doing it (or a manager) may use; for everyone else
                        // it is just a row until it is finished.
                        $unfinished = (string) $row['status'] === 'in_progress';
                        $rowUrl     = $unfinished
                            ? (can('inspections.perform') ? url('inspection-run.php', ['id' => (int) $row['id']]) : '')
                            : url('inspection-view.php', ['id' => (int) $row['id']]);
                        ?>
                        <tr class="<?= (int) $row['critical_failed'] === 1 ? 'is-danger' : ((int) $row['failed_count'] > 0 ? 'is-warn' : '') ?>"
                            <?= $rowUrl !== '' ? 'data-row-href="' . e($rowUrl) . '"' : '' ?>>
                            <td data-label="When" class="is-row-title">
                                <?php if ($rowUrl !== ''): ?>
                                    <a href="<?= e($rowUrl) ?>"><?= e(Dates::date((string) $row['started_at'])) ?></a>
                                <?php else: ?>
                                    <span class="cell-primary"><?= e(Dates::date((string) $row['started_at'])) ?></span>
                                <?php endif; ?>
                                <span class="cell-secondary"><?= e(Dates::time((string) $row['started_at'])) ?></span>
                            </td>
                            <td data-label="<?= attr(asset_word(false, true)) ?>">
                                <?php if ($row['asset_id'] === null): ?>
                                    <?= icon('map-pin', '', 14) ?> <?= e((string) ($row['location_name'] ?? 'Area')) ?>
                                    <span class="cell-secondary">Area check</span>
                                <?php elseif (can('assets.view')): ?>
                                    <a href="<?= e(url('asset-view.php', ['id' => (int) $row['asset_id']])) ?>">
                                        <?= e((string) $row['asset_name']) ?>
                                    </a>
                                    <span class="cell-secondary"><?= e((string) $row['asset_tag']) ?></span>
                                <?php else: ?>
                                    <?= e((string) $row['asset_name']) ?>
                                    <span class="cell-secondary"><?= e((string) $row['asset_tag']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Checklist">
                                <?= e((string) $row['checklist_name']) ?>
                                <?php if (!empty($row['due_at'])): ?>
                                    <span class="cell-secondary">
                                        due by <?= e(Dates::time((string) $row['due_at'])) ?>
                                        <?php if ((int) ($row['was_late'] ?? 0) === 1): ?>
                                            &middot; <span class="text-warn">late</span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
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
