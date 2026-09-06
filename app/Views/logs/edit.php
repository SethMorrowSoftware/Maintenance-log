<?php
/**
 * Log maintenance.
 *
 * The form a mechanic fills in most often, so the shape of it matters.
 *
 * Four things are required: which machine, what kind of work, a short title, and
 * when. The date and time default to now. Everything else — the write-up,
 * parts, costs, meter, downtime — is optional and collapsed by default, so the
 * quick case is genuinely quick and the thorough case is still possible.
 */

use App\Auth;
use App\Dates;
use App\Settings;
use App\Status;
use App\Uploader;
use App\View;

$meterUnit   = $asset === null ? '' : (string) $asset['meter_type'];
$hasMeter    = $meterUnit !== '' && $meterUnit !== 'none';
$currency    = Settings::currency();
$canSeeCosts = costs_visible();
$partColumns = $canSeeCosts ? 'cols-4' : 'cols-3';

// After a rejected save the typed part lines come back, and the section that
// holds an error or a typed value opens so the message can be seen.
$oldParts = old('parts', null);

if (is_array($oldParts)) {
    $logParts = [];

    foreach ($oldParts as $line) {
        if (!is_array($line)) {
            continue;
        }

        $logParts[] = [
            'part_id'    => (string) ($line['part_id'] ?? ''),
            'part_name'  => (string) ($line['part_name'] ?? ''),
            'quantity'   => (string) ($line['quantity'] ?? ''),
            'unit_cost'  => (string) ($line['unit_cost'] ?? ''),
            'total_cost' => (float) ($line['quantity'] ?? 0) * (float) ($line['unit_cost'] ?? 0),
        ];
    }
}

// Arriving from a work order that is still open: saving this log is how the
// mechanic finishes it, so the save button says so.
$closesWorkOrder = !$editing
    && feature_on('work_orders')
    && ($workOrder ?? null) !== null
    && !App\Status::isClosedWorkOrder((string) $workOrder['status']);
$canCloseLinked  = $closesWorkOrder && App\Acl::canCloseWorkOrder($workOrder);

$touched = static function (array $names): bool {
    foreach ($names as $name) {
        if (has_error($name) || (string) old($name, '') !== '') {
            return true;
        }
    }

    return false;
};
?>

<form method="post" action="<?= e(url('log-edit.php', $editing ? ['id' => (int) $log['id']] : [])) ?>"
      enctype="multipart/form-data" data-validate data-guard data-cost-scope
      <?= feature_on('drafts') ? 'data-draft="log-' . ($editing ? (int) $log['id'] : 'new') . '"' : '' ?>>
    <?= csrf_field() ?>

    <?php if ($schedule !== null): ?>
        <div class="alert alert-info">
            <?= icon('calendar', '', 18) ?>
            <div class="alert-body">
                <strong class="alert-title">Logging a scheduled job</strong>
                <p style="margin:4px 0 0">
                    “<?= e((string) $schedule['name']) ?>” on <?= e((string) $schedule['asset_name']) ?>.
                    Saving this marks the schedule done and works out when it is next due.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($workOrder !== null): ?>
        <div class="alert alert-info">
            <?= icon('work-order', '', 18) ?>
            <div class="alert-body">
                <strong class="alert-title">Logging against <?= e((string) $workOrder['wo_number']) ?></strong>
                <p style="margin:4px 0 0"><?= e((string) $workOrder['title']) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-sidebar">
        <div>

            <?php // ==================== What happened ==================== ?>
            <div class="card is-accent">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('wrench', '', 18) ?> What did you do?</h2>
                </div>
                <div class="card-body">

                    <?php View::partial('asset-picker', [
                        'name'     => 'asset_id',
                        'label'    => 'Which ' . asset_word() . '?',
                        'value'    => $values['asset_id'],
                        'assets'   => $assets,
                        'required' => true,
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'title',
                        'label'       => 'What was the job?',
                        'type'        => 'text',
                        'value'       => $values['title'],
                        'required'    => true,
                        'placeholder' => 'Replaced front brake pads',
                        'hint'        => 'A few words. This is what shows up in the history list.',
                        'attrs'       => ['maxlength' => 191],
                    ]); ?>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'log_type',
                            'label'    => 'Type of work',
                            'type'     => 'select',
                            'value'    => $values['log_type'],
                            'options'  => Status::options('log_type'),
                            'required' => true,
                            'hint'     => 'Preventive means scheduled. Corrective and repair mean something broke.',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'     => 'performed_at',
                            'label'    => 'When?',
                            'type'     => 'datetime',
                            'value'    => Dates::inputDatetime((string) $values['performed_at']),
                            'required' => true,
                            'hint'     => 'Already set to now. Change it if you are catching up on paperwork.',
                            'attrs'    => ['max' => Dates::localNow()->format('Y-m-d\TH:i')],
                        ]); ?>
                    </div>

                    <?php if (can('logs.edit_any') && $technicians !== []): ?>
                        <?php View::partial('form-field', [
                            'name'    => 'user_id',
                            'label'   => 'Who did the work?',
                            'type'    => 'select',
                            'value'   => $values['user_id'],
                            'options' => $technicians,
                            'hint'    => 'Defaults to you. Change it if you are entering somebody else\'s job.',
                        ]); ?>
                    <?php endif; ?>

                    <?php View::partial('form-field', [
                        'name'        => 'work_performed',
                        'label'       => 'What was done?',
                        'type'        => 'textarea',
                        'value'       => $values['work_performed'],
                        'rows'        => 4,
                        'placeholder' => "Pads were down to the wear line. Fitted a new set, cleaned the rotor, bled the brake and took it round for a lap.",
                        'hint'        => 'Optional, but the next person to work on this will thank you.',
                        'attrs'       => ['maxlength' => 10000, 'data-autogrow' => true, 'data-counter' => 10000],
                    ]); ?>
                </div>
            </div>

            <?php // ================== Parts used (optional) ================== ?>
            <?php if (feature_on('parts')): ?>
            <details class="card" <?= $logParts !== [] ? 'open' : '' ?>>
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h2 class="card-title"><?= icon('package', '', 18) ?> Parts used</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <p class="text-sm text-muted mb-3">
                        Picking a part from stock takes it off the shelf count automatically.
                        You can also type in something bought specially.
                    </p>

                    <div data-repeater data-repeater-index="<?= count($logParts) ?>">
                        <div data-repeater-rows>
                            <?php foreach ($logParts as $index => $part): ?>
                                <div class="repeater-row" data-repeater-row data-line-total>
                                    <div class="form-row <?= e($partColumns) ?>">
                                        <div class="form-group">
                                            <label class="form-label">Part</label>
                                            <select class="form-select" name="parts[<?= (int) $index ?>][part_id]"
                                                    data-part-select aria-label="Part from stock">
                                                <option value="">Not from stock</option>
                                                <?php foreach ($partOptions as $option): ?>
                                                    <option value="<?= (int) $option['id'] ?>"
                                                            data-name="<?= attr((string) $option['name']) ?>"
                                                            data-number="<?= attr((string) $option['part_number']) ?>"
                                                            <?= $canSeeCosts ? 'data-cost="' . attr((string) $option['unit_cost']) . '"' : '' ?>
                                                            <?= selected($option['id'], $part['part_id']) ?>>
                                                        <?= e((string) $option['name']) ?>
                                                        (<?= e(decimal($option['quantity_on_hand'])) ?> in stock)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-input" data-part-name aria-label="Part description"
                                                   name="parts[<?= (int) $index ?>][part_name]"
                                                   value="<?= attr((string) $part['part_name']) ?>" maxlength="191">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Qty</label>
                                            <input type="number" step="0.01" min="0" class="form-input" data-line-qty inputmode="decimal" aria-label="Quantity"
                                                   name="parts[<?= (int) $index ?>][quantity]"
                                                   value="<?= attr((string) $part['quantity']) ?>">
                                        </div>
                                        <?php if ($canSeeCosts): ?>
                                            <div class="form-group">
                                                <label class="form-label">Each</label>
                                                <div class="input-group">
                                                    <span class="input-addon"><?= e($currency) ?></span>
                                                    <input type="number" step="0.01" min="0" class="form-input" data-line-cost inputmode="decimal" aria-label="Cost each"
                                                           name="parts[<?= (int) $index ?>][unit_cost]"
                                                           value="<?= attr((string) $part['unit_cost']) ?>">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm text-muted">
                                            <?php if ($canSeeCosts): ?>
                                                Line total: <strong data-line-out><?= e(money($part['total_cost'])) ?></strong>
                                            <?php endif; ?>
                                        </span>
                                        <button type="button" class="btn btn-ghost btn-sm repeater-remove"
                                                data-repeater-remove>
                                            <?= icon('trash', '', 15) ?> Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <template data-repeater-template data-row-attrs="data-line-total">
                            <div class="form-row <?= e($partColumns) ?>">
                                <div class="form-group">
                                    <label class="form-label">Part</label>
                                    <select class="form-select" name="parts[__INDEX__][part_id]" data-part-select aria-label="Part from stock">
                                        <option value="">Not from stock</option>
                                        <?php foreach ($partOptions as $option): ?>
                                            <option value="<?= (int) $option['id'] ?>"
                                                    data-name="<?= attr((string) $option['name']) ?>"
                                                    data-number="<?= attr((string) $option['part_number']) ?>"
                                                    <?= $canSeeCosts ? 'data-cost="' . attr((string) $option['unit_cost']) . '"' : '' ?>>
                                                <?= e((string) $option['name']) ?>
                                                (<?= e(decimal($option['quantity_on_hand'])) ?> in stock)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-input" data-part-name aria-label="Part description"
                                           name="parts[__INDEX__][part_name]" maxlength="191">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Qty</label>
                                    <input type="number" step="0.01" min="0" class="form-input" data-line-qty inputmode="decimal" aria-label="Quantity"
                                           name="parts[__INDEX__][quantity]" value="1">
                                </div>
                                <?php if ($canSeeCosts): ?>
                                    <div class="form-group">
                                        <label class="form-label">Each</label>
                                        <div class="input-group">
                                            <span class="input-addon"><?= e($currency) ?></span>
                                            <input type="number" step="0.01" min="0" class="form-input" data-line-cost inputmode="decimal" aria-label="Cost each"
                                                   name="parts[__INDEX__][unit_cost]" value="">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm text-muted"><?php if ($canSeeCosts): ?>Line total: <strong data-line-out>&mdash;</strong><?php endif; ?></span>
                                <button type="button" class="btn btn-ghost btn-sm" data-repeater-remove>
                                    Remove
                                </button>
                            </div>
                        </template>

                        <button type="button" class="btn btn-secondary btn-sm" data-repeater-add>
                            <?= icon('plus', '', 15) ?> Add a part
                        </button>
                    </div>
                </div>
            </details>
            <?php endif; ?>

            <?php // ============ Time, cost and condition (optional) ============ ?>
            <details class="card"
                <?= ($values['labor_hours'] || $values['meter_reading'] || $values['downtime_minutes'] || $values['other_cost']
                    || $touched(['labor_hours', 'meter_reading', 'downtime_minutes', 'other_cost', 'labor_rate', 'labor_cost', 'parts_cost', 'status_after', 'schedule_id', 'work_order_id'])) ? 'open' : '' ?>>
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h2 class="card-title"><?= icon('clock', '', 18) ?> <?= $canSeeCosts ? 'Time, cost and condition' : 'Time and condition' ?></h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'   => 'labor_hours',
                            'label'  => 'How long did it take?',
                            'type'   => 'decimal',
                            'value'  => $values['labor_hours'],
                            'suffix' => 'hours',
                            'attrs'  => ['min' => '0', 'step' => '0.25', 'data-labor-hours' => true],
                            'hint'   => 'In hours. 1.5 means an hour and a half.',
                        ]); ?>

                        <?php if (feature_on('downtime')): ?>
                            <?php View::partial('form-field', [
                                'name'   => 'downtime_minutes',
                                'label'  => 'Time out of service',
                                'type'   => 'number',
                                'value'  => $values['downtime_minutes'],
                                'suffix' => 'minutes',
                                'attrs'  => ['min' => '0'],
                                'hint'   => 'How long guests could not use it.',
                            ]); ?>
                        <?php endif; ?>

                        <?php if (feature_on('meters')): ?>
                            <?php // Always in the page, shown only for a machine with a meter — the
                                  // context script switches it on when a different machine is picked. ?>
                            <div data-meter-field <?= $hasMeter ? '' : 'hidden' ?>>
                                <?php View::partial('form-field', [
                                    'name'     => 'meter_reading',
                                    'label'    => 'Meter reading',
                                    'type'     => 'meter',
                                    'value'    => $hasMeter ? $values['meter_reading'] : '',
                                    'suffix'   => $hasMeter ? $meterUnit : '',
                                    'required' => $hasMeter && Settings::bool('require_meter_on_log', false),
                                    'attrs'    => ['min' => '0'],
                                    'hint'   => $hasMeter
                                        ? 'Currently ' . decimal($asset['meter_reading']) . ' ' . $meterUnit . '.'
                                        : 'The number on the meter now.',
                                ]); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canSeeCosts): ?>
                    <div class="form-row cols-4">
                        <?php View::partial('form-field', [
                            'name'   => 'labor_rate',
                            'label'  => 'Labour rate',
                            'type'   => 'money',
                            'value'  => $values['labor_rate'],
                            'prefix' => $currency,
                            'suffix' => '/hr',
                            'attrs'  => ['min' => '0', 'data-labor-rate' => true],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'   => 'labor_cost',
                            'label'  => 'Labour cost',
                            'type'   => 'money',
                            'value'  => $values['labor_cost'],
                            'prefix' => $currency,
                            'attrs'  => ['min' => '0', 'data-labor-cost' => true],
                            'hint'   => 'Worked out from the rate.',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'   => 'parts_cost',
                            'label'  => 'Parts cost',
                            'type'   => 'money',
                            'value'  => $values['parts_cost'],
                            'prefix' => $currency,
                            'attrs'  => ['min' => '0', 'data-parts-cost' => true],
                            'hint'   => 'Adds up the parts above.',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'   => 'other_cost',
                            'label'  => 'Anything else',
                            'type'   => 'money',
                            'value'  => $values['other_cost'],
                            'prefix' => $currency,
                            'attrs'  => ['min' => '0', 'data-other-cost' => true],
                            'hint'   => 'Outside contractor, shipping…',
                        ]); ?>
                    </div>

                    <div class="flex items-center justify-between p-3 rounded bg-sunken">
                        <strong>Total cost of this job</strong>
                        <strong class="text-lg tabular" data-grand-total><?= e(money($values['total_cost'] ?? ((float) $values['labor_cost'] + (float) $values['parts_cost'] + (float) $values['other_cost']))) ?></strong>
                    </div>

                    <hr>
                    <?php endif; ?>

                    <?php View::partial('form-field', [
                        'name'    => 'status_after',
                        'label'   => 'What state is the ' . asset_word() . ' in now?',
                        'type'    => 'select',
                        'value'   => $values['status_after'],
                        'options' => Status::options('asset'),
                        'empty'   => 'Leave it as it is'
                            . ($asset === null ? '' : ' (' . Status::label((string) $asset['status'], 'asset') . ')'),
                        'hint'    => 'Choosing one changes the ' . asset_word() . '\'s status straight away.',
                    ]); ?>

                    <?php // Both selects are always in the page: the context script refills
                          // them when a different machine is picked, and hides them when
                          // the machine has nothing to link to. ?>
                    <?php if (feature_on('schedules') && can('schedules.view')): ?>
                        <div data-ctx-schedules <?= $assetSchedules === [] ? 'hidden' : '' ?>>
                            <?php View::partial('form-field', [
                                'name'    => 'schedule_id',
                                'label'   => 'Was this a scheduled job?',
                                'type'    => 'select',
                                'value'   => $values['schedule_id'],
                                'options' => $assetSchedules,
                                'empty'   => 'No, one-off work',
                                'hint'    => 'Linking it marks the schedule done and sets the next due date.',
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (feature_on('work_orders') && can('workorders.view')): ?>
                        <div data-ctx-workorders <?= $openWorkOrders === [] ? 'hidden' : '' ?>>
                            <?php View::partial('form-field', [
                                'name'    => 'work_order_id',
                                'label'   => 'Does this close a work order?',
                                'type'    => 'select',
                                'value'   => $values['work_order_id'],
                                'options' => $openWorkOrders,
                                'empty'   => 'Not related to a work order',
                            ]); ?>

                            <?php // Whether it closes is decided on the button at the
                                  // bottom, not by a tickbox nobody scrolls to. ?>
                            <p class="form-hint">
                                Whether this finishes it off is the button you press at the bottom.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </details>

            <?php // ======================= Follow-up ======================= ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('alert-circle', '', 18) ?> Anything left to do?</h2>
                </div>
                <div class="card-body">
                    <label class="form-check" for="f_followup">
                        <input type="checkbox" id="f_followup" name="requires_followup" value="1"
                               <?= checked((int) $values['requires_followup']) ?>
                               data-collapse-toggle="#followup-notes" aria-expanded="<?= $values['requires_followup'] ? 'true' : 'false' ?>">
                        <span class="form-check-label">
                            This needs following up
                            <small>
                                Flags it on the dashboard<?= can('workorders.create') ? ', and opens a work order so it does not get lost' : '' ?>.
                            </small>
                        </span>
                    </label>

                    <div id="followup-notes" <?= $values['requires_followup'] ? '' : 'hidden' ?>>
                        <?php View::partial('form-field', [
                            'name'        => 'followup_notes',
                            'label'       => 'What still needs doing?',
                            'type'        => 'textarea',
                            'value'       => $values['followup_notes'],
                            'rows'        => 3,
                            'placeholder' => 'Clutch is starting to sound rough. Order a replacement and fit it before the weekend.',
                            'attrs'       => ['maxlength' => 2000, 'data-autogrow' => true],
                        ]); ?>
                    </div>

                    <?php // Arriving from an open work order, the two save buttons
                          // already ask whether the job is finished. Asking again
                          // here is how somebody ends up with a closed work order
                          // attached to a log that says the work is unfinished. ?>
                    <?php if (!$closesWorkOrder): ?>
                        <label class="form-check" for="f_not_finished">
                            <input type="checkbox" id="f_not_finished" name="not_finished" value="1"
                                   <?= checked(!(int) $values['is_completed']) ?>>
                            <span class="form-check-label">
                                The job is not finished yet
                                <small>Record what you have done so far and come back to it.</small>
                            </span>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php // ========================== Sidebar ========================== ?>
        <div>
            <div class="card">
                <div class="card-body">
                    <?php if ($closesWorkOrder): ?>
                        <?php // The sticky bar at the foot of the page is a phone-only
                              // control, so the same choice has to exist here or a
                              // mechanic at a desk would have no way to finish the job
                              // from the log they just wrote. "Still more to do" is
                              // first in the source: a stray Enter must not close
                              // anything. CSS draws the other one on top. ?>
                        <div class="save-choice">
                            <button type="submit" name="close_work_order" value="0"
                                    class="btn btn-secondary btn-block btn-lg is-second">
                                Save, still more to do
                            </button>
                            <button type="submit" name="close_work_order" value="1"
                                    class="btn btn-primary btn-block btn-lg is-first">
                                <?= icon('check-circle', '', 18) ?>
                                <?= $canCloseLinked ? 'Save and finish ' : 'Save and hand over ' ?><?= e((string) $workOrder['wo_number']) ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            <?= icon('save', '', 18) ?> <?= $editing ? 'Save changes' : 'Save this log' ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!$editing): ?>
                        <button type="submit" name="after" value="new" class="btn btn-secondary btn-block mt-2">
                            <?= icon('plus', '', 16) ?> Save and log another
                        </button>
                    <?php endif; ?>

                    <a class="btn btn-ghost btn-block mt-2" data-no-guard
                       href="<?= e($editing ? url('log-view.php', ['id' => (int) $log['id']]) : url('logs.php')) ?>">
                        Cancel
                    </a>
                    <p class="form-hint text-center draft-status" data-draft-status hidden></p>
                </div>
            </div>

            <?php View::partial('asset-context', [
                'asset'  => $asset,
                'events' => $assetHistory,
            ]); ?>

            <?php if (feature_on('photos')): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('camera', '', 17) ?> Photos</h3>
                </div>
                <div class="card-body">
                    <label class="dropzone" data-dropzone
                           data-max-mb="<?= (int) round(Settings::maxUploadBytes() / 1048576) ?>">
                        <?= icon('camera', '', 24) ?>
                        <strong>Add photos</strong>
                        <span class="text-sm">A picture of the worn part is worth a paragraph.</span>
                        <input type="file" name="attachments[]" multiple
                               accept="<?= e(Uploader::acceptAttribute()) ?>" data-dropzone-input>
                    </label>
                    <div class="mt-3" data-dropzone-preview hidden></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // On a phone the sidebar lands below the form, so Save travels with the thumb.
          // Arriving from a work order, the two ways of saving are two buttons, so
          // the consequence is on the thing being pressed rather than in a tickbox
          // two screens up. "Still more to do" comes first in the source because a
          // stray Enter key fires the first submit control on the form. ?>
    <div class="form-sticky no-print<?= $closesWorkOrder ? ' is-two' : '' ?>">
        <?php if ($closesWorkOrder): ?>
            <button type="submit" name="close_work_order" value="0" class="btn btn-secondary btn-lg is-second">
                Save, still more to do
            </button>
            <button type="submit" name="close_work_order" value="1" class="btn btn-primary btn-lg is-first">
                <?= icon('check-circle', '', 18) ?>
                <?= $canCloseLinked ? 'Save and finish ' : 'Save and hand over ' ?><?= e((string) $workOrder['wo_number']) ?>
            </button>
        <?php else: ?>
            <button type="submit" class="btn btn-primary btn-lg">
                <?= icon('save', '', 18) ?> <?= $editing ? 'Save changes' : 'Save this log' ?>
            </button>
        <?php endif; ?>
    </div>
</form>
