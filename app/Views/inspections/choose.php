<?php

use App\Status;
?>
<div class="card" style="max-width:680px;margin:0 auto">
    <div class="card-header">
        <div>
            <h2 class="card-title">Which check?</h2>
            <p class="card-subtitle">
                More than one checklist applies to <?= e((string) $asset['name']) ?>.
            </p>
        </div>
    </div>
    <div class="card-body">
        <div class="flex flex-col gap-2">
            <?php foreach ($checklists as $checklist): ?>
                <a class="form-check-card" style="text-decoration:none"
                   href="<?= e(url('inspection-run.php', [
                       'asset_id'     => (int) $asset['id'],
                       'checklist_id' => (int) $checklist['id'],
                   ])) ?>">
                    <span class="stat-icon" style="width:36px;height:36px">
                        <?= icon('clipboard-check', '', 18) ?>
                    </span>
                    <span class="flex-1">
                        <strong style="display:block"><?= e((string) $checklist['name']) ?></strong>
                        <span class="text-sm text-muted">
                            <?= e(Status::label((string) $checklist['frequency'], 'frequency')) ?>
                            <?php if (!empty($checklist['estimated_minutes'])): ?>
                                &middot; about <?= (int) $checklist['estimated_minutes'] ?> minutes
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($checklist['description'])): ?>
                            <span class="text-sm text-subtle" style="display:block;margin-top:4px">
                                <?= e(str_limit((string) $checklist['description'], 140)) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <?= icon('chevron-right', '', 18) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-footer">
        <a class="btn btn-ghost" href="<?= e(url('inspection-run.php')) ?>">Back</a>
    </div>
</div>
