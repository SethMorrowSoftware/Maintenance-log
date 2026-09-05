<?php
/**
 * Starting a check: the area checks this person can run, then the machines.
 *
 * Variables: $assets (rows for the picker), $areaChecklists (rows with
 * location_name, due_time)
 */

use App\Checks;
use App\View;

$areaChecklists = $areaChecklists ?? [];
?>

<?php if ($areaChecklists !== []): ?>
    <div class="card" style="max-width:640px;margin:0 auto">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('map-pin', '', 18) ?> Area checks</h2>
                <p class="card-subtitle">Checks of a place rather than of one <?= e(asset_word()) ?>. Tap one to start it.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="flex flex-col gap-2">
                <?php foreach ($areaChecklists as $checklist): ?>
                    <a class="form-check-card" style="text-decoration:none"
                       href="<?= e(url('inspection-run.php', ['checklist_id' => (int) $checklist['id']])) ?>">
                        <span class="stat-icon" style="width:36px;height:36px">
                            <?= icon('clipboard-check', '', 18) ?>
                        </span>
                        <span class="flex-1">
                            <strong style="display:block"><?= e((string) $checklist['name']) ?></strong>
                            <span class="text-sm text-muted">
                                <?= e((string) $checklist['location_name']) ?>
                                <?php if (!empty($checklist['due_time'])): ?>
                                    &middot; by <?= e(Checks::timeLabel((string) $checklist['due_time'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($checklist['estimated_minutes'])): ?>
                                    &middot; about <?= (int) $checklist['estimated_minutes'] ?> minutes
                                <?php endif; ?>
                            </span>
                        </span>
                        <?= icon('chevron-right', '', 18) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($assets !== []): ?>
    <div class="card" style="max-width:640px;margin:0 auto">
        <div class="card-header">
            <h2 class="card-title"><?= icon('clipboard-check', '', 18) ?> <?= $areaChecklists !== [] ? 'Or check ' . e(an_asset()) : 'What are you checking?' ?></h2>
        </div>
        <form method="get" action="<?= e(url('inspection-run.php')) ?>">
            <div class="card-body">
                <?php View::partial('asset-picker', [
                    'name'     => 'asset_id',
                    'label'    => asset_word(false, true),
                    'value'    => '',
                    'assets'   => $assets,
                    'required' => true,
                    'hint'     => 'Pick the kart, ride or ' . asset_word() . ' you are standing in front of.',
                ]); ?>
            </div>
            <div class="card-footer">
                <a class="btn btn-ghost" href="<?= e(url('inspections.php')) ?>">Cancel</a>
                <span class="flex-1"></span>
                <button type="submit" class="btn btn-primary btn-lg">
                    Continue <?= icon('chevron-right', '', 17) ?>
                </button>
            </div>
        </form>
    </div>
<?php elseif ($areaChecklists === []): ?>
    <?php View::partial('empty-state', [
        'icon'        => 'clipboard-check',
        'title'       => 'Nothing to check yet',
        'message'     => \App\Scope::limited()
            ? 'No checklist covers anything in your area. Ask whoever looks after checklists.'
            : 'There are no ' . asset_word(true) . ' in service and no area checklists. Add ' . an_asset() . ' or a checklist first.',
        'actionLabel' => can('checklists.manage') ? 'Checklists' : '',
        'actionUrl'   => can('checklists.manage') ? url('checklists.php') : '',
        'actionIcon'  => 'checklist',
    ]); ?>
<?php endif; ?>
