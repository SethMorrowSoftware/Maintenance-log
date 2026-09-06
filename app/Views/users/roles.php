<?php
/**
 * The permission grid.
 *
 * Variables: $roles (key => label, least to most privileged), $descriptions,
 * $catalogue (group => [permission => label]), $matrix (role => permissions
 * in force), $defaults (role => built-in permissions), $offModules
 * (permission => module label, for switched-off modules), $headcount
 * (role => active people), $customised (bool)
 */

use App\Acl;

$roleKeys = array_keys($roles);
$total    = count(Acl::allPermissions());
?>

<form method="post" action="<?= e(url('roles.php')) ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('shield', '', 18) ?> Who can do what</h2>
                <p class="card-subtitle">
                    Tick what each role may do. Administrators can always do everything.
                    Ticking anything in a section also lets that role see the section.
                    <?php if ($customised): ?>
                        Some roles have been changed from the defaults; a marked box
                        <span class="roles-mark" aria-hidden="true"></span> is one that differs.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table roles-matrix">
                <thead>
                    <tr>
                        <th scope="col">Permission</th>
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                            <?php $people = (int) ($headcount[$roleKey] ?? 0); ?>
                            <th scope="col" class="is-role" title="<?= attr((string) ($descriptions[$roleKey] ?? '')) ?>">
                                <?= e($roleLabel) ?>
                                <small>
                                    <?= count($matrix[$roleKey] ?? []) ?> of <?= $total ?>
                                    · <?= $people ?> <?= $people === 1 ? 'person' : 'people' ?>
                                </small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($catalogue as $group => $permissions): ?>
                        <?php
                        $groupOff = $permissions !== [] && count(array_intersect_key($permissions, $offModules)) === count($permissions);
                        ?>
                        <tr class="roles-group<?= $groupOff ? ' is-dimmed' : '' ?>">
                            <th scope="rowgroup" colspan="<?= count($roles) + 1 ?>">
                                <?= e((string) $group) ?>
                                <?php if ($groupOff): ?>
                                    <span class="badge badge-muted">Switched off under Settings → Features</span>
                                <?php endif; ?>
                            </th>
                        </tr>

                        <?php foreach ($permissions as $permission => $label): ?>
                            <?php $rowOff = isset($offModules[$permission]); ?>
                            <tr<?= $rowOff ? ' class="is-dimmed"' : '' ?>>
                                <td>
                                    <?= e((string) $label) ?>
                                    <?php if ($permission === 'costs.view'): ?>
                                        <span class="cell-secondary">Administrators only, unless you decide otherwise.</span>
                                    <?php elseif ($permission === 'logs.edit_own'): ?>
                                        <span class="cell-secondary">Logs they wrote themselves.</span>
                                    <?php endif; ?>
                                    <?php if ($rowOff && !$groupOff): ?>
                                        <span class="badge badge-muted"><?= e($offModules[$permission]) ?> is switched off</span>
                                    <?php endif; ?>
                                </td>

                                <?php foreach ($roleKeys as $roleKey): ?>
                                    <?php
                                    $has       = in_array($permission, $matrix[$roleKey] ?? [], true);
                                    $isDefault = in_array($permission, $defaults[$roleKey] ?? [], true) === $has;
                                    $fixed     = $roleKey === Acl::ROLE_ADMIN;
                                    ?>
                                    <td class="is-role<?= $isDefault ? '' : ' is-changed' ?>">
                                        <?php // The whole cell is the tap target, not just the box. ?>
                                        <label class="roles-cell">
                                            <input type="checkbox"
                                                   name="perm[<?= e((string) $roleKey) ?>][]"
                                                   value="<?= attr((string) $permission) ?>"
                                                   aria-label="<?= attr((string) $label . ' — ' . $roles[$roleKey]) ?>"
                                                   <?= $has ? 'checked' : '' ?>
                                                   <?= $fixed ? 'disabled' : '' ?>>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer form-actions">
            <button type="submit" class="btn btn-primary"><?= icon('save', '', 17) ?> Save</button>
            <a class="btn btn-secondary" href="<?= e(url('users.php')) ?>">Back to people</a>
            <?php if ($customised): ?>
                <button type="submit" name="action" value="reset" class="btn btn-ghost"
                        data-confirm="Put every role back to its default permissions? Anything you changed here is undone."
                        data-confirm-title="Reset the roles?"
                        data-confirm-text="Reset">
                    Reset to defaults
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= icon('info', '', 18) ?> How roles work</h2>
    </div>
    <div class="card-body">
        <ul class="setup-steps">
            <li>Each person has one role, chosen on their <a href="<?= e(url('users.php')) ?>">People</a> page.
                Change the role, or change what the role can do here — whichever is less work.</li>
            <li>Changes apply to everybody with that role the next time they open a page. Nobody has to sign in again.</li>
            <li>A box you cannot tick belongs to a module that is switched off under
                <a href="<?= e(url('settings.php', ['tab' => 'features'])) ?>">Settings → Features</a>.
                The tick is kept, and comes back with the module.</li>
            <li>Administrators always keep every permission, so nobody can lock the site.</li>
        </ul>
    </div>
</div>
