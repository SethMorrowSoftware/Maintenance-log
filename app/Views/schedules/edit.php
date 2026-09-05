<?php
/**
 * Add or edit a preventive maintenance schedule.
 *
 * The concept people trip over is calendar-versus-meter, so the form asks that
 * as a plain choice with a worked example under each option, rather than
 * offering a "frequency type" dropdown and hoping.
 */

use App\Status;
use App\View;

$meterUnit = $asset === null ? 'hours' : (string) $asset['meter_type'];
$hasMeter  = $asset !== null && $meterUnit !== 'none';
?>

<form method="post" action="<?= e(url('schedule-edit.php', $editing ? ['id' => (int) $schedule['id']] : [])) ?>"
      data-validate data-guard>
    <?= csrf_field() ?>

    <div class="grid grid-sidebar">
        <div>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('calendar', '', 18) ?> What and where</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('asset-picker', [
                        'name'     => 'asset_id',
                        'label'    => 'Which machine?',
                        'value'    => $values['asset_id'],
                        'assets'   => $assets,
                        'required' => true,
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'name',
                        'label'       => 'What is the job?',
                        'type'        => 'text',
                        'value'       => $values['name'],
                        'required'    => true,
                        'placeholder' => '50 hour service',
                        'hint'        => 'What you would call it on a work sheet.',
                        'attrs'       => ['maxlength' => 191],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'description',
                        'label'       => 'Description',
                        'type'        => 'textarea',
                        'value'       => $values['description'],
                        'rows'        => 2,
                        'placeholder' => 'Oil change, air filter, chain and general check.',
                        'attrs'       => ['maxlength' => 2000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </div>

            <div class="card is-accent">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('refresh', '', 18) ?> How often?</h2>
                        <p class="card-subtitle">By the calendar, by the meter, or both</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'frequency_type',
                            'label'    => 'Repeat',
                            'type'     => 'select',
                            'value'    => $values['frequency_type'],
                            'options'  => [
                                'daily'      => 'Every day',
                                'weekly'     => 'Every week',
                                'monthly'    => 'Every month',
                                'quarterly'  => 'Every 3 months',
                                'semiannual' => 'Every 6 months',
                                'annual'     => 'Every year',
                                'days'       => 'Every N days',
                                'weeks'      => 'Every N weeks',
                                'months'     => 'Every N months',
                                'meter'      => 'By the meter only (no calendar)',
                            ],
                            'required' => true,
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'frequency_value',
                            'label' => 'N (for "every N")',
                            'type'  => 'number',
                            'value' => $values['frequency_value'],
                            'hint'  => 'Only used by the "every N" options.',
                            'attrs' => ['min' => 1, 'max' => 999],
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'   => 'meter_interval',
                        'label'  => 'Meter interval',
                        'type'   => 'decimal',
                        'value'  => $values['meter_interval'],
                        'suffix' => $hasMeter ? $meterUnit : 'units',
                        'hint'   => $hasMeter
                            ? 'For example 50 to service every 50 ' . $meterUnit . '. '
                              . 'Fill this in as well as a calendar interval and whichever comes first wins.'
                            : 'This machine has no meter, so leave this blank.',
                        'attrs'  => ['min' => '0', 'step' => '0.01'],
                    ]); ?>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'   => 'lead_time_days',
                            'label'  => 'Warn me this far ahead',
                            'type'   => 'number',
                            'value'  => $values['lead_time_days'],
                            'suffix' => 'days',
                            'hint'   => 'How long before it is due it starts showing as "due soon".',
                            'attrs'  => ['min' => 0, 'max' => 365],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'   => 'estimated_hours',
                            'label'  => 'Usually takes',
                            'type'   => 'decimal',
                            'value'  => $values['estimated_hours'],
                            'suffix' => 'hours',
                            'attrs'  => ['min' => '0', 'step' => '0.25'],
                        ]); ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('users', '', 18) ?> Who and how</h2>
                </div>
                <div class="card-body">
                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'    => 'assigned_to',
                            'label'   => 'Usually done by',
                            'type'    => 'select',
                            'value'   => $values['assigned_to'],
                            'options' => $assignees,
                            'empty'   => 'Anyone',
                            'hint'    => 'They get the reminder.',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'    => 'log_type',
                            'label'   => 'Records as',
                            'type'    => 'select',
                            'value'   => $values['log_type'],
                            'options' => Status::options('log_type'),
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'    => 'priority',
                            'label'   => 'Priority',
                            'type'    => 'select',
                            'value'   => $values['priority'],
                            'options' => Status::options('priority'),
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'    => 'checklist_id',
                        'label'   => 'Use a checklist',
                        'type'    => 'select',
                        'value'   => $values['checklist_id'],
                        'options' => $checklists,
                        'empty'   => 'No checklist',
                        'hint'    => 'Attaches an inspection checklist to this job.',
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'instructions',
                        'label'       => 'Instructions',
                        'type'        => 'textarea',
                        'value'       => $values['instructions'],
                        'rows'        => 4,
                        'placeholder' => "Drain oil warm. Replace the filter element. Check chain tension and clutch wear. Torque wheel nuts to 25 ft-lb.",
                        'hint'        => 'Shown to whoever picks the job up.',
                        'attrs'       => ['maxlength' => 5000, 'data-autogrow' => true],
                    ]); ?>

                    <label class="form-check" for="f_active">
                        <input type="checkbox" id="f_active" name="is_active" value="1"
                               <?= checked((int) $values['is_active']) ?>>
                        <span class="form-check-label">
                            This schedule is active
                            <small>Untick to pause it over the winter without deleting it.</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?= icon('save', '', 18) ?> <?= $editing ? 'Save changes' : 'Add schedule' ?>
                    </button>
                    <a class="btn btn-ghost btn-block mt-2" data-no-guard href="<?= e(url('schedules.php')) ?>">Cancel</a>
                </div>
            </div>

            <?php if ($editing): ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Current state</h3></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <dt>Last done</dt>
                            <dd><?= empty($schedule['last_performed_at'])
                                ? '<span class="text-subtle">Never</span>'
                                : e(App\Dates::datetime((string) $schedule['last_performed_at'])) ?></dd>
                            <dt>Next due</dt>
                            <dd>
                                <?php if (!empty($schedule['next_due_date'])): ?>
                                    <?= e(App\Dates::dateOnly((string) $schedule['next_due_date'])) ?>
                                <?php elseif ($schedule['next_due_meter'] !== null): ?>
                                    at <?= e(decimal($schedule['next_due_meter'])) ?> <?= e($meterUnit) ?>
                                <?php else: ?>
                                    <span class="text-subtle">Not yet worked out</span>
                                <?php endif; ?>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="card is-danger">
                    <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
                    <div class="card-body">
                        <p class="text-sm text-muted mb-3">
                            Deleting a schedule does not delete the maintenance already logged against it.
                        </p>
                        <?php View::partial('confirm-delete', [
                            'url'         => url('schedules.php'),
                            'id'          => (int) $schedule['id'],
                            'label'       => 'Delete this schedule',
                            'message'     => 'Delete "' . (string) $schedule['name'] . '"?',
                            'buttonClass' => 'btn btn-danger-outline btn-block',
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>
