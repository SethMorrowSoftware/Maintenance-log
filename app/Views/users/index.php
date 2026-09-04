<?php
/**
 * Everyone who can sign in.
 */

use App\Acl;
use App\Dates;
use App\View;

$canManage = can('users.manage');
$myId      = user_id();
?>

<?php View::partial('filter-bar', [
    'action'  => 'users.php',
    'filters' => [
        'q' => [
            'label'       => 'Search',
            'value'       => $filters['q'],
            'placeholder' => 'Name, username or email',
        ],
        'role' => [
            'label'   => 'Role',
            'type'    => 'select',
            'value'   => $filters['role'],
            'options' => $roles,
        ],
        'active' => [
            'label'   => 'Can sign in',
            'type'    => 'select',
            'value'   => $filters['active'],
            'options' => ['1' => 'Yes', '0' => 'No'],
            'empty'   => 'Either',
        ],
    ],
]); ?>

<?php if ($users === []): ?>
    <?php View::partial('empty-state', [
        'icon'        => 'users',
        'title'       => 'Nobody matches that',
        'message'     => 'Try a shorter search, or clear the filters.',
        'actionLabel' => $canManage ? 'Add someone' : '',
        'actionUrl'   => $canManage ? url('user-edit.php') : '',
    ]); ?>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th class="is-numeric">Jobs logged</th>
                        <th>Last signed in</th>
                        <th class="is-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $person): ?>
                        <?php
                        $personId = (int) $person['id'];
                        $isActive = (int) $person['is_active'] === 1;
                        $fullName = trim((string) $person['first_name'] . ' ' . (string) $person['last_name'])
                            ?: (string) $person['username'];
                        $locked = !empty($person['locked_until'])
                            && !Dates::isPast((string) $person['locked_until']);
                        ?>
                        <tr<?= $isActive ? '' : ' class="is-dimmed"' ?>>
                            <td data-label="Name">
                                <?php View::partial('user-chip', ['user' => $person, 'showRole' => false]); ?>
                                <?php if (!$isActive): ?>
                                    <span class="badge badge-muted">Cannot sign in</span>
                                <?php endif; ?>
                                <?php if ($locked): ?>
                                    <span class="badge badge-warn">Locked out</span>
                                <?php endif; ?>
                                <?php if ((int) $person['must_change_password'] === 1): ?>
                                    <span class="badge badge-info">Must change password</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Role">
                                <?= e(Acl::roleLabel((string) $person['role'])) ?>
                                <?php if ((string) $person['job_title'] !== ''): ?>
                                    <span class="cell-secondary"><?= e((string) $person['job_title']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Contact">
                                <a href="mailto:<?= attr((string) $person['email']) ?>">
                                    <?= e((string) $person['email']) ?>
                                </a>
                                <?php if ((string) $person['phone'] !== ''): ?>
                                    <span class="cell-secondary"><?= e((string) $person['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Jobs logged" class="is-numeric">
                                <?= e(num($person['log_count'])) ?>
                                <?php if ((int) $person['open_work'] > 0): ?>
                                    <span class="cell-secondary">
                                        <?= (int) $person['open_work'] ?> open work order<?= (int) $person['open_work'] === 1 ? '' : 's' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Last signed in">
                                <?php if (!empty($person['last_login_at'])): ?>
                                    <?= e(Dates::ago((string) $person['last_login_at'])) ?>
                                <?php else: ?>
                                    <span class="text-subtle">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="is-actions">
                                <?php if ($canManage): ?>
                                    <div class="flex gap-1 justify-end flex-wrap">
                                        <a class="btn btn-ghost btn-sm"
                                           href="<?= e(url('user-edit.php', ['id' => $personId])) ?>">
                                            <?= icon('edit', '', 15) ?> Edit
                                        </a>

                                        <?php if ($personId !== $myId): ?>
                                            <form method="post" action="<?= e(url('users.php', $_GET)) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= $personId ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <button type="submit" class="btn btn-ghost btn-sm">
                                                    <?= $isActive ? 'Switch off' : 'Switch on' ?>
                                                </button>
                                            </form>

                                            <?php View::partial('confirm-delete', [
                                                'url'         => url('users.php'),
                                                'id'          => $personId,
                                                'label'       => 'Remove',
                                                'message'     => 'Remove ' . $fullName . '? Their name stays on '
                                                               . 'every job they logged — this only stops them signing in '
                                                               . 'and takes them off this list.',
                                                'buttonClass' => 'btn btn-ghost btn-sm text-danger',
                                            ]); ?>
                                        <?php else: ?>
                                            <span class="text-subtle text-sm">That is you</span>
                                        <?php endif; ?>
                                    </div>
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
        'singular'  => 'person',
        'plural'    => 'people',
    ]); ?>
<?php endif; ?>
