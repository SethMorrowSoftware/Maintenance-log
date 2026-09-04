<?php
/**
 * The audit log.
 *
 * A row is only interesting when something looks wrong, so the list stays quiet
 * and the "what exactly changed" detail folds out of the row rather than living
 * on a page of its own.
 */

use App\Audit;
use App\Dates;
use App\View;

/** Values in the log are usually scalars, but never assume it. */
$flat = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }

    if (is_array($value)) {
        return implode(', ', array_map('strval', array_filter($value, 'is_scalar')));
    }

    return is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
};

$linkFor = static function (string $entityType, $entityId): ?string {
    if ($entityId === null || (int) $entityId <= 0) {
        return null;
    }

    $map = [
        'asset'           => 'asset-view.php',
        'maintenance_log' => 'log-view.php',
        'work_order'      => 'workorder-view.php',
        'inspection'      => 'inspection-view.php',
        'part'            => 'part-view.php',
        'user'            => 'user-edit.php',
        'checklist'       => 'checklist-edit.php',
        'schedule'        => 'schedule-edit.php',
    ];

    return isset($map[$entityType]) ? url($map[$entityType], ['id' => (int) $entityId]) : null;
};
?>

<?php View::partial('filter-bar', [
    'action'  => 'audit.php',
    'filters' => [
        'q' => [
            'label'       => 'Search',
            'value'       => $filters['q'],
            'placeholder' => 'What happened, or whose name',
        ],
        'user_id' => [
            'label'   => 'Who',
            'type'    => 'select',
            'value'   => $filters['user_id'],
            'options' => $people,
            'empty'   => 'Anyone',
        ],
        'action' => [
            'label'   => 'What',
            'type'    => 'select',
            'value'   => $filters['action'],
            'options' => $actions,
            'empty'   => 'Anything',
        ],
        'entity_type' => [
            'label'   => 'Record type',
            'type'    => 'select',
            'value'   => $filters['entity_type'],
            'options' => $entities,
            'empty'   => 'Any',
        ],
        'from' => ['label' => 'From', 'type' => 'date', 'value' => $filters['from']],
        'to'   => ['label' => 'To',   'type' => 'date', 'value' => $filters['to']],
    ],
]); ?>

<?php if ($entries === []): ?>
    <?php View::partial('empty-state', [
        'icon'    => 'history',
        'title'   => 'Nothing recorded',
        'message' => 'No activity matches those filters.',
    ]); ?>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table is-stacked table-compact">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Who</th>
                        <th>What happened</th>
                        <th class="is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <?php
                        $before = Audit::decode($entry['old_values'] ?? null);
                        $after  = Audit::decode($entry['new_values'] ?? null);
                        $link   = $linkFor((string) $entry['entity_type'], $entry['entity_id']);
                        $tone   = Audit::actionTone((string) $entry['action']);
                        ?>
                        <tr>
                            <td data-label="When" style="white-space:nowrap">
                                <?= e(Dates::datetime((string) $entry['created_at'])) ?>
                                <span class="cell-secondary"><?= e(Dates::ago((string) $entry['created_at'])) ?></span>
                            </td>
                            <td data-label="Who">
                                <?php if (!empty($entry['username'])): ?>
                                    <?php View::partial('user-chip', ['user' => $entry, 'showRole' => false]); ?>
                                <?php else: ?>
                                    <?= e((string) $entry['user_name'] ?: 'System') ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="What happened">
                                <span class="badge badge-<?= e($tone) ?>">
                                    <?= e(Audit::actionLabel((string) $entry['action'])) ?>
                                </span>
                                <?= e((string) $entry['description']) ?>

                                <?php if ($before !== [] || $after !== []): ?>
                                    <details class="audit-detail">
                                        <summary>Exactly what changed</summary>
                                        <table class="table table-compact audit-diff">
                                            <thead>
                                                <tr><th>Field</th><th>Was</th><th>Now</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_keys($after + $before) as $field): ?>
                                                    <tr>
                                                        <td><?= e(\App\Str::label((string) $field)) ?></td>
                                                        <td class="text-muted">
                                                            <?= e(str_limit($flat($before[$field] ?? null), 120)) ?: '<span class="text-subtle">&mdash;</span>' ?>
                                                        </td>
                                                        <td>
                                                            <?= e(str_limit($flat($after[$field] ?? null), 120)) ?: '<span class="text-subtle">&mdash;</span>' ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                <?php endif; ?>

                                <?php if ((string) $entry['ip_address'] !== ''): ?>
                                    <span class="cell-secondary">from <?= e((string) $entry['ip_address']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="is-actions">
                                <?php if ($link !== null): ?>
                                    <a class="btn btn-ghost btn-sm" href="<?= e($link) ?>">
                                        Open <?= icon('arrow-right', '', 14) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php View::partial('pagination', [
        'paginator' => $paginator,
        'singular'  => 'entry',
        'plural'    => 'entries',
    ]); ?>

    <p class="text-sm text-muted mt-3">
        <?= icon('info', '', 15) ?>
        Entries older than <?= (int) $retentionDays ?> days are cleared out by the nightly job.
        Change that under Settings &rarr; Security.
    </p>
<?php endif; ?>
