<?php
/**
 * The checklist templates.
 *
 * Admin territory, but written the same way as everything else: what it is,
 * what it covers, and how often — no jargon.
 */

use App\Dates;
use App\Status;
use App\View;

$canManage = can('checklists.manage');
?>

<?php if ($rows === []): ?>
    <?php View::partial('empty-state', [
        'icon'        => 'checklist',
        'title'       => 'No checklists yet',
        'message'     => 'A checklist is the list of things somebody works through before a kart '
                       . 'or a ride opens for the day. Make one and it appears automatically when '
                       . 'a matching machine is inspected.',
        'actionLabel' => $canManage ? 'New checklist' : '',
        'actionUrl'   => $canManage ? url('checklist-edit.php') : '',
    ]); ?>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr>
                        <th>Checklist</th>
                        <th>Covers</th>
                        <th>How often</th>
                        <th class="is-numeric">Items</th>
                        <th>Last used</th>
                        <th class="is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $rowId    = (int) $row['id'];
                        $isActive = (int) $row['is_active'] === 1;
                        ?>
                        <tr<?= $isActive ? '' : ' class="is-dimmed"' ?>>
                            <td data-label="Checklist">
                                <?php if ($canManage): ?>
                                    <a class="cell-primary" href="<?= e(url('checklist-edit.php', ['id' => $rowId])) ?>">
                                        <?= e((string) $row['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="cell-primary"><?= e((string) $row['name']) ?></span>
                                <?php endif; ?>
                                <?php if (!$isActive): ?>
                                    <span class="badge badge-muted">Switched off</span>
                                <?php endif; ?>
                                <?php if (!empty($row['description'])): ?>
                                    <span class="cell-secondary"><?= e(str_limit((string) $row['description'], 90)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Covers">
                                <?php if ((string) $row['applies_to'] === 'all'): ?>
                                    Every machine
                                <?php elseif ((string) $row['applies_to'] === 'category'): ?>
                                    <?= e((string) ($row['category_name'] ?? 'a category')) ?>
                                <?php else: ?>
                                    <?= e((string) ($row['asset_name'] ?? 'one machine')) ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="How often">
                                <?= e(Status::label((string) $row['frequency'], 'frequency')) ?>
                                <?php if (!empty($row['estimated_minutes'])): ?>
                                    <span class="cell-secondary">
                                        about <?= e(Dates::humanDuration((int) $row['estimated_minutes'])) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Items" class="is-numeric">
                                <?= (int) $row['item_count'] ?>
                                <?php if ((int) $row['critical_count'] > 0): ?>
                                    <span class="cell-secondary">
                                        <?= (int) $row['critical_count'] ?> safety
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Last used">
                                <?php if (!empty($row['last_run'])): ?>
                                    <?= e(Dates::ago((string) $row['last_run'])) ?>
                                    <span class="cell-secondary">
                                        <?= (int) $row['run_count'] ?> time<?= (int) $row['run_count'] === 1 ? '' : 's' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-subtle">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="is-actions">
                                <?php if ($canManage): ?>
                                    <div class="flex gap-1 justify-end flex-wrap">
                                        <a class="btn btn-ghost btn-sm"
                                           href="<?= e(url('checklist-edit.php', ['id' => $rowId])) ?>">
                                            <?= icon('edit', '', 15) ?> Edit
                                        </a>

                                        <form method="post" action="<?= e(url('checklists.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $rowId ?>">
                                            <input type="hidden" name="action" value="duplicate">
                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                <?= icon('copy', '', 15) ?> Copy
                                            </button>
                                        </form>

                                        <form method="post" action="<?= e(url('checklists.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $rowId ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <button type="submit" class="btn btn-ghost btn-sm">
                                                <?= $isActive ? 'Switch off' : 'Switch on' ?>
                                            </button>
                                        </form>

                                        <?php View::partial('confirm-delete', [
                                            'url'         => url('checklists.php'),
                                            'id'          => $rowId,
                                            'label'       => 'Delete',
                                            'message'     => 'Delete “' . (string) $row['name'] . '”? '
                                                           . 'Inspections already carried out keep their own record.',
                                            'buttonClass' => 'btn btn-ghost btn-sm text-danger',
                                            'iconOnly'    => false,
                                        ]); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-sm text-muted mt-4">
        A checklist shows up on an inspection when it matches the machine being checked.
        The most specific one wins: a checklist for one particular kart beats one for
        all go-karts, which beats one for everything.
    </p>
<?php endif; ?>
