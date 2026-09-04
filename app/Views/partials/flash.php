<?php
/**
 * Flash messages.
 *
 * Rendered twice over: as .alert blocks that work with JavaScript disabled,
 * and as a JSON block core.js picks up to raise toasts. The alerts hide
 * themselves once JS has taken over, so nothing is shown twice.
 *
 * Variables: $inline (bool) — always show alerts, never hand off to toasts
 */

use App\Flash;

$messages = Flash::messages();
$errors   = Flash::errors();
$inline   = $inline ?? false;

if ($messages === [] && $errors === []) {
    return;
}

$iconFor = [
    'success' => 'check-circle',
    'error'   => 'alert-circle',
    'warning' => 'alert-triangle',
    'info'    => 'info',
];
?>
<?php if ($messages !== []): ?>
    <div class="flash-messages<?= $inline ? '' : ' js-flash-fallback' ?>">
        <?php foreach ($messages as $message): ?>
            <?php $type = $message['type']; ?>
            <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
                <?= icon($iconFor[$type] ?? 'info', '', 18) ?>
                <div class="alert-body"><?= e($message['message']) ?></div>
                <button type="button" class="alert-dismiss" data-dismiss="alert" aria-label="Dismiss">
                    <?= icon('x', '', 15) ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$inline): ?>
        <script type="application/json" id="rl-flash"><?= js($messages) ?></script>
    <?php endif; ?>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="alert alert-error" role="alert">
        <?= icon('alert-circle', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">
                <?= count($errors) === 1 ? 'There is a problem with one field' : 'There are problems with ' . count($errors) . ' fields' ?>
            </strong>
            <ul style="margin:6px 0 0;padding-left:18px;">
                <?php foreach ($errors as $message): ?>
                    <li><?= e($message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>
