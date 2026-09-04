<?php

use App\View;
?>
<div class="card" style="max-width:640px;margin:0 auto">
    <div class="card-header">
        <h2 class="card-title"><?= icon('clipboard-check', '', 18) ?> What are you checking?</h2>
    </div>
    <form method="get" action="<?= e(url('inspection-run.php')) ?>">
        <div class="card-body">
            <?php View::partial('asset-picker', [
                'name'     => 'asset_id',
                'label'    => 'Asset',
                'value'    => '',
                'assets'   => $assets,
                'required' => true,
                'hint'     => 'Pick the kart, ride or machine you are standing in front of.',
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
