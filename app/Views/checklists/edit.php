<?php
/**
 * The checklist builder.
 *
 * A checklist is a list of sentences somebody reads off a phone, so the builder
 * puts the sentence first and everything else out of the way. The extra
 * settings on each line live behind a "More" toggle: most items need nothing
 * but their text.
 */

use App\View;

$canDelete = $editing && can('checklists.manage');
?>

<form method="post" action="<?= e(url('checklist-edit.php', $editing ? ['id' => (int) $checklist['id']] : [])) ?>"
      data-guard>
    <?= csrf_field() ?>

    <div class="grid grid-sidebar">
        <div>
            <?php // ==================== The list itself ==================== ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('checklist', '', 18) ?> What gets checked</h2>
                        <p class="card-subtitle">
                            One line per thing to look at. Write them in the order somebody
                            would walk around the <?= e(asset_word()) ?>.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (has_error('items')): ?>
                        <div class="alert alert-error">
                            <?= icon('alert-circle', '', 18) ?>
                            <div class="alert-body"><?= e(error_for('items')) ?></div>
                        </div>
                    <?php endif; ?>

                    <div data-repeater data-repeater-index="<?= count($items) ?>">
                        <div data-repeater-rows>
                            <?php foreach ($items as $item): ?>
                                <div class="repeater-row" data-repeater-row>
                                    <input type="hidden" name="items[<?= (int) $item['id'] ?>][id]"
                                           value="<?= (int) $item['id'] ?>">

                                    <div class="builder-main">
                                        <span class="builder-handle" aria-hidden="true"><?= icon('menu', '', 16) ?></span>
                                        <input type="text" class="form-input builder-text"
                                               name="items[<?= (int) $item['id'] ?>][item_text]"
                                               value="<?= attr((string) $item['item_text']) ?>"
                                               maxlength="255" required
                                               aria-label="What to check"
                                               placeholder="Brakes stop the kart in a straight line">
                                        <select class="form-select builder-type"
                                                name="items[<?= (int) $item['id'] ?>][response_type]"
                                                aria-label="Kind of answer">
                                            <?php foreach ($responseTypes as $value => $label): ?>
                                                <option value="<?= e((string) $value) ?>"
                                                    <?= selected((string) $item['response_type'], (string) $value) ?>>
                                                    <?= e((string) $label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-ghost btn-sm" data-repeater-remove
                                                aria-label="Remove this line">
                                            <?= icon('trash', '', 15) ?>
                                        </button>
                                    </div>

                                    <div class="builder-flags">
                                        <label class="form-check">
                                            <input type="checkbox" value="1"
                                                   name="items[<?= (int) $item['id'] ?>][is_critical]"
                                                   <?= checked((int) $item['is_critical'], 1) ?>>
                                            <span class="form-check-label">Safety-critical</span>
                                        </label>
                                        <label class="form-check">
                                            <input type="checkbox" value="1"
                                                   name="items[<?= (int) $item['id'] ?>][is_required]"
                                                   <?= checked((int) $item['is_required'], 1) ?>>
                                            <span class="form-check-label">Must be answered</span>
                                        </label>
                                        <details class="builder-more">
                                            <summary>More</summary>
                                            <div class="form-row cols-4 mt-2">
                                                <div class="form-group">
                                                    <label class="form-label">Group heading</label>
                                                    <input type="text" class="form-input" list="section-list"
                                                           name="items[<?= (int) $item['id'] ?>][section]"
                                                           value="<?= attr((string) $item['section']) ?>"
                                                           maxlength="120" placeholder="Brakes">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Unit</label>
                                                    <input type="text" class="form-input" maxlength="20"
                                                           name="items[<?= (int) $item['id'] ?>][unit]"
                                                           value="<?= attr((string) $item['unit']) ?>"
                                                           placeholder="psi">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Lowest OK</label>
                                                    <input type="number" step="0.01" class="form-input"
                                                           name="items[<?= (int) $item['id'] ?>][min_value]"
                                                           value="<?= attr((string) ($item['min_value'] ?? '')) ?>">
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Highest OK</label>
                                                    <input type="number" step="0.01" class="form-input"
                                                           name="items[<?= (int) $item['id'] ?>][max_value]"
                                                           value="<?= attr((string) ($item['max_value'] ?? '')) ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Extra guidance</label>
                                                <input type="text" class="form-input" maxlength="500"
                                                       name="items[<?= (int) $item['id'] ?>][description]"
                                                       value="<?= attr((string) $item['description']) ?>"
                                                       placeholder="Shown in small print under the line.">
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <template data-repeater-template>
                            <div class="builder-main">
                                <span class="builder-handle" aria-hidden="true"></span>
                                <input type="text" class="form-input builder-text"
                                       name="items[new___INDEX__][item_text]" maxlength="255"
                                       aria-label="What to check"
                                       placeholder="Brakes stop the kart in a straight line">
                                <select class="form-select builder-type"
                                        name="items[new___INDEX__][response_type]" aria-label="Kind of answer">
                                    <?php foreach ($responseTypes as $value => $label): ?>
                                        <option value="<?= e((string) $value) ?>"
                                            <?= $value === 'pass_fail_na' ? 'selected' : '' ?>>
                                            <?= e((string) $label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-ghost btn-sm" data-repeater-remove
                                        aria-label="Remove this line">
                                    <?= icon('trash', '', 15) ?>
                                </button>
                            </div>
                            <div class="builder-flags">
                                <label class="form-check">
                                    <input type="checkbox" value="1" name="items[new___INDEX__][is_critical]">
                                    <span class="form-check-label">Safety-critical</span>
                                </label>
                                <label class="form-check">
                                    <input type="checkbox" value="1" name="items[new___INDEX__][is_required]" checked>
                                    <span class="form-check-label">Must be answered</span>
                                </label>
                                <details class="builder-more">
                                    <summary>More</summary>
                                    <div class="form-row cols-4 mt-2">
                                        <div class="form-group">
                                            <label class="form-label">Group heading</label>
                                            <input type="text" class="form-input" list="section-list"
                                                   name="items[new___INDEX__][section]" maxlength="120"
                                                   placeholder="Brakes">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Unit</label>
                                            <input type="text" class="form-input" maxlength="20"
                                                   name="items[new___INDEX__][unit]" placeholder="psi">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Lowest OK</label>
                                            <input type="number" step="0.01" class="form-input"
                                                   name="items[new___INDEX__][min_value]">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Highest OK</label>
                                            <input type="number" step="0.01" class="form-input"
                                                   name="items[new___INDEX__][max_value]">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Extra guidance</label>
                                        <input type="text" class="form-input" maxlength="500"
                                               name="items[new___INDEX__][description]"
                                               placeholder="Shown in small print under the line.">
                                    </div>
                                </details>
                            </div>
                        </template>

                        <button type="button" class="btn btn-secondary" data-repeater-add>
                            <?= icon('plus', '', 16) ?> Add a line
                        </button>
                    </div>

                    <datalist id="section-list">
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= attr((string) $section) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <p class="form-hint mt-3">
                        Mark a line <strong>safety-critical</strong> if failing it means the <?= e(asset_word()) ?>
                        should not carry guests. Failing one raises an urgent work order by itself.
                    </p>
                </div>
            </div>
        </div>

        <?php // ==================== Settings ==================== ?>
        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">About this checklist</h3></div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'        => 'name',
                        'label'       => 'Name',
                        'value'       => $values['name'],
                        'required'    => true,
                        'placeholder' => 'Daily go-kart check',
                        'attrs'       => ['maxlength' => 191, 'autofocus' => true],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'    => 'applies_to',
                        'label'   => 'Use it for',
                        'type'    => 'select',
                        'value'   => $values['applies_to'],
                        'options' => [
                            'category' => 'A kind of ' . asset_word() . ' (all go-karts, all rides…)',
                            'asset'    => 'One particular ' . asset_word(),
                            'all'      => 'Every ' . asset_word(),
                            'location' => 'An area — a place, not a ' . asset_word() . ' (the bowling desk, the arcade…)',
                        ],
                        'hint'    => 'Decides when this list is offered. An area checklist is run once for the area, with no ' . asset_word() . ' picked.',
                        'attrs'   => ['data-reveal' => 'scope'],
                    ]); ?>

                    <div data-reveal-for="scope" data-reveal-when="location">
                        <?php View::partial('form-field', [
                            'name'    => 'location_id',
                            'label'   => 'Which area',
                            'type'    => 'select',
                            'value'   => $values['location_id'],
                            'options' => $locations,
                            'empty'   => 'Choose…',
                            'hint'    => 'Areas are the locations under Categories & Locations.',
                        ]); ?>
                    </div>

                    <div data-reveal-for="scope" data-reveal-when="category">
                        <?php View::partial('form-field', [
                            'name'    => 'category_id',
                            'label'   => 'Which kind',
                            'type'    => 'select',
                            'value'   => $values['category_id'],
                            'options' => $categories,
                            'empty'   => 'Choose…',
                        ]); ?>
                    </div>

                    <div data-reveal-for="scope" data-reveal-when="asset">
                        <?php View::partial('form-field', [
                            'name'    => 'asset_id',
                            'label'   => 'Which ' . asset_word(),
                            'type'    => 'select',
                            'value'   => $values['asset_id'],
                            'options' => $assets,
                            'empty'   => 'Choose…',
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'    => 'frequency',
                        'label'   => 'How often it is meant to be done',
                        'type'    => 'select',
                        'value'   => $values['frequency'],
                        'options' => $frequencies,
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'   => 'estimated_minutes',
                        'label'  => 'Roughly how long it takes',
                        'type'   => 'number',
                        'value'  => $values['estimated_minutes'],
                        'suffix' => 'minutes',
                        'attrs'  => ['min' => 0, 'max' => 1440, 'step' => 1],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'        => 'description',
                        'label'       => 'Notes for whoever runs it',
                        'type'        => 'textarea',
                        'value'       => $values['description'],
                        'rows'        => 3,
                        'attrs'       => ['maxlength' => 2000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </div>

            <?php // ==================== When it should be done ==================== ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title"><?= icon('clock', '', 17) ?> When it should be done</h3>
                        <p class="card-subtitle">Give it a time and it becomes a timed check: counted as done, late or missed on Today's checks.</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'  => 'due_time',
                        'label' => 'Due by',
                        'type'  => 'time',
                        'value' => $values['due_time'],
                        'hint'  => 'Leave blank for a list that is only recorded when it is run, and never chased.',
                        'attrs' => ['step' => 300],
                    ]); ?>

                    <div class="form-group">
                        <label class="form-label">On these days</label>
                        <?php
                        $tickedDays = old('due_days', str_split((string) ($values['due_days'] ?: '1234567')));
                        $tickedDays = array_map('intval', is_array($tickedDays) ? $tickedDays : []);
                        ?>
                        <div class="day-picker" role="group" aria-label="Days of the week">
                            <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $dayNumber => $dayName): ?>
                                <label class="day-choice">
                                    <input type="checkbox" name="due_days[]" value="<?= $dayNumber ?>"
                                        <?= in_array($dayNumber, $tickedDays, true) ? 'checked' : '' ?>>
                                    <span><?= $dayName ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (has_error('due_days')): ?>
                            <p class="form-error"><?= e(error_for('due_days')) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'   => 'remind_minutes',
                        'label'  => 'Remind on Slack beforehand',
                        'type'   => 'number',
                        'value'  => $values['remind_minutes'],
                        'suffix' => 'minutes before',
                        'hint'   => 'Optional. A nudge listing what is still to do. Blank for none.',
                        'attrs'  => ['min' => 0, 'max' => 1440, 'step' => 5, 'placeholder' => '30'],
                    ]); ?>

                    <label class="form-check" for="f_alert_missed">
                        <input type="checkbox" id="f_alert_missed" name="alert_missed" value="1"
                            <?= checked((int) old('alert_missed', (int) $values['alert_missed']), 1) ?>>
                        <span class="form-check-label">
                            Post to Slack if it is not finished by then
                            <small>
                                <?php if ($slackOn): ?>
                                    Slack is connected. The message says what is still to do and who was due to do it.
                                <?php else: ?>
                                    Slack is not posting at the moment — turn it on, and "Checks not finished on time",
                                    under Settings → Slack. The people who manage checklists are told in the app either way.
                                <?php endif; ?>
                            </small>
                        </span>
                    </label>

                    <div class="form-row cols-2 mt-2">
                        <?php View::partial('form-field', [
                            'name'        => 'alert_channel',
                            'label'       => 'Slack channel',
                            'value'       => $values['alert_channel'],
                            'placeholder' => 'Same as Settings',
                            'hint'        => 'Blank uses the channel in Settings → Slack.',
                            'attrs'       => ['maxlength' => 80],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'alert_mention',
                            'label'       => 'Who to alert',
                            'value'       => $values['alert_mention'],
                            'placeholder' => '@here or U0123ABCD',
                            'hint'        => 'Added to the not-finished message. Blank for nobody.',
                            'attrs'       => ['maxlength' => 80],
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'   => 'escalate_minutes',
                        'label'  => 'Post again if still not finished after',
                        'type'   => 'number',
                        'value'  => $values['escalate_minutes'],
                        'suffix' => 'minutes',
                        'hint'   => 'Optional. The second message mentions whoever is set above, or the alert mention in Settings → Slack.',
                        'attrs'  => ['min' => 0, 'max' => 1440, 'step' => 5, 'placeholder' => '30'],
                    ]); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Rules</h3></div>
                <div class="card-body">
                    <label class="form-check" for="f_require_signature">
                        <input type="checkbox" id="f_require_signature" name="require_signature" value="1"
                            <?= checked((int) $values['require_signature'], 1) ?>>
                        <span class="form-check-label">
                            Ask for a name at the end
                            <small>Whoever runs it has to type their name to finish. It goes on
                            the printed record.</small>
                        </span>
                    </label>

                    <label class="form-check" for="f_require_meter">
                        <input type="checkbox" id="f_require_meter" name="require_meter" value="1"
                            <?= checked((int) $values['require_meter'], 1) ?>>
                        <span class="form-check-label">
                            Ask for the meter reading
                            <small>Hours or miles on the <?= e(asset_word()) ?>, if it has one. Skipped
                            automatically if a line above already asks for it.</small>
                        </span>
                    </label>

                    <label class="form-check" for="f_is_active">
                        <input type="checkbox" id="f_is_active" name="is_active" value="1"
                            <?= checked((int) $values['is_active'], 1) ?>>
                        <span class="form-check-label">
                            In use
                            <small>Untick to retire it without losing past inspections.</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <?= icon('save', '', 17) ?>
                    <?= $editing ? 'Save changes' : 'Create checklist' ?>
                </button>
                <a class="btn btn-ghost btn-block" href="<?= e(url('checklists.php')) ?>" data-no-guard>Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php if ($canDelete): ?>
    <div class="card is-danger mt-5">
        <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
        <div class="card-body flex items-center justify-between gap-3 flex-wrap">
            <p class="text-sm text-muted" style="margin:0">
                Deleting removes the template. Inspections already carried out keep their
                own copy of every line, so history is not affected.
            </p>
            <?php View::partial('confirm-delete', [
                'url'     => url('checklists.php'),
                'id'      => (int) $checklist['id'],
                'label'   => 'Delete this checklist',
                'message' => 'Delete “' . (string) $checklist['name'] . '”?',
            ]); ?>
        </div>
    </div>
<?php endif; ?>
