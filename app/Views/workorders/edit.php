<?php
/**
 * Report an issue.
 *
 * Written for the ride operator who has just noticed something wrong, not for
 * the person who will fix it. Two required fields, plain questions, and the
 * scheduling detail tucked away for whoever triages it later.
 */

use App\Dates;
use App\Settings;
use App\Status;
use App\Uploader;
use App\View;
?>

<form method="post" action="<?= e(url('workorder-edit.php', $editing ? ['id' => (int) $workOrder['id']] : [])) ?>"
      enctype="multipart/form-data" data-validate data-guard
      <?= feature_on('drafts') ? 'data-draft="wo-' . ($editing ? (int) $workOrder['id'] : 'new') . '"' : '' ?>>
    <?= csrf_field() ?>

    <div class="grid grid-sidebar">
        <div>
            <div class="card is-accent">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('work-order', '', 18) ?> What is wrong?</h2>
                </div>
                <div class="card-body">

                    <?php View::partial('form-field', [
                        'name'        => 'title',
                        'label'       => 'What is the problem?',
                        'type'        => 'text',
                        'value'       => $values['title'],
                        'required'    => true,
                        'placeholder' => 'Kart 4 pulls to the left under braking',
                        'hint'        => 'One line. Enough for somebody to know what they are looking at.',
                        'attrs'       => ['autofocus' => true, 'maxlength' => 191],
                    ]); ?>

                    <?php View::partial('asset-picker', [
                        'name'     => 'asset_id',
                        'label'    => 'Which ' . asset_word() . '?',
                        'value'    => $values['asset_id'],
                        'assets'   => $assets,
                        'required' => false,
                        'hint'     => 'Leave blank if it is not about a particular ' . asset_word() . '.',
                        'emptyLabel' => 'Not about a specific ' . asset_word(),
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'description',
                        'label'       => 'Tell us more',
                        'type'        => 'textarea',
                        'value'       => $values['description'],
                        'rows'        => 4,
                        'placeholder' => "Started this morning. Gets worse the harder you brake. Pulled it off the track at 11am.",
                        'hint'        => 'When it started, what it does, anything you already tried.',
                        'attrs'       => ['maxlength' => 5000, 'data-autogrow' => true],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'     => 'priority',
                        'label'    => 'How urgent is it?',
                        'type'     => 'select',
                        'value'    => $values['priority'],
                        'options'  => Status::options('priority'),
                        'required' => true,
                        'hint'     => 'Urgent means guests are at risk or a ride is closed.',
                    ]); ?>

                    <label class="form-check" for="f_safety">
                        <input type="checkbox" id="f_safety" name="is_safety_issue" value="1"
                               <?= checked((int) $values['is_safety_issue']) ?>>
                        <span class="form-check-label">
                            This is a safety issue
                            <small>Flags it for immediate attention.</small>
                        </span>
                    </label>

                    <?php if (!$editing): ?>
                        <label class="form-check" for="f_oos">
                            <input type="checkbox" id="f_oos" name="took_out_of_service" value="1"
                                   <?= checked((int) $values['took_out_of_service']) ?>>
                            <span class="form-check-label">
                                I have taken it out of service
                                <small>Marks the <?= e(asset_word()) ?> unavailable to guests straight away.</small>
                            </span>
                        </label>

                        <?php // The mechanic who reports the fault is very often the one
                              // already under the kart. One tick puts their name on it and
                              // starts the clock, instead of a second trip through the job. ?>
                        <?php if (can('workorders.edit')): ?>
                            <label class="form-check" for="f_fixing">
                                <input type="checkbox" id="f_fixing" name="fixing_now" value="1"
                                       <?= checked((int) ($values['fixing_now'] ?? 0)) ?>>
                                <span class="form-check-label">
                                    I am fixing this now
                                    <small>Puts your name on it and marks it in progress.</small>
                                </span>
                            </label>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php // "How was it spotted?" matters to whoever reads the reports at the
                  // end of the month; it does not matter to the operator standing at a
                  // broken kart. It keeps its sensible default out of sight. ?>
            <details class="card" <?= $editing ? 'open' : '' ?>>
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h2 class="card-title"><?= icon('info', '', 18) ?> More detail</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'    => 'source',
                        'label'   => 'How was it spotted?',
                        'type'    => 'select',
                        'value'   => $values['source'],
                        'options' => Status::options('wo_source'),
                        'hint'    => 'Used by the reports. Leave it as it is if you are not sure.',
                    ]); ?>
                </div>
            </details>

            <?php if (can('workorders.assign')): ?>
                <details class="card" <?= $editing ? 'open' : '' ?>>
                    <summary class="card-header" style="cursor:pointer;list-style:none">
                        <h2 class="card-title"><?= icon('users', '', 18) ?> Who and when</h2>
                        <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                    </summary>
                    <div class="card-body">
                        <div class="form-row cols-3">
                            <?php View::partial('form-field', [
                                'name'    => 'assigned_to',
                                'label'   => 'Assign to',
                                'type'    => 'select',
                                'value'   => $values['assigned_to'],
                                'options' => $assignees,
                                'empty'   => 'Nobody yet',
                                'hint'    => 'They will get a notification.',
                            ]); ?>

                            <?php View::partial('form-field', [
                                'name'  => 'due_date',
                                'label' => 'Needed by',
                                'type'  => 'date',
                                'value' => Dates::inputDateOnly((string) $values['due_date']),
                            ]); ?>

                            <?php View::partial('form-field', [
                                'name'   => 'estimated_hours',
                                'label'  => 'Estimated time',
                                'type'   => 'decimal',
                                'value'  => $values['estimated_hours'],
                                'suffix' => 'hours',
                                'attrs'  => ['min' => '0', 'step' => '0.25'],
                            ]); ?>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
        </div>

        <div>
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?= icon('save', '', 18) ?> <?= $editing ? 'Save changes' : 'Report it' ?>
                    </button>
                    <a class="btn btn-ghost btn-block mt-2" data-no-guard
                       href="<?= e($editing ? url('workorder-view.php', ['id' => (int) $workOrder['id']]) : url('workorders.php')) ?>">
                        Cancel
                    </a>
                    <p class="form-hint text-center draft-status" data-draft-status hidden></p>
                </div>
            </div>

            <?php View::partial('asset-context', [
                'asset'      => $asset ?? null,
                'events'     => $assetHistory ?? [],
                'selectName' => 'asset_id',
            ]); ?>

            <?php if (feature_on('photos')): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title"><?= icon('camera', '', 17) ?> Photos</h3></div>
                <div class="card-body">
                    <label class="dropzone" data-dropzone
                           data-max-mb="<?= (int) round(Settings::maxUploadBytes() / 1048576) ?>">
                        <?= icon('camera', '', 24) ?>
                        <strong>Add a photo</strong>
                        <span class="text-sm">A picture of the problem saves a conversation.</span>
                        <input type="file" name="attachments[]" multiple
                               accept="<?= e(Uploader::acceptAttribute()) ?>" data-dropzone-input>
                    </label>
                    <div class="mt-3" data-dropzone-preview hidden></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // On a phone the sidebar lands below the form, so the button travels with the thumb. ?>
    <div class="form-sticky no-print">
        <button type="submit" class="btn btn-primary btn-lg">
            <?= icon('save', '', 18) ?> <?= $editing ? 'Save changes' : 'Report it' ?>
        </button>
    </div>
</form>
