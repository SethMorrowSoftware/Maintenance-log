<?php
/**
 * Settings.
 *
 * The form is built from the settings table itself — key, type, label and
 * description all come from the database — so adding a setting to seed.sql puts
 * it on this page with no code change.
 */

use App\Settings;
use App\Str;
use App\View;

$logoUrl = Settings::logoUrl();
$health  = $health ?? null;
?>

<?php
$tabLinks = [];

foreach ($groups as $groupKey => $groupLabel) {
    $tabLinks[$groupKey] = [
        'label' => $groupLabel,
        'url'   => url('settings.php', ['tab' => $groupKey]),
    ];
}

View::partial('tabs', ['tabs' => $tabLinks, 'active' => $tab]);
?>

<?php if ($tab === 'system' && $health !== null): ?>
    <?php
    // ==================== System: is it healthy? ====================
    $worst = 'ok';

    foreach ($health['checks'] as $check) {
        if ($check['state'] === 'fail') {
            $worst = 'fail';
        } elseif ($check['state'] === 'warn' && $worst !== 'fail') {
            $worst = 'warn';
        }
    }

    $stateLabel = ['ok' => 'Fine', 'info' => 'Note', 'warn' => 'Check', 'fail' => 'Problem'];
    $stateTone  = ['ok' => 'ok', 'info' => 'info', 'warn' => 'warn', 'fail' => 'danger'];
    ?>

    <div class="alert alert-<?= $worst === 'ok' ? 'success' : ($worst === 'fail' ? 'error' : 'warning') ?>">
        <?= icon($worst === 'ok' ? 'check-circle' : 'alert-triangle', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">
                <?php if ($worst === 'ok'): ?>
                    Everything looks healthy
                <?php elseif ($worst === 'warn'): ?>
                    Working, with a few things worth a look
                <?php else: ?>
                    Something needs fixing
                <?php endif; ?>
            </strong>
            <p style="margin:4px 0 0">
                Checked just now. Each line below says what it means and what to do about it.
            </p>
        </div>
    </div>

    <div class="stat-grid">
        <?php foreach ($health['counts'] as $count): ?>
            <?php View::partial('stat-card', [
                'label' => $count['label'], 'value' => $count['value'],
                'icon'  => $count['icon'], 'tone' => 'muted',
            ]); ?>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('activity', '', 18) ?> Health checks</h2>
                <p class="card-subtitle">The things that go wrong on shared hosting, checked live</p>
            </div>
        </div>
        <ul class="health-list">
            <?php foreach ($health['checks'] as $check): ?>
                <li class="health-item tone-<?= e($stateTone[$check['state']] ?? 'info') ?>">
                    <span class="badge badge-<?= e($stateTone[$check['state']] ?? 'info') ?> health-state">
                        <?= e($stateLabel[$check['state']] ?? 'Note') ?>
                    </span>
                    <div class="health-body">
                        <div class="health-head">
                            <strong><?= e($check['label']) ?></strong>
                            <span class="health-value"><?= e($check['value']) ?></span>
                        </div>
                        <?php if ($check['hint'] !== ''): ?>
                            <div class="health-hint"><?= e($check['hint']) ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('download', '', 18) ?> Everything, in one file</h2>
                    <p class="card-subtitle">A copy of every record, for backup or for moving elsewhere</p>
                </div>
            </div>
            <div class="card-body">
                <p class="text-sm text-muted">
                    Machines, jobs, parts, work orders, inspections, schedules, people and the change log,
                    as spreadsheet files<?= class_exists('ZipArchive') ? ' inside one ZIP' : '' ?>.
                    Passwords and secret tokens are left out. Photos are not included: they live in
                    <code>storage/uploads</code>, which cPanel's backup covers.
                </p>
                <a class="btn btn-primary" href="<?= e(url('export.php')) ?>" data-no-guard>
                    <?= icon('download', '', 17) ?> Download everything
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('clock', '', 18) ?> The nightly job</h2>
                    <p class="card-subtitle">Run it by hand to check it works, or to catch up</p>
                </div>
            </div>
            <div class="card-body">
                <p class="text-sm text-muted">
                    Works out what service is due, warns about parts running low and tidies old records.
                    Normally cron runs it every morning; the command is under Security.
                </p>
                <a class="btn btn-secondary" href="<?= e(url('cron.php')) ?>" target="_blank" rel="noopener">
                    <?= icon('play', '', 17) ?> Run it now
                </a>
                <a class="btn btn-ghost" href="<?= e(url('settings.php', ['tab' => 'security'])) ?>">
                    Cron command
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icon('info', '', 18) ?> About this installation</h2>
        </div>
        <div class="card-body">
            <dl class="detail-list">
                <?php foreach ($health['facts'] as $fact): ?>
                    <dt><?= e($fact['label']) ?></dt>
                    <dd><?= e($fact['value']) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>

<?php else: ?>

<form method="post" action="<?= e(url('settings.php', ['tab' => $tab])) ?>" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($tab) ?>">

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= e((string) $groups[$tab]) ?></h2>
                <?php if ($tab === 'email'): ?>
                    <p class="card-subtitle">
                        Only needed if you want the system to send notifications by email.
                        Everything works without it.
                    </p>
                <?php elseif ($tab === 'security'): ?>
                    <p class="card-subtitle">Sensible defaults are already set. Change these only if you need to.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php $visible = 0; ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $key   = (string) $row['setting_key'];
                $type  = (string) $row['setting_type'];
                $label = (string) $row['label'] !== '' ? (string) $row['label'] : Str::label($key);
                $hint  = (string) $row['description'];
                $value = (string) ($row['setting_value'] ?? '');

                // Internal bookkeeping is not something to edit in a form.
                if ($type === 'hidden') {
                    continue;
                }

                $visible++;
                ?>

                <?php if ($type === 'bool'): ?>
                    <label class="form-check" for="f_s_<?= e($key) ?>">
                        <input type="checkbox" id="f_s_<?= e($key) ?>" name="s_<?= e($key) ?>" value="1"
                            <?= checked($value, '1') ?>>
                        <span class="form-check-label">
                            <?= e($label) ?>
                            <?php if ($hint !== ''): ?><small><?= e($hint) ?></small><?php endif; ?>
                        </span>
                    </label>

                <?php elseif ($type === 'select' && isset($choices[$key])): ?>
                    <?php View::partial('form-field', [
                        'name'    => 's_' . $key,
                        'label'   => $label,
                        'type'    => 'select',
                        'value'   => $value,
                        'options' => $choices[$key],
                        'hint'    => $hint,
                        'empty'   => null,
                        'noOld'   => true,
                    ]); ?>

                <?php elseif ($type === 'password'): ?>
                    <?php View::partial('form-field', [
                        'name'  => 's_' . $key,
                        'label' => $label,
                        'type'  => 'password',
                        'value' => '',
                        'hint'  => $hint . ($value !== '' ? ' Leave blank to keep the one already saved.' : ''),
                        'noOld' => true,
                        'attrs' => ['autocomplete' => 'new-password'],
                    ]); ?>

                <?php elseif ($key === 'logo_path'): ?>
                    <?php // Handled by its own upload form below the settings. ?>
                    <?php $visible--; ?>

                <?php else: ?>
                    <?php View::partial('form-field', [
                        'name'  => 's_' . $key,
                        'label' => $label,
                        'type'  => $type === 'int' ? 'number'
                                : ($type === 'color' ? 'color'
                                : ($type === 'email' ? 'email' : 'text')),
                        'value' => $value,
                        'hint'  => $hint,
                        'noOld' => true,
                        'attrs' => $type === 'int' ? ['step' => 1] : ['maxlength' => 191],
                    ]); ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($visible === 0): ?>
                <p class="text-muted">There is nothing to change in this section.</p>
            <?php endif; ?>

            <?php if ($tab === 'uploads'): ?>
                <div class="alert alert-info">
                    <?= icon('info', '', 18) ?>
                    <div class="alert-body">
                        This server will not accept a file larger than
                        <strong><?= e(Str::formatBytes($uploadLimit)) ?></strong> whatever you
                        set here — that is <code>upload_max_filesize</code> and
                        <code>post_max_size</code> in PHP. On cPanel you can usually raise them
                        under <em>Select PHP Version &rarr; Options</em>.
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <?= icon('save', '', 17) ?> Save <?= e(strtolower((string) $groups[$tab])) ?> settings
            </button>
        </div>
    </div>
</form>

<?php endif; ?>

<?php // ==================== Email: send a test ==================== ?>
<?php if ($tab === 'email'): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('mail', '', 18) ?> Does it work?</h2>
                <p class="card-subtitle">Save the settings above first, then send yourself a test.</p>
            </div>
        </div>
        <form method="post" action="<?= e(url('settings.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_email">
            <div class="card-body">
                <div class="flex gap-2 items-end flex-wrap">
                    <div style="flex:1 1 260px">
                        <?php View::partial('form-field', [
                            'name'        => 'test_email',
                            'label'       => 'Send a test message to',
                            'type'        => 'email',
                            'value'       => (string) (user()['email'] ?? ''),
                            'noOld'       => true,
                            'placeholder' => 'you@example.com',
                        ]); ?>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <?= icon('mail', '', 17) ?> Send it
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php // ==================== Branding: the logo ==================== ?>
<?php if ($tab === 'branding'): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('image', '', 18) ?> Logo</h2>
                <p class="card-subtitle">
                    Appears in the sidebar and at the top of every printed sheet.
                    A wide PNG with a transparent background works best.
                </p>
            </div>
        </div>
        <div class="card-body">
            <?php if ($logoUrl !== null): ?>
                <div class="logo-preview">
                    <img src="<?= e($logoUrl) ?>" alt="The current logo">
                </div>

                <form method="post" action="<?= e(url('settings.php')) ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_logo">
                    <button type="submit" class="btn btn-ghost btn-sm"
                            data-confirm="Remove the logo and go back to the default mark?"
                            data-confirm-title="Remove logo">
                        <?= icon('trash', '', 15) ?> Remove it
                    </button>
                </form>
                <hr>
            <?php endif; ?>

            <form method="post" action="<?= e(url('settings.php')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logo">
                <div class="flex gap-2 items-end flex-wrap">
                    <div style="flex:1 1 260px">
                        <?php View::partial('form-field', [
                            'name'  => 'logo',
                            'label' => $logoUrl === null ? 'Choose an image' : 'Replace it',
                            'type'  => 'file',
                            'noOld' => true,
                            'attrs' => ['accept' => 'image/*'],
                        ]); ?>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <?= icon('upload', '', 17) ?> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php // ==================== Security: the cron token ==================== ?>
<?php if ($tab === 'security'): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('clock', '', 18) ?> The nightly job</h2>
                <p class="card-subtitle">
                    Works out what maintenance is due, warns about low stock and tidies old records.
                </p>
            </div>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted">
                Add this to <strong>Cron Jobs</strong> in cPanel, set to run once a day
                (a good time is 6am, before anyone opens up):
            </p>

            <div class="code-block">
                <code data-copy-source>curl -s "<?= e(absolute_url('cron.php', ['token' => Settings::cronToken()])) ?>"</code>
                <button type="button" class="btn btn-ghost btn-sm"
                        data-copy="<?= attr('curl -s "' . absolute_url('cron.php', ['token' => Settings::cronToken()]) . '"') ?>">
                    <?= icon('copy', '', 15) ?> Copy
                </button>
            </div>

            <p class="text-sm text-muted mt-3">
                The token in that address is what stops anybody else triggering the job.
                Generating a new one immediately stops the old address working, so update
                the cron command at the same time.
            </p>

            <form method="post" action="<?= e(url('settings.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="new_cron_token">
                <button type="submit" class="btn btn-ghost btn-sm"
                        data-confirm="Generate a new token? The cron command you have set up will stop working until you update it."
                        data-confirm-title="New cron token">
                    <?= icon('refresh', '', 15) ?> Generate a new token
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
