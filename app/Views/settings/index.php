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
                    <?= e(asset_word(true, true)) ?>, jobs, parts, work orders, inspections, schedules, people and the change log,
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

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('kart', '', 18) ?> The starting fleet</h2>
                    <p class="card-subtitle">The Castle Fun Center <?= e(asset_word(true)) ?>, ready to rename</p>
                </div>
            </div>
            <div class="card-body">
                <?php if (\App\Fleet::present()): ?>
                    <p class="text-sm text-muted">
                        Loaded. Twenty go-karts, the Freefall, Dragon Coaster and Swings, the zip line, twelve
                        bowling lanes, six axe-throw lanes and the indoor attractions, each with its checklist and
                        service schedule. Rename, re-tag or delete any of them from the
                        <a href="<?= e(url('assets.php')) ?>"><?= e(asset_word(true)) ?></a> screen.
                    </p>
                <?php elseif (\App\Fleet::available()): ?>
                    <p class="text-sm text-muted">
                        Not loaded. This adds twenty go-karts, the Freefall, Dragon Coaster and Swings, the zip
                        line, twelve bowling lanes, six axe-throw lanes and the indoor attractions — forty-eight
                        <?= e(asset_word(true)) ?> — each with its checklist and service schedule, and no made-up
                        history. Anything you already have is left exactly as it is: <?= e(an_asset()) ?> with the
                        same tag as one of these is kept as yours.
                    </p>
                    <form method="post" action="<?= e(url('settings.php', ['tab' => 'system'])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="load_fleet">
                        <button type="submit" class="btn btn-primary"
                                data-confirm="Add the Castle Fun Center starting fleet to this site? Nothing you already have is changed, and you can rename or delete any of it afterwards."
                                data-confirm-title="Load the starting fleet"
                                data-confirm-text="Load the fleet">
                            <?= icon('plus', '', 17) ?> Load the starting fleet
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-sm text-muted">
                        Not loaded, and the file it comes from (<code>install/fleet.sql</code>) is not on the
                        server — the <code>install</code> folder is usually deleted after setting up. Upload that
                        folder from the release, come back here to load the fleet, then delete the folder again.
                    </p>
                <?php endif; ?>
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

<?php elseif ($tab === 'fields'): ?>
    <?php
    // ==================== Fields: extra questions on every machine ====================
    $fieldTypes   = \App\CustomFields::TYPES;
    $customFields = $customFields ?? [];

    // One row of the editor. $i is the row index, or '__INDEX__' in the template.
    $fieldRow = static function ($i, array $field) use ($fieldTypes): void {
        $n = 'fields[' . $i . ']';
        ?>
        <input type="hidden" name="<?= e($n) ?>[key]" value="<?= attr((string) ($field['key'] ?? '')) ?>">
        <div class="form-row cols-3">
            <div class="form-group">
                <label class="form-label">Field name</label>
                <input type="text" class="form-input" name="<?= e($n) ?>[label]"
                       value="<?= attr((string) ($field['label'] ?? '')) ?>"
                       maxlength="80" placeholder="Seat size" aria-label="Field name">
            </div>
            <div class="form-group">
                <label class="form-label">Kind of answer</label>
                <select class="form-select" name="<?= e($n) ?>[type]" data-reveal="ftype<?= e((string) $i) ?>">
                    <?php foreach ($fieldTypes as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected((string) ($field['type'] ?? 'text'), $value) ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Hint <span class="text-subtle">(optional)</span></label>
                <input type="text" class="form-input" name="<?= e($n) ?>[hint]"
                       value="<?= attr((string) ($field['hint'] ?? '')) ?>"
                       maxlength="200" placeholder="Shown in small print under the box">
            </div>
        </div>
        <div class="form-group" data-reveal-for="ftype<?= e((string) $i) ?>" data-reveal-when="choice">
            <label class="form-label">The choices, separated by commas</label>
            <input type="text" class="form-input" name="<?= e($n) ?>[options]"
                   value="<?= attr(implode(', ', (array) ($field['options'] ?? []))) ?>"
                   placeholder="Small, Medium, Large">
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <label class="form-check">
                <input type="checkbox" value="1" name="<?= e($n) ?>[list]" <?= !empty($field['list']) ? 'checked' : '' ?>>
                <span class="form-check-label">Show as a column on the <?= e(asset_word()) ?> list</span>
            </label>
            <button type="button" class="btn btn-ghost btn-sm text-danger" data-repeater-remove
                    aria-label="Remove this field">
                <?= icon('trash', '', 15) ?> Remove
            </button>
        </div>
        <?php
    };
    ?>

    <form method="post" action="<?= e(url('settings.php', ['tab' => 'fields'])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_fields">

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('list', '', 18) ?> Extra fields on every <?= e(asset_word()) ?></h2>
                    <p class="card-subtitle">
                        The built-in form covers make, model, serial numbers, engine, tyres and dates.
                        Anything else this site needs to record — seat size, restrictor plate, gas or
                        electric, a supplier's part number — add here. Each field goes on the
                        <?= e(asset_word()) ?> form, the <?= e(asset_word()) ?> page and the export.
                    </p>
                </div>
            </div>
            <div class="card-body">
                <?php if (has_error('fields')): ?>
                    <div class="alert alert-error">
                        <?= icon('alert-circle', '', 18) ?>
                        <div class="alert-body"><?= e(error_for('fields')) ?></div>
                    </div>
                <?php endif; ?>

                <div class="fields-editor" data-repeater data-repeater-index="<?= count($customFields) ?>">
                    <div data-repeater-rows>
                        <?php foreach ($customFields as $i => $field): ?>
                            <div class="repeater-row" data-repeater-row>
                                <?php $fieldRow($i, $field); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <template data-repeater-template>
                        <?php $fieldRow('__INDEX__', ['type' => 'text', 'list' => false]); ?>
                    </template>

                    <?php if ($customFields === []): ?>
                        <p class="text-muted">
                            No extra fields yet. The built-in form may be all you need.
                        </p>
                    <?php endif; ?>

                    <button type="button" class="btn btn-secondary" data-repeater-add>
                        <?= icon('plus', '', 16) ?> Add a field
                    </button>
                </div>

                <p class="form-hint mt-3">
                    Removing a field hides it. What was typed into it stays on each
                    <?= e(asset_word()) ?> and comes back if the field is added again with the same name.
                </p>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">
                    <?= icon('save', '', 17) ?> Save fields
                </button>
            </div>
        </div>
    </form>

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
                    <p class="card-subtitle">
                        Sensible defaults are already set. Change these only if you need to.
                        What each role is allowed to do lives on the
                        <a href="<?= e(url('roles.php')) ?>">Roles</a> page.
                    </p>
                <?php elseif ($tab === 'features'): ?>
                    <p class="card-subtitle">
                        Use what you need and switch off the rest. Off means gone from the menu and the
                        forms — nothing is deleted, and switching it back on brings the records straight back.
                    </p>
                <?php elseif ($tab === 'slack'): ?>
                    <p class="card-subtitle">
                        Alerts in a Slack channel, as chatty or as quiet as you like.
                        Set it up with the steps below, test it, then turn it on.
                    </p>
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

                if ($type !== 'heading') {
                    $visible++;
                }
                ?>

                <?php if ($type === 'heading'): ?>
                    <div class="form-section">
                        <h3 class="form-section-title"><?= e($label) ?></h3>
                        <?php if ($hint !== ''): ?><p class="form-hint"><?= e($hint) ?></p><?php endif; ?>
                    </div>

                <?php elseif ($type === 'bool'): ?>
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

<?php // ==================== Slack: set-up steps and a test ==================== ?>
<?php if ($tab === 'slack'): ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= icon('check-circle', '', 18) ?> Does it work?</h2>
                    <p class="card-subtitle">Save the token and channel above first, then send a test.</p>
                </div>
            </div>
            <form method="post" action="<?= e(url('settings.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_slack">
                <div class="card-body">
                    <p class="text-sm text-muted">
                        Posts one line to the main channel. If it arrives, turn <strong>Post to Slack</strong>
                        on above and save. If it does not, the message here says what to fix.
                    </p>
                    <button type="submit" class="btn btn-secondary">
                        <?= icon('play', '', 17) ?> Send a test message
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('info', '', 18) ?> Setting up the Slack side</h2>
            </div>
            <div class="card-body">
                <ol class="setup-steps">
                    <li>Go to <strong>api.slack.com/apps</strong> and choose <strong>Create New App → From scratch</strong>.
                        Name it something like <em><?= e(Settings::siteName()) ?></em> and pick your workspace.</li>
                    <li>Under <strong>OAuth &amp; Permissions → Scopes → Bot Token Scopes</strong>, add <code>chat:write</code>.</li>
                    <li>Click <strong>Install to Workspace</strong> and allow it.</li>
                    <li>Copy the <strong>Bot User OAuth Token</strong> — it starts with <code>xoxb-</code> — into the
                        <em>Bot token</em> box above.</li>
                    <li>In Slack, open the channel you want the alerts in and type
                        <code>/invite @<?= e(preg_replace('/\s+/', '', Settings::siteName()) ?: 'YourApp') ?></code>
                        (use whatever you named the app). Do the same for any extra channels you name above.</li>
                    <li>Save, send a test, then turn <strong>Post to Slack</strong> on.</li>
                </ol>
                <p class="form-hint">
                    The token is stored like a password and is never shown again or included in exports.
                    Anyone with it can post as the app, so treat it like one.
                </p>
            </div>
        </div>
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

            <p class="text-sm text-muted mt-4">
                <strong>For timed checks, add a second job</strong> set to run <strong>every five
                minutes</strong>. It only looks at today's checklists and posts when one is not
                finished by its due time. Without it, RideLog still checks whenever somebody
                opens a page, which is usually good enough on a busy day and not on a quiet one.
            </p>

            <div class="code-block">
                <code data-copy-source>curl -s "<?= e(absolute_url('cron.php', ['token' => Settings::cronToken(), 'job' => 'checks'])) ?>"</code>
                <button type="button" class="btn btn-ghost btn-sm"
                        data-copy="<?= attr('curl -s "' . absolute_url('cron.php', ['token' => Settings::cronToken(), 'job' => 'checks']) . '"') ?>">
                    <?= icon('copy', '', 15) ?> Copy
                </button>
            </div>

            <p class="text-sm text-muted mt-3">
                The token in those addresses is what stops anybody else triggering the jobs.
                Generating a new one immediately stops the old addresses working, so update
                both cron commands at the same time.
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
